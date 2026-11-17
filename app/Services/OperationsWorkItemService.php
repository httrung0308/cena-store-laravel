<?php

namespace App\Services;

use App\Data\OperationsWorkItemDefinition;
use App\Data\VerifiedOperationsSourceOutcome;
use App\Enums\ErrorCode;
use App\Enums\OperationsWorkItemStatus;
use App\Enums\RoleSlug;
use App\Exceptions\DomainException;
use App\Models\OperationsWorkItem;
use App\Models\OperationsWorkItemHistory;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use LogicException;

final class OperationsWorkItemService
{
    public function __construct(private readonly BusinessClock $clock) {}

    /** Creation must share the transaction that commits the verified source event. */
    public function create(OperationsWorkItemDefinition $definition, User $actor): OperationsWorkItem
    {
        $this->requireTransaction();
        $now = $this->clock->now();
        $occurredAtUtc = $definition->occurredAt->setTimezone('UTC');
        $inserted = DB::table('operations_work_items')->insertOrIgnore([
            'source_type' => $definition->sourceType->value,
            'source_event_key' => $definition->sourceEventKey,
            'source_reference' => $definition->sourceReference,
            'source_label' => $definition->sourceLabel,
            'title' => $definition->title,
            'content' => $definition->content,
            'occurred_at' => $occurredAtUtc->format('Y-m-d H:i:s.u'),
            'status' => OperationsWorkItemStatus::Pending->value,
            'version' => 1,
            'created_by' => $actor->id,
            'created_at' => $now->format('Y-m-d H:i:s.u'),
            'updated_at' => $now->format('Y-m-d H:i:s.u'),
        ]);
        $item = OperationsWorkItem::query()
            ->where('source_type', $definition->sourceType->value)
            ->where('source_event_key', $definition->sourceEventKey)
            ->lockForUpdate()
            ->first();

        if ($item === null) {
            throw new LogicException('An ignored work item insert did not match the unique source key.');
        }
        if ($inserted === 1) {
            $this->appendHistory($item, $actor, null, OperationsWorkItemStatus::Pending, null, null);

            return $item->refresh();
        }
        if (! $this->matchesDefinition($item, $definition)) {
            throw new DomainException(ErrorCode::IdempotencyMismatch);
        }

        return $item;
    }

    public function update(OperationsWorkItem $workItem, User $actor, int $expectedVersion, OperationsWorkItemStatus $status, ?string $note): OperationsWorkItem
    {
        return DB::transaction(function () use ($workItem, $actor, $expectedVersion, $status, $note): OperationsWorkItem {
            $locked = OperationsWorkItem::query()->whereKey($workItem->id)->lockForUpdate()->firstOrFail();
            $this->assertVersion($locked, $expectedVersion);
            $current = $locked->status;
            $this->assertTransition($current, $status);
            $this->saveUpdate($locked, $actor, $current, $status, $note, null);

            return $locked->refresh();
        }, 3);
    }

    /** Completion is reserved for a server-side integration carrying a matching verified outcome. */
    public function complete(OperationsWorkItem $workItem, User $actor, int $expectedVersion, VerifiedOperationsSourceOutcome $outcome, ?string $note = null): OperationsWorkItem
    {
        return DB::transaction(function () use ($workItem, $actor, $expectedVersion, $outcome, $note): OperationsWorkItem {
            $locked = OperationsWorkItem::query()->whereKey($workItem->id)->lockForUpdate()->firstOrFail();
            $this->assertVersion($locked, $expectedVersion);
            if ($locked->source_type !== $outcome->sourceType || $locked->source_event_key !== $outcome->sourceEventKey) {
                throw new DomainException(ErrorCode::StateConflict, ['work_item' => ['Kết quả xác minh không thuộc công việc này.']]);
            }
            $this->assertTransition($locked->status, OperationsWorkItemStatus::Completed);
            $this->saveUpdate($locked, $actor, $locked->status, OperationsWorkItemStatus::Completed, $note, $outcome->outcomeCode);

            return $locked->refresh();
        }, 3);
    }

    private function saveUpdate(OperationsWorkItem $item, User $actor, OperationsWorkItemStatus $from, OperationsWorkItemStatus $to, ?string $note, ?string $outcomeCode): void
    {
        $item->status = $to;
        $item->version++;
        $item->updated_at = $this->clock->now();
        $item->save();
        $this->appendHistory($item, $actor, $from, $to, $note, $outcomeCode);
    }

    private function appendHistory(OperationsWorkItem $item, User $actor, ?OperationsWorkItemStatus $from, OperationsWorkItemStatus $to, ?string $note, ?string $outcomeCode): void
    {
        $roles = $actor->roles()->pluck('slug')->map(static fn (RoleSlug $slug): string => $slug->value)->sort()->values()->all();
        (new OperationsWorkItemHistory)->forceFill([
            'work_item_id' => $item->id,
            'actor_id' => $actor->id,
            'actor_name_snapshot' => $actor->full_name,
            'actor_roles_snapshot' => $roles,
            'from_status' => $from,
            'to_status' => $to,
            'outcome_code' => $outcomeCode,
            'note' => $note,
            'snapshot' => [
                'source_type' => $item->source_type->value,
                'source_reference' => $item->source_reference,
                'source_label' => $item->source_label,
                'title' => $item->title,
                'content' => $item->content,
                'occurred_at' => $item->occurred_at->toISOString(),
                'version' => $item->version,
            ],
            'created_at' => $this->clock->now(),
        ])->save();
    }

    private function assertTransition(OperationsWorkItemStatus $from, OperationsWorkItemStatus $to): void
    {
        $valid = $from === $to
            || ($from === OperationsWorkItemStatus::Pending && $to === OperationsWorkItemStatus::InProgress)
            || ($from === OperationsWorkItemStatus::InProgress && $to === OperationsWorkItemStatus::Pending)
            || ($from === OperationsWorkItemStatus::InProgress && $to === OperationsWorkItemStatus::Completed);
        if (! $valid || $from === OperationsWorkItemStatus::Completed) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }

    private function assertVersion(OperationsWorkItem $item, int $expectedVersion): void
    {
        if ($item->version !== $expectedVersion) {
            throw new DomainException(ErrorCode::StateConflict, ['work_item' => ['Công việc đã được cập nhật. Vui lòng tải lại trang.']]);
        }
    }

    private function matchesDefinition(OperationsWorkItem $item, OperationsWorkItemDefinition $definition): bool
    {
        return $item->source_event_key === $definition->sourceEventKey
            && $item->source_reference === $definition->sourceReference
            && $item->source_label === $definition->sourceLabel
            && $item->title === $definition->title
            && $item->content === $definition->content
            && $item->occurred_at->equalTo($definition->occurredAt);
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Operations work item creation requires the source transaction.');
        }
    }
}
