<?php

namespace Tests\Feature;

use App\Data\OperationsWorkItemDefinition;
use App\Data\VerifiedOperationsSourceOutcome;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundDestinationType;
use App\Enums\RefundReasonType;
use App\Enums\RefundStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\InventoryMovement;
use App\Models\OperationsWorkItem;
use App\Models\OperationsWorkItemHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\ShipmentReturnEvent;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OperationsQueueService;
use App\Services\OperationsWorkItemService;
use App\Services\OrderWorkflowService;
use App\Services\ShipmentReturnService;
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
        $admin = User::factory()->administrator()->create();
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
        $admin = User::factory()->administrator()->create();
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

    public function test_failed_delivery_and_write_off_proposal_create_and_auto_complete_work_items(): void
    {
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $admin = User::factory()->administrator()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->create(['user_id' => $customer->id]);
        $product = Product::factory()->create();
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'stock_qty' => 4, 'average_cost' => '300000.0000']);
        (new InventoryMovement)->forceFill([
            'variant_id' => $variant->id,
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => 1,
            'operation_key' => 'wc-test-import-'.$variant->id,
            'stock_before' => 0,
            'stock_delta' => 4,
            'stock_after' => 4,
            'reserved_before' => 0,
            'reserved_delta' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_delta' => 0,
            'quarantine_after' => 0,
            'unit_cost_basis' => '300000.0000',
            'average_cost_before' => '0.0000',
            'average_cost_after' => '300000.0000',
            'actor_id' => $staff->id,
            'actor_name_snapshot' => $staff->full_name,
            'created_at' => now()->utc(),
        ])->save();
        $cart = new Cart;
        $cart->forceFill(['user_id' => $customer->id, 'version' => 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => 1])->save();

        $checkout = app(CheckoutService::class);
        $order = $checkout->place($customer, $address->id, 'work-center-flow-1', $checkout->quote($customer, $address)['fingerprint'], null);

        $workflow = app(OrderWorkflowService::class);
        $workflow->confirmAndPack($order, $staff, OrderStatus::Pending->value);
        $workflow->ship($order->fresh(), $staff, OrderStatus::Packing->value, 'GHN', 'GHN-WC-1');
        $workflow->fail($order->fresh(), $staff, OrderStatus::OnDelivery->value, 'Khách từ chối nhận hàng');

        $shipment = $order->fresh()->shipment;
        $unit = $order->items()->with('units')->firstOrFail()->units->firstOrFail();

        $returnWorkItem = OperationsWorkItem::query()
            ->where('source_type', OperationsSourceType::Shipment->value)
            ->where('source_event_key', "shipment:{$shipment->id}:failed-return")
            ->sole();
        $this->assertSame(OperationsWorkItemStatus::Pending, $returnWorkItem->status);
        $this->assertSame($order->order_code, $returnWorkItem->source_reference);

        $returns = app(ShipmentReturnService::class);
        $returnItem = $returns->receiveUnit($shipment, $unit->id, $staff, 'Đã nhận lại kiện hoàn');
        $this->assertSame(OperationsWorkItemStatus::Completed, $returnWorkItem->fresh()->status);

        $returns->proposeWriteOff($returnItem, $staff, 'Hộp và đế bị hư hỏng nặng không thể bán lại.');
        $writeOffWorkItem = OperationsWorkItem::query()
            ->where('source_type', OperationsSourceType::Inventory->value)
            ->where('source_event_key', "shipment-return:{$returnItem->id}:write-off-approval")
            ->sole();
        $this->assertSame(OperationsWorkItemStatus::Pending, $writeOffWorkItem->status);

        $returns->approveWriteOff($returnItem->fresh(), $admin, 'Xác nhận ghi hỏng do hư hại vận chuyển.');
        $this->assertSame(OperationsWorkItemStatus::Completed, $writeOffWorkItem->fresh()->status);
    }

    public function test_new_order_moves_across_live_queues_from_pending_to_packing_and_leaves_after_ship(): void
    {
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->create(['user_id' => $customer->id]);
        $product = Product::factory()->create();
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'stock_qty' => 4, 'average_cost' => '300000.0000']);
        (new InventoryMovement)->forceFill([
            'variant_id' => $variant->id,
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => 1,
            'operation_key' => 'wc-live-queue-import-'.$variant->id,
            'stock_before' => 0,
            'stock_delta' => 4,
            'stock_after' => 4,
            'reserved_before' => 0,
            'reserved_delta' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_delta' => 0,
            'quarantine_after' => 0,
            'unit_cost_basis' => '300000.0000',
            'average_cost_before' => '0.0000',
            'average_cost_after' => '300000.0000',
            'actor_id' => $staff->id,
            'actor_name_snapshot' => $staff->full_name,
            'created_at' => now()->utc(),
        ])->save();

        $cart = new Cart;
        $cart->forceFill(['user_id' => $customer->id, 'version' => 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => 1])->save();

        $checkout = app(CheckoutService::class);
        $order = $checkout->place($customer, $address->id, 'work-center-live-1', $checkout->quote($customer, $address)['fingerprint'], null);

        $queues = app(OperationsQueueService::class);
        $this->assertGreaterThanOrEqual(1, $queues->counts(false)[OperationsQueueService::TYPE_ORDER_PENDING]);

        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index', ['type' => OperationsQueueService::TYPE_ORDER_PENDING, 'search' => $order->order_code]))
            ->assertOk()
            ->assertSee($order->order_code);

        $workflow = app(OrderWorkflowService::class);
        $workflow->confirmAndPack($order, $staff, OrderStatus::Pending->value);

        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index', ['type' => OperationsQueueService::TYPE_ORDER_PENDING, 'search' => $order->order_code]))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 0);

        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index', ['type' => OperationsQueueService::TYPE_ORDER_PACKING, 'search' => $order->order_code]))
            ->assertOk()
            ->assertSee($order->order_code)
            ->assertViewHas('items', fn ($items) => $items->total() === 1);

        $workflow->ship($order->fresh(), $staff, OrderStatus::Packing->value, 'GHN', 'GHN-LIVE-1');

        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index', ['search' => $order->order_code]))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 0);
    }

    public function test_admin_only_queues_and_work_items_are_hidden_and_forbidden_for_staff(): void
    {
        $admin = User::factory()->administrator()->create();
        $staff = User::factory()->staff()->create();

        $refundOrder = Order::factory()->create([
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => OrderPaymentStatus::RefundPending,
        ]);
        $refund = Refund::factory()->create([
            'order_id' => $refundOrder->id,
            'status' => RefundStatus::Requested,
            'requested_by' => $staff->id,
        ]);

        $refundWorkItem = OperationsWorkItem::factory()->create([
            'source_type' => OperationsSourceType::Payment,
            'source_event_key' => "refund:{$refund->id}:approval",
            'source_reference' => $refundOrder->order_code,
            'created_by' => $staff->id,
        ]);

        $writeOffWorkItem = OperationsWorkItem::factory()->create([
            'source_type' => OperationsSourceType::Inventory,
            'source_event_key' => 'shipment-return:999999:write-off-approval',
            'source_reference' => 'ORD-WO-999',
            'created_by' => $staff->id,
        ]);

        $queues = app(OperationsQueueService::class);
        $this->assertSame(0, $queues->counts(false)[OperationsQueueService::TYPE_REFUND_APPROVAL]);
        $this->assertGreaterThanOrEqual(1, $queues->counts(true)[OperationsQueueService::TYPE_REFUND_APPROVAL]);

        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index'))
            ->assertOk()
            ->assertDontSee('Duyệt hoàn tiền')
            ->assertDontSee('Hàng hỏng chờ xử lý');

        $this->actingAs($admin)
            ->get(route('admin.operations-work-items.index'))
            ->assertOk()
            ->assertSee('Duyệt hoàn tiền')
            ->assertSee('Hàng hỏng chờ xử lý');

        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.show', $refundWorkItem))
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('admin.operations-work-items.update', $refundWorkItem), [
                'version' => 1,
                'status' => OperationsWorkItemStatus::InProgress->value,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('admin.operations-work-items.update', $writeOffWorkItem), [
                'version' => 1,
                'status' => OperationsWorkItemStatus::InProgress->value,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.operations-work-items.update', $refundWorkItem), [
                'version' => 1,
                'status' => OperationsWorkItemStatus::InProgress->value,
                'note' => 'Admin đang kiểm tra hoàn tiền.',
            ])
            ->assertRedirect(route('admin.operations-work-items.show', $refundWorkItem));
    }

    public function test_backfill_command_supports_dry_run_is_idempotent_and_skips_completed_items(): void
    {
        $admin = User::factory()->administrator()->create();
        $staff = User::factory()->staff()->create();

        $failedOrder = Order::factory()->create(['order_status' => OrderStatus::Failed]);
        $shipment = Shipment::factory()->create([
            'order_id' => $failedOrder->id,
            'status' => ShipmentStatus::Returning,
            'failed_at' => now()->utc(),
        ]);

        $completedReturnOrder = Order::factory()->create(['order_status' => OrderStatus::Failed]);
        $completedShipment = Shipment::factory()->create([
            'order_id' => $completedReturnOrder->id,
            'status' => ShipmentStatus::Returning,
            'failed_at' => now()->utc(),
        ]);
        OperationsWorkItem::factory()->create([
            'source_type' => OperationsSourceType::Shipment,
            'source_event_key' => "shipment:{$completedShipment->id}:failed-return",
            'source_reference' => $completedReturnOrder->order_code,
            'status' => OperationsWorkItemStatus::Completed,
            'created_by' => $admin->id,
        ]);

        $refundOrder = Order::factory()->create([
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => OrderPaymentStatus::RefundPending,
        ]);
        $refund = Refund::factory()->create([
            'order_id' => $refundOrder->id,
            'status' => RefundStatus::Requested,
            'reason_type' => RefundReasonType::OrderCancelled,
            'destination_type' => RefundDestinationType::Cash,
            'requested_by' => $staff->id,
        ]);

        $failedOrderItem = OrderItem::factory()->create(['order_id' => $failedOrder->id]);
        $failedUnit = OrderItemUnit::factory()->create(['order_item_id' => $failedOrderItem->id]);

        $incidentEvent = new ShipmentReturnEvent;
        $incidentEvent->forceFill([
            'shipment_id' => $shipment->id,
            'shipment_return_item_id' => null,
            'order_item_unit_id' => $failedUnit->id,
            'event_type' => ShipmentReturnEvent::MISSING_UNIT,
            'actor_id' => $staff->id,
            'note' => 'Thiếu 1 đôi trong kiện hoàn.',
            'occurred_at' => now()->utc(),
            'created_at' => now()->utc(),
        ])->save();

        $this->artisan('operations:backfill-work-items', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('operations_work_items', [
            'source_type' => OperationsSourceType::Shipment->value,
            'source_event_key' => "shipment:{$shipment->id}:failed-return",
        ]);
        $this->assertDatabaseMissing('operations_work_items', [
            'source_type' => OperationsSourceType::Payment->value,
            'source_event_key' => "refund:{$refund->id}:approval",
        ]);

        $this->artisan('operations:backfill-work-items')
            ->assertExitCode(0);

        $this->assertDatabaseHas('operations_work_items', [
            'source_type' => OperationsSourceType::Shipment->value,
            'source_event_key' => "shipment:{$shipment->id}:failed-return",
            'status' => OperationsWorkItemStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('operations_work_items', [
            'source_type' => OperationsSourceType::Payment->value,
            'source_event_key' => "refund:{$refund->id}:approval",
            'status' => OperationsWorkItemStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('operations_work_items', [
            'source_type' => OperationsSourceType::Shipment->value,
            'source_event_key' => "shipment-return-event:{$incidentEvent->id}",
            'status' => OperationsWorkItemStatus::Pending->value,
        ]);

        $completedCount = OperationsWorkItem::query()
            ->where('source_type', OperationsSourceType::Shipment->value)
            ->where('source_event_key', "shipment:{$completedShipment->id}:failed-return")
            ->count();
        $this->assertSame(1, $completedCount);

        // Second run must remain completely idempotent
        $this->artisan('operations:backfill-work-items')
            ->assertExitCode(0);

        $this->assertSame(1, OperationsWorkItem::query()->where('source_event_key', "shipment:{$shipment->id}:failed-return")->count());
        $this->assertSame(1, OperationsWorkItem::query()->where('source_event_key', "refund:{$refund->id}:approval")->count());
    }

    public function test_work_center_renders_three_columns_oldest_first_within_priority_and_single_filter_control(): void
    {
        $staff = User::factory()->staff()->create();

        $olderOrder = Order::factory()->create([
            'order_status' => OrderStatus::Pending,
            'created_at' => CarbonImmutable::parse('2026-11-01 08:00:00', 'UTC'),
        ]);
        $newerOrder = Order::factory()->create([
            'order_status' => OrderStatus::Pending,
            'created_at' => CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC'),
        ]);

        $queues = app(OperationsQueueService::class);
        $this->assertArrayNotHasKey(OperationsQueueService::TYPE_ORDER_CONFIRMED, $queues->queueDefinitions(false));

        $pageItems = $queues->paginateActive(false, OperationsQueueService::TYPE_ORDER_PENDING)->items();
        $references = array_column($pageItems, 'reference');
        $olderPos = array_search($olderOrder->order_code, $references, true);
        $newerPos = array_search($newerOrder->order_code, $references, true);
        $this->assertNotFalse($olderPos);
        $this->assertNotFalse($newerPos);
        $this->assertLessThan($newerPos, $olderPos);

        $response = $this->actingAs($staff)->get(route('admin.operations-work-items.index'));
        $response->assertOk()
            ->assertSee('Việc cần làm')
            ->assertSee('Thông tin liên quan')
            ->assertSee('Đã chờ')
            ->assertDontSee('Thao tác')
            ->assertDontSee('Trạng thái')
            ->assertDontSee('queue-type-filter')
            ->assertDontSee('<select', false)
            ->assertDontSee('Chờ đóng gói (đơn cũ)')
            ->assertSeeInOrder([$olderOrder->order_code, $newerOrder->order_code]);

        // Explicit ?type=order_confirmed is still supported when count is 0
        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index', ['type' => OperationsQueueService::TYPE_ORDER_CONFIRMED]))
            ->assertOk()
            ->assertSee('Chờ đóng gói (đơn cũ)');

        // Completed tab shows concise note linking to admin.orders.index
        $this->actingAs($staff)
            ->get(route('admin.operations-work-items.index', ['list' => 'completed']))
            ->assertOk()
            ->assertSee(route('admin.orders.index'), false);
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
