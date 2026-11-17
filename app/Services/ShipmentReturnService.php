<?php

namespace App\Services;

use App\Data\InventoryChange;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderStatus;
use App\Enums\OrderUnitState;
use App\Enums\ReturnDisposition;
use App\Enums\RoleSlug;
use App\Enums\SalesLedgerType;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentReturnEvent;
use App\Models\ShipmentReturnItem;
use App\Models\User;
use App\Models\UserRole;
use App\Support\BusinessClock;
use App\Support\Cost;
use App\Support\OperationKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ShipmentReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SalesLedgerService $ledger,
        private readonly BusinessClock $clock,
    ) {}

    public function receiveUnit(Shipment $shipment, int $unitId, User $actor, string $note): ShipmentReturnItem
    {
        $note = $this->normalizeNote($note, 5);

        return DB::transaction(function () use ($shipment, $unitId, $actor, $note): ShipmentReturnItem {
            $currentActor = $this->lockActor($actor, RoleSlug::Staff);
            $context = $this->lockShipmentContext($shipment->id);
            $order = $context['order'];
            $lockedShipment = $context['shipment'];
            $units = $context['units'];

            $unit = $units->firstWhere('id', $unitId);
            if ($unit === null) {
                throw new DomainException(ErrorCode::NotFound);
            }
            $existing = ShipmentReturnItem::query()->where('order_item_unit_id', $unit->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->shipment_id !== $lockedShipment->id) {
                    throw new DomainException(ErrorCode::StateConflict);
                }

                return $existing;
            }
            $this->assertReturning($order, $lockedShipment);
            if ($unit->state !== OrderUnitState::Outbound) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            if ($unit->original_cogs_amount === null) {
                throw new DomainException(ErrorCode::InvalidCost);
            }

            $now = $this->clock->now();
            $returnItem = new ShipmentReturnItem;
            $returnItem->forceFill([
                'shipment_id' => $lockedShipment->id,
                'order_item_unit_id' => $unit->id,
                'variant_id' => $unit->current_variant_id,
                'received_by' => $currentActor->id,
                'received_at' => $now,
                'disposition' => ReturnDisposition::Pending,
                'cost_basis' => Cost::normalize($unit->original_cogs_amount),
                'note' => $note,
                'created_at' => $now,
                'updated_at' => $now,
            ])->save();

            $this->inventory->receiveQuarantine(new InventoryChange(
                OperationKey::validate("shipment-return:{$returnItem->id}:receive"),
                $unit->current_variant_id,
                InventoryAction::ShipmentReturnReceived,
                InventoryReferenceType::ShipmentReturnItem,
                $returnItem->id,
                1,
                Cost::normalize($unit->original_cogs_amount),
                $currentActor->id,
                $note,
            ));

            $unit->state = OrderUnitState::ReturnedToStore;
            $unit->updated_at = $now;
            $unit->save();
            $this->recordEvent($returnItem, ShipmentReturnEvent::RECEIVED, $currentActor, $note, $now);

            $receivedUnitCount = ShipmentReturnItem::query()
                ->where('shipment_id', $lockedShipment->id)
                ->whereIn('order_item_unit_id', $units->modelKeys())
                ->count();

            if ($receivedUnitCount === $units->count() && $lockedShipment->status === ShipmentStatus::Returning) {
                $lockedShipment->status = ShipmentStatus::ReturnReceived;
                $lockedShipment->return_received_at = $now;
                $lockedShipment->updated_at = $now;
                $lockedShipment->save();
            }

            return $returnItem->fresh();
        }, 3);
    }

    public function recordIncident(Shipment $shipment, int $unitId, User $actor, string $incidentType, string $note): ShipmentReturnEvent
    {
        $note = $this->normalizeNote($note, 20, 1000);
        $incidentTypes = [ShipmentReturnEvent::MISSING_UNIT, ShipmentReturnEvent::SKU_MISMATCH, ShipmentReturnEvent::DAMAGED_UNIT, ShipmentReturnEvent::NO_EVIDENCE];
        if (! in_array($incidentType, $incidentTypes, true)) {
            throw new DomainException(ErrorCode::InvalidPayload, ['incident_type' => ['Loại bất thường không hợp lệ.']]);
        }

        return DB::transaction(function () use ($shipment, $unitId, $actor, $incidentType, $note): ShipmentReturnEvent {
            $currentActor = $this->lockActor($actor, RoleSlug::Staff);
            $context = $this->lockShipmentContext($shipment->id);
            $unit = $context['units']->firstWhere('id', $unitId);
            if ($unit === null) {
                throw new DomainException(ErrorCode::NotFound);
            }
            $existing = ShipmentReturnEvent::query()
                ->where('shipment_id', $context['shipment']->id)
                ->where('order_item_unit_id', $unit->id)
                ->where('event_type', $incidentType)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }
            $this->assertReturnShipmentState($context['order'], $context['shipment']);
            $returnItem = ShipmentReturnItem::query()->where('order_item_unit_id', $unit->id)->lockForUpdate()->first();
            $requiresUnreceivedUnit = in_array($incidentType, [ShipmentReturnEvent::MISSING_UNIT, ShipmentReturnEvent::SKU_MISMATCH], true);
            $validUnreceived = $unit->state === OrderUnitState::Outbound && $returnItem === null && $context['shipment']->status === ShipmentStatus::Returning;
            $validQuarantined = $unit->state === OrderUnitState::ReturnedToStore
                && $returnItem?->disposition === ReturnDisposition::Pending;
            if (($requiresUnreceivedUnit && ! $validUnreceived)
                || (! $requiresUnreceivedUnit && ! $validUnreceived && ! $validQuarantined)) {
                throw new DomainException(ErrorCode::StateConflict);
            }

            return $this->recordShipmentEvent($context['shipment']->id, $unit->id, null, $incidentType, $currentActor, $note, $this->clock->now());
        }, 3);
    }

    public function restock(ShipmentReturnItem $returnItem, User $actor, string $note): ShipmentReturnItem
    {
        $note = $this->normalizeNote($note, 20);

        return DB::transaction(function () use ($returnItem, $actor, $note): ShipmentReturnItem {
            $currentActor = $this->lockActor($actor, RoleSlug::Staff);
            $context = $this->lockReturnContext($returnItem->id);
            $lockedReturnItem = $context['return_item'];

            if ($lockedReturnItem->disposition === ReturnDisposition::Restock) {
                return $lockedReturnItem;
            }
            $this->assertPendingDisposition($context);
            if ($lockedReturnItem->events()->whereIn('event_type', [ShipmentReturnEvent::WRITE_OFF_PROPOSED, ShipmentReturnEvent::WRITE_OFF_APPROVED])->exists()) {
                throw new DomainException(ErrorCode::StateConflict);
            }

            $now = $this->clock->now();
            $this->inventory->restock(new InventoryChange(
                OperationKey::validate("shipment-return:{$lockedReturnItem->id}:restock"),
                $lockedReturnItem->variant_id,
                InventoryAction::ReturnRestocked,
                InventoryReferenceType::ShipmentReturnItem,
                $lockedReturnItem->id,
                1,
                Cost::normalize($lockedReturnItem->cost_basis),
                $currentActor->id,
                $note,
            ));

            $lockedReturnItem->disposition = ReturnDisposition::Restock;
            $lockedReturnItem->inspected_by = $currentActor->id;
            $lockedReturnItem->inspected_at = $now;
            $lockedReturnItem->updated_at = $now;
            $lockedReturnItem->save();
            $this->recordEvent($lockedReturnItem, ShipmentReturnEvent::RESTOCKED, $currentActor, $note, $now);

            return $lockedReturnItem->fresh();
        }, 3);
    }

    public function proposeWriteOff(ShipmentReturnItem $returnItem, User $actor, string $note): ShipmentReturnEvent
    {
        $note = $this->normalizeNote($note, 20);

        return DB::transaction(function () use ($returnItem, $actor, $note): ShipmentReturnEvent {
            $currentActor = $this->lockActor($actor, RoleSlug::Staff);
            $context = $this->lockReturnContext($returnItem->id);
            $lockedReturnItem = $context['return_item'];

            $proposal = $lockedReturnItem->events()->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED)->first();
            if ($proposal !== null) {
                return $proposal;
            }
            $this->assertPendingDisposition($context);
            if ($lockedReturnItem->events()->whereIn('event_type', [
                ShipmentReturnEvent::RESTOCKED,
                ShipmentReturnEvent::WRITE_OFF_PROPOSED,
                ShipmentReturnEvent::WRITE_OFF_APPROVED,
            ])->exists()) {
                throw new DomainException(ErrorCode::StateConflict);
            }

            return $this->recordEvent($lockedReturnItem, ShipmentReturnEvent::WRITE_OFF_PROPOSED, $currentActor, $note, $this->clock->now());
        }, 3);
    }

    public function approveWriteOff(ShipmentReturnItem $returnItem, User $actor, string $note): ShipmentReturnItem
    {
        $note = $this->normalizeNote($note, 20);

        return DB::transaction(function () use ($returnItem, $actor, $note): ShipmentReturnItem {
            $currentActor = $this->lockActor($actor, RoleSlug::Admin);
            $context = $this->lockReturnContext($returnItem->id);
            $lockedReturnItem = $context['return_item'];

            if ($lockedReturnItem->disposition === ReturnDisposition::WriteOff) {
                return $lockedReturnItem;
            }
            $this->assertPendingDisposition($context);
            if (! $lockedReturnItem->events()->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED)->exists()) {
                throw new DomainException(ErrorCode::StateConflict);
            }

            $now = $this->clock->now();
            $movement = $this->inventory->writeOff(new InventoryChange(
                OperationKey::validate("shipment-return:{$lockedReturnItem->id}:writeoff"),
                $lockedReturnItem->variant_id,
                InventoryAction::ReturnWrittenOff,
                InventoryReferenceType::ShipmentReturnItem,
                $lockedReturnItem->id,
                1,
                null,
                $currentActor->id,
                $note,
            ));

            $this->ledger->postUnique(
                OperationKey::validate("inventory-loss:shipment-return:{$lockedReturnItem->id}"),
                SalesLedgerType::InventoryLoss,
                ['expense_delta' => Cost::cogs($movement->unit_cost_basis, 1)],
                [
                    'order_id' => $context['order']->id,
                    'order_item_unit_id' => $context['unit']->id,
                    'inventory_movement_id' => $movement->id,
                ],
                $now,
            );

            $lockedReturnItem->disposition = ReturnDisposition::WriteOff;
            $lockedReturnItem->inspected_by = $currentActor->id;
            $lockedReturnItem->inspected_at = $now;
            $lockedReturnItem->updated_at = $now;
            $lockedReturnItem->save();
            $this->recordEvent($lockedReturnItem, ShipmentReturnEvent::WRITE_OFF_APPROVED, $currentActor, $note, $now);

            return $lockedReturnItem->fresh();
        }, 3);
    }

    /** @return array{order: Order, shipment: Shipment, items: Collection<int, OrderItem>, units: Collection<int, OrderItemUnit>} */
    private function lockShipmentContext(int $shipmentId): array
    {
        $shipmentReference = Shipment::query()->whereKey($shipmentId)->firstOrFail();
        $order = Order::query()->whereKey($shipmentReference->order_id)->lockForUpdate()->firstOrFail();
        $shipment = Shipment::query()->whereKey($shipmentId)->lockForUpdate()->firstOrFail();
        if ($shipment->order_id !== $order->id) {
            throw new DomainException(ErrorCode::StateConflict);
        }

        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        $units = OrderItemUnit::query()->whereIn('order_item_id', $items->modelKeys())->orderBy('id')->lockForUpdate()->get();

        return ['order' => $order, 'shipment' => $shipment, 'items' => $items, 'units' => $units];
    }

    /**
     * @return array{order: Order, shipment: Shipment, items: Collection<int, OrderItem>, units: Collection<int, OrderItemUnit>, unit: OrderItemUnit, return_item: ShipmentReturnItem}
     */
    private function lockReturnContext(int $returnItemId): array
    {
        $returnReference = ShipmentReturnItem::query()->whereKey($returnItemId)->firstOrFail();
        $context = $this->lockShipmentContext($returnReference->shipment_id);
        $unit = $context['units']->firstWhere('id', $returnReference->order_item_unit_id);
        if ($unit === null) {
            throw new DomainException(ErrorCode::StateConflict);
        }
        $lockedReturnItem = ShipmentReturnItem::query()->whereKey($returnItemId)->lockForUpdate()->firstOrFail();
        if ($lockedReturnItem->shipment_id !== $context['shipment']->id || $lockedReturnItem->order_item_unit_id !== $unit->id) {
            throw new DomainException(ErrorCode::StateConflict);
        }
        $context['unit'] = $unit;
        $context['return_item'] = $lockedReturnItem;

        return $context;
    }

    /** @param array{order: Order, shipment: Shipment} $context */
    private function assertReturning(Order $order, Shipment $shipment): void
    {
        if ($order->order_status !== OrderStatus::Failed || $shipment->status !== ShipmentStatus::Returning) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }

    /** @param array{order: Order, shipment: Shipment, unit: OrderItemUnit, return_item: ShipmentReturnItem} $context */
    private function assertPendingDisposition(array $context): void
    {
        $this->assertReturnShipmentState($context['order'], $context['shipment']);
        if ($context['return_item']->disposition !== ReturnDisposition::Pending
            || $context['unit']->state !== OrderUnitState::ReturnedToStore) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }

    private function assertReturnShipmentState(Order $order, Shipment $shipment): void
    {
        if ($order->order_status !== OrderStatus::Failed
            || ! in_array($shipment->status, [ShipmentStatus::Returning, ShipmentStatus::ReturnReceived], true)) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }

    private function lockActor(User $actor, RoleSlug $requiredRole): User
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Shipment return actions require a surrounding transaction.');
        }
        $current = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        abort_unless($current !== null && $current->is_active && ! $current->trashed(), 403);

        $roles = Role::query()->whereIn('slug', [RoleSlug::Admin->value, RoleSlug::Staff->value])->orderBy('id')->lockForUpdate()->get();
        $roleIds = $roles->pluck('id')->all();
        $assignments = UserRole::query()->where('user_id', $current->id)->whereIn('role_id', $roleIds)->orderBy('role_id')->lockForUpdate()->get();
        $adminRoleId = $roles->first(fn (Role $role): bool => $role->slug === RoleSlug::Admin)?->id;
        $staffRoleId = $roles->first(fn (Role $role): bool => $role->slug === RoleSlug::Staff)?->id;
        $hasAdminRole = $adminRoleId !== null && $assignments->contains(fn (UserRole $assignment): bool => $assignment->role_id === $adminRoleId);
        $hasStaffRole = $staffRoleId !== null && $assignments->contains(fn (UserRole $assignment): bool => $assignment->role_id === $staffRoleId);

        $allowed = match ($requiredRole) {
            RoleSlug::Admin => $hasAdminRole,
            RoleSlug::Staff => $hasStaffRole && ! $hasAdminRole,
            RoleSlug::Customer => false,
        };
        abort_unless($allowed, 403);

        return $current;
    }

    private function recordEvent(ShipmentReturnItem $returnItem, string $type, User $actor, string $note, \DateTimeInterface $occurredAt): ShipmentReturnEvent
    {
        return $this->recordShipmentEvent($returnItem->shipment_id, $returnItem->order_item_unit_id, $returnItem, $type, $actor, $note, $occurredAt);
    }

    private function recordShipmentEvent(int $shipmentId, int $unitId, ?ShipmentReturnItem $returnItem, string $type, User $actor, string $note, \DateTimeInterface $occurredAt): ShipmentReturnEvent
    {
        $event = new ShipmentReturnEvent;
        $event->forceFill([
            'shipment_id' => $shipmentId,
            'shipment_return_item_id' => $returnItem?->id,
            'order_item_unit_id' => $unitId,
            'event_type' => $type,
            'actor_id' => $actor->id,
            'note' => $note,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ])->save();

        return $event->refresh();
    }

    private function normalizeNote(string $note, int $minimumLength, int $maximumLength = 500): string
    {
        $note = trim($note);
        if (! mb_check_encoding($note, 'UTF-8') || mb_strlen($note) < $minimumLength || mb_strlen($note) > $maximumLength) {
            throw new DomainException(ErrorCode::InvalidPayload, ['note' => ['Vui lòng ghi nhận tình trạng hoặc chứng cứ đầy đủ.']]);
        }

        return $note;
    }
}
