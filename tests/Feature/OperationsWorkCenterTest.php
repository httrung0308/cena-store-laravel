<?php

namespace Tests\Feature;

use App\Data\OperationsWorkItemDefinition;
use App\Data\VerifiedOperationsSourceOutcome;
use App\Enums\ErrorCode;
use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use App\Exceptions\DomainException;
use App\Models\OperationsWorkItem;
use App\Models\OperationsWorkItemHistory;
use App\Models\User;
use App\Services\OperationsWorkItemService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsWorkCenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_server_creation_is_idempotent_and_rejects_definition_mismatch(): void
    {
        $actor = User::factory()->staff()->create();
        $definition = $this->definition();
        $service = app(OperationsWorkItemService::class);

        $item = DB::transaction(fn () => $service->create($definition, $actor));
        $replay = DB::transaction(fn () => $service->create($definition, $actor));

        $this->assertSame($item->id, $replay->id);
        $this->assertSame(1, OperationsWorkItemHistory::query()->where('work_item_id', $item->id)->count());
        try {
            DB::transaction(fn () => $service->create($this->definition(title: 'Nội dung khác'), $actor));
            $this->fail('A source event replay with a different definition must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(ErrorCode::IdempotencyMismatch, $exception->errorCode);
        }
    }

    public function test_binary_event_key_collation_keeps_case_distinct_source_events_separate(): void
    {
        $actor = User::factory()->staff()->create();
        $service = app(OperationsWorkItemService::class);
        $upper = DB::transaction(fn () => $service->create($this->definition('Order:event:case'), $actor));
        $lower = DB::transaction(fn () => $service->create($this->definition('order:event:case'), $actor));

        $this->assertNotSame($upper->id, $lower->id);
        $this->assertSame(2, OperationsWorkItem::query()->whereIn('source_event_key', ['Order:event:case', 'order:event:case'])->count());
    }

    public function test_creation_normalizes_occurred_at_to_utc_and_replays_the_same_instant(): void
    {
        $actor = User::factory()->staff()->create();
        $service = app(OperationsWorkItemService::class);
        $localDefinition = $this->definition(occurredAt: CarbonImmutable::parse('2026-11-10 15:00:00', 'Asia/Ho_Chi_Minh'));
        $utcDefinition = $this->definition(occurredAt: CarbonImmutable::parse('2026-11-10 08:00:00', 'UTC'));

        $item = DB::transaction(fn () => $service->create($localDefinition, $actor));
        $replay = DB::transaction(fn () => $service->create($utcDefinition, $actor));

        $this->assertSame($item->id, $replay->id);
        $this->assertSame('2026-11-10 08:00:00.000000', $item->getRawOriginal('occurred_at'));
        $this->assertSame(1, OperationsWorkItemHistory::query()->where('work_item_id', $item->id)->count());
    }

    public function test_updates_enforce_legal_edges_version_and_append_actor_snapshot_history(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $service = app(OperationsWorkItemService::class);
        $item = DB::transaction(fn () => $service->create($this->definition(), $staff));

        $started = $service->update($item, $staff, 1, OperationsWorkItemStatus::InProgress, 'Đang kiểm tra.');
        $returned = $service->update($started, $admin, 2, OperationsWorkItemStatus::Pending, 'Chờ thêm thông tin.');
        $this->assertSame(OperationsWorkItemStatus::Pending, $returned->status);
        $this->assertSame(3, $returned->version);
        $this->assertSame(['admin'], $returned->histories()->latest('id')->firstOrFail()->actor_roles_snapshot);
        $this->assertSame($admin->full_name, $returned->histories()->latest('id')->firstOrFail()->actor_name_snapshot);
        $this->assertSame(3, $returned->histories()->count());

        try {
            $service->update($returned, $staff, 1, OperationsWorkItemStatus::InProgress, null);
            $this->fail('A stale version must not overwrite the current state.');
        } catch (DomainException $exception) {
            $this->assertSame(ErrorCode::StateConflict, $exception->errorCode);
        }

        try {
            $service->update($returned, $staff, 3, OperationsWorkItemStatus::Completed, null);
            $this->fail('HTTP-facing updates cannot complete work items.');
        } catch (DomainException $exception) {
            $this->assertSame(ErrorCode::StateConflict, $exception->errorCode);
        }
        $this->assertSame(3, $returned->fresh()->version);
    }

    public function test_completion_requires_matching_typed_source_outcome_and_is_terminal(): void
    {
        $staff = User::factory()->staff()->create();
        $service = app(OperationsWorkItemService::class);
        $item = DB::transaction(fn () => $service->create($this->definition(), $staff));
        $item = $service->update($item, $staff, 1, OperationsWorkItemStatus::InProgress, null);

        try {
            $service->complete($item, $staff, 2, new VerifiedOperationsSourceOutcome(OperationsSourceType::Order, 'different-event', 'ORDER_VERIFIED'));
            $this->fail('A verified outcome for another source event must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(ErrorCode::StateConflict, $exception->errorCode);
        }

        $completed = $service->complete($item, $staff, 2, new VerifiedOperationsSourceOutcome(OperationsSourceType::Order, 'order:event:17', 'ORDER_VERIFIED'), 'Đã xác minh.');
        $this->assertSame(OperationsWorkItemStatus::Completed, $completed->status);
        $history = $completed->histories()->latest('id')->firstOrFail();
        $this->assertSame('ORDER_VERIFIED', $history->outcome_code);
        $this->assertSame('Đã xác minh.', $history->note);

        try {
            $service->update($completed, $staff, 3, OperationsWorkItemStatus::Pending, null);
            $this->fail('Completed work items are immutable terminal records.');
        } catch (DomainException $exception) {
            $this->assertSame(ErrorCode::StateConflict, $exception->errorCode);
        }
    }

    public function test_admin_and_staff_can_use_work_center_but_customer_and_guest_cannot(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $item = OperationsWorkItem::factory()->create(['created_by' => $staff->id]);

        $this->actingAs($staff)->get(route('admin.operations-work-items.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.operations-work-items.show', $item))->assertOk();
        $this->actingAs($customer)->get(route('admin.operations-work-items.index'))->assertForbidden();
        $this->get(route('admin.operations-work-items.show', $item))->assertForbidden();
        $this->patch(route('admin.operations-work-items.update', $item), ['version' => 1, 'status' => 'IN_PROGRESS'])->assertForbidden();
        auth()->logout();
        $this->get(route('admin.operations-work-items.index'))->assertRedirect();
    }

    public function test_page_filters_active_and_completed_lists_and_http_cannot_submit_completed(): void
    {
        $staff = User::factory()->staff()->create();
        $service = app(OperationsWorkItemService::class);
        $pending = DB::transaction(fn () => $service->create($this->definition(), $staff));
        $complete = DB::transaction(fn () => $service->create($this->definition('order:event:18', 'Việc hoàn tất'), $staff));
        $complete = $service->update($complete, $staff, 1, OperationsWorkItemStatus::InProgress, null);
        $complete = $service->complete($complete, $staff, 2, new VerifiedOperationsSourceOutcome(OperationsSourceType::Order, 'order:event:18', 'ORDER_VERIFIED'));

        $this->actingAs($staff)->get(route('admin.operations-work-items.index', ['search' => 'Kiểm tra']))->assertOk()->assertSee($pending->title)->assertDontSee($complete->title);
        $this->get(route('admin.operations-work-items.index', ['list' => 'completed']))->assertOk()->assertSee($complete->title)->assertDontSee($pending->title);
        $this->patch(route('admin.operations-work-items.update', $pending), ['version' => 1, 'status' => 'COMPLETED'])->assertSessionHasErrors('status');
        $this->assertSame(OperationsWorkItemStatus::Pending, $pending->fresh()->status);
    }

    private function definition(string $key = 'order:event:17', string $title = 'Kiểm tra yêu cầu hoàn hàng', ?CarbonImmutable $occurredAt = null): OperationsWorkItemDefinition
    {
        return new OperationsWorkItemDefinition(
            OperationsSourceType::Order,
            $key,
            'ORD-2026-017',
            'Đơn hàng',
            $title,
            'Nhân viên cần xác minh nội dung phát sinh từ nghiệp vụ đã được ghi nhận.',
            $occurredAt ?? CarbonImmutable::parse('2026-11-10 08:00:00', 'UTC'),
        );
    }
}
