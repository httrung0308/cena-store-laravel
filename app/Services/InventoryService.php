<?php

namespace App\Services;

use App\Data\InventoryChange;
use App\Enums\AfterSalesStatus;
use App\Enums\AfterSalesType;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction as Action;
use App\Enums\InventoryReferenceType as Reference;
use App\Enums\RoleSlug;
use App\Enums\SalesLedgerType;
use App\Enums\StockImportStatus;
use App\Exceptions\DomainException;
use App\Models\AfterSalesRequest;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShipmentReturnItem;
use App\Models\StockImportItem;
use App\Models\User;
use App\Models\UserRole;
use App\Support\BusinessClock;
use App\Support\Cost;
use App\Support\OperationKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Caller owns authorization, root locks and workflow transitions; this service owns bucket arithmetic. */
final class InventoryService
{
    private const BUCKETS = ['stock', 'reserved', 'quarantine'];

    public function __construct(private readonly BusinessClock $clock, private readonly SalesLedgerService $ledger) {}

    public function reserve(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::OrderReserved, Action::ExchangeReserved]);
    }

    public function release(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::OrderReleased, Action::ExchangeReleased]);
    }

    public function issue(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::OrderDispatched, Action::ExchangeCompletedOut]);
    }

    public function receive(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::ImportPosted]);
    }

    public function receiveQuarantine(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::ShipmentReturnReceived, Action::ExchangeOldReceived, Action::DefectReceived]);
    }

    public function restock(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::ReturnRestocked, Action::ExchangeCompletedIn]);
    }

    public function writeOff(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::ReturnWrittenOff, Action::QuarantineWriteoff]);
    }

    public function adjust(InventoryChange $change): InventoryMovement
    {
        return $this->one($change, [Action::ManualAdjustIn, Action::ManualAdjustOut]);
    }

    /**
     * Lock all variants ascending before applying ordered intents. Caller first locks roots/units
     * in canonical order and retries the entire SQL-only outer transaction on deadlocks.
     *
     * @param  list<InventoryChange>  $changes
     * @return list<InventoryMovement>
     */
    public function applyBatch(array $changes): array
    {
        $this->requireTransaction();
        if (! array_is_list($changes) || $changes === []) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $keys = [];
        $intents = [];
        foreach ($changes as $change) {
            if (! $change instanceof InventoryChange) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
            $intent = $this->normalize($change);
            $key = strtolower($intent['operation_key']);
            if (isset($keys[$key])) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
            $keys[$key] = true;
            $intents[] = $intent;
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($intents): array {
                    $variants = $this->lockVariants(array_column($intents, 'variant_id'));
                    $results = [];
                    foreach ($intents as $intent) {
                        $existing = InventoryMovement::where('operation_key', $intent['operation_key'])->lockForUpdate()->first();
                        if ($existing !== null) {
                            $results[] = $this->replay($existing, $intent);

                            continue;
                        }
                        $variant = $variants->get($intent['variant_id']);
                        $history = InventoryMovement::where('variant_id', $variant->id)->orderBy('id')->lockForUpdate()->get();
                        $this->verifyHistory($variant, $history);
                        $results[] = $this->append($variant, $history, $intent);
                    }

                    return $results;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 1) {
                    throw $exception;
                }
                // The savepoint undoes every bucket/companion entry before reading the winning key.
            }
        }
        throw new LogicException('Unreachable inventory retry.');
    }

    /** Read-only reconciliation; discrepancies are never repaired. @param list<int> $variantIds */
    public function assertConsistent(array $variantIds): void
    {
        $this->requireTransaction();
        foreach ($this->lockVariants($variantIds) as $variant) {
            $this->verifyHistory($variant, InventoryMovement::where('variant_id', $variant->id)->orderBy('id')->lockForUpdate()->get());
        }
    }

    /** @param list<Action> $allowed */
    private function one(InventoryChange $change, array $allowed): InventoryMovement
    {
        if (! in_array($change->action, $allowed, true)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }

        return $this->applyBatch([$change])[0];
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Inventory requires the surrounding business transaction.');
        }
    }

    /** @param list<int> $ids @return Collection<int, ProductVariant> */
    private function lockVariants(array $ids): Collection
    {
        foreach ($ids as $id) {
            if (! is_int($id) || $id < 1) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        $variants = new Collection;
        foreach ($ids as $id) {
            $variant = ProductVariant::withTrashed()->whereKey($id)->lockForUpdate()->first();
            if ($variant === null) {
                throw new DomainException(ErrorCode::NotFound);
            }
            $variants->put($id, $variant);
        }

        return $variants;
    }

    /** @return array<string, mixed> */
    private function normalize(InventoryChange $change): array
    {
        $quantity = $change->quantity;
        if ((! is_int($quantity) && ! is_string($quantity)) || ! preg_match('/^[0-9]{1,10}$/D', (string) $quantity)
            || bccomp((string) $quantity, '1', 0) < 0 || bccomp((string) $quantity, '2147483647', 0) > 0) {
            throw new DomainException(ErrorCode::InvalidQuantity);
        }
        if ($change->variantId < 1 || ($change->referenceId !== null && $change->referenceId < 1)
            || ($change->actorId !== null && $change->actorId < 1)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $reason = $change->reason;
        if ($reason !== null && (! mb_check_encoding($reason, 'UTF-8') || mb_strlen($reason) > 500 || trim($reason) === '')) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $reason = $reason === null ? null : trim($reason);
        $manual = in_array($change->action, [Action::ManualAdjustIn, Action::ManualAdjustOut, Action::QuarantineWriteoff], true);
        if ($change->referenceId === null || ($manual && ($change->actorId === null || mb_strlen($reason ?? '') < 20))
            || ($change->action === Action::ReturnWrittenOff && $reason === null)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $cost = $this->needsInputCost($change->action) ? Cost::normalize($change->unitCostBasis) : null;
        if ($cost === null && $change->unitCostBasis !== null) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }

        return ['operation_key' => OperationKey::validate($change->operationKey), 'variant_id' => $change->variantId,
            'action' => $change->action, 'reference_type' => $change->referenceType, 'reference_id' => $change->referenceId,
            'quantity' => (int) $quantity, 'unit_cost_basis' => $cost, 'actor_id' => $change->actorId, 'reason' => $reason];
    }

    private function needsInputCost(Action $action): bool
    {
        return in_array($action, [Action::ImportPosted, Action::ManualAdjustIn, Action::ShipmentReturnReceived,
            Action::ExchangeOldReceived, Action::DefectReceived, Action::ReturnRestocked, Action::ExchangeCompletedIn], true);
    }

    /** @return array{stock: int, reserved: int, quarantine: int} */
    private function deltas(Action $action, int $quantity): array
    {
        [$stock, $reserved, $quarantine] = match ($action) {
            Action::ImportPosted, Action::ManualAdjustIn => [$quantity, 0, 0],
            Action::OrderReserved, Action::ExchangeReserved => [0, $quantity, 0],
            Action::OrderReleased, Action::ExchangeReleased => [0, -$quantity, 0],
            Action::OrderDispatched, Action::ExchangeCompletedOut => [-$quantity, -$quantity, 0],
            Action::ShipmentReturnReceived, Action::ExchangeOldReceived, Action::DefectReceived => [0, 0, $quantity],
            Action::ReturnRestocked, Action::ExchangeCompletedIn => [$quantity, 0, -$quantity],
            Action::ReturnWrittenOff, Action::QuarantineWriteoff => [0, 0, -$quantity],
            Action::ManualAdjustOut => [-$quantity, 0, 0],
        };

        return compact('stock', 'reserved', 'quarantine');
    }

    /** @param array<string, mixed> $intent */
    private function replay(InventoryMovement $entry, array $intent): InventoryMovement
    {
        foreach (['operation_key', 'variant_id', 'action', 'reference_type', 'reference_id', 'actor_id', 'reason'] as $field) {
            if ($entry->getAttribute($field) !== $intent[$field]) {
                throw new DomainException(ErrorCode::IdempotencyMismatch);
            }
        }
        foreach ($this->deltas($intent['action'], $intent['quantity']) as $bucket => $delta) {
            if ($entry->getAttribute($bucket.'_delta') !== $delta) {
                throw new DomainException(ErrorCode::IdempotencyMismatch);
            }
        }
        if ($this->needsInputCost($intent['action']) && $entry->unit_cost_basis !== $intent['unit_cost_basis']) {
            throw new DomainException(ErrorCode::IdempotencyMismatch);
        }

        return $entry;
    }

    /** @param Collection<int, InventoryMovement> $history */
    private function verifyHistory(ProductVariant $variant, Collection $history): void
    {
        $balances = ['stock' => 0, 'reserved' => 0, 'quarantine' => 0];
        $cost = '0.0000';
        $ownership = [];
        foreach ($history as $entry) {
            $quantity = max(abs($entry->stock_delta), abs($entry->reserved_delta), abs($entry->quarantine_delta));
            if ($quantity < 1 || $quantity > 2147483647 || $entry->average_cost_before !== $cost) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            $deltas = $this->deltas($entry->action, $quantity);
            $owner = $entry->reference_type->value.':'.$entry->reference_id;
            $ownership[$owner] ??= ['reserved' => 0, 'quarantine' => 0, 'basis' => null];
            $owned = &$ownership[$owner];
            if (($this->needsInputCost($entry->action) || $deltas['stock'] < 0 || $deltas['quarantine'] < 0) && $entry->unit_cost_basis === null) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            if (($deltas['stock'] < 0 && $entry->unit_cost_basis !== $cost)
                || ($deltas['quarantine'] < 0 && $entry->unit_cost_basis !== $owned['basis'])
                || ($deltas['stock'] === 0 && $deltas['quarantine'] === 0 && $entry->unit_cost_basis !== null)) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            foreach (['reserved', 'quarantine'] as $bucket) {
                $owned[$bucket] += $deltas[$bucket];
                if ($owned[$bucket] < 0) {
                    throw new DomainException(ErrorCode::StateConflict);
                }
            }
            if ($deltas['quarantine'] > 0) {
                $owned['basis'] = $entry->unit_cost_basis;
            }
            unset($owned);
            foreach (self::BUCKETS as $bucket) {
                if ($entry->getAttribute($bucket.'_before') !== $balances[$bucket] || $entry->getAttribute($bucket.'_delta') !== $deltas[$bucket]) {
                    throw new DomainException(ErrorCode::StateConflict);
                }
                $balances[$bucket] += $deltas[$bucket];
                if ($entry->getAttribute($bucket.'_after') !== $balances[$bucket]) {
                    throw new DomainException(ErrorCode::StateConflict);
                }
            }
            if ($deltas['stock'] > 0) {
                $cost = Cost::weightedAverage($entry->stock_before, $cost, $quantity, $entry->unit_cost_basis);
            }
            if ($entry->average_cost_after !== $cost) {
                throw new DomainException(ErrorCode::StateConflict);
            }
        }
        foreach (self::BUCKETS as $bucket) {
            if ($variant->getAttribute($bucket.'_qty') !== $balances[$bucket]) {
                throw new DomainException(ErrorCode::StateConflict);
            }
        }
        if ($variant->average_cost !== $cost) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }

    /** @param Collection<int, InventoryMovement> $history @param array<string, mixed> $intent */
    private function append(ProductVariant $variant, Collection $history, array $intent): InventoryMovement
    {
        $action = $intent['action'];
        $actor = $intent['actor_id'] === null ? null : User::whereKey($intent['actor_id'])->lockForUpdate()->first();
        if ($intent['actor_id'] !== null && ($actor === null || ! $actor->is_active)) {
            throw new DomainException(ErrorCode::Forbidden);
        }
        if (in_array($action, [Action::ManualAdjustIn, Action::ManualAdjustOut, Action::QuarantineWriteoff], true)
            && ($actor === null || UserRole::where('user_id', $actor->id)->whereHas('role', fn ($query) => $query->where('slug', RoleSlug::Admin))->lockForUpdate()->first() === null)) {
            throw new DomainException(ErrorCode::Forbidden);
        }
        if (in_array($action, [Action::OrderReserved, Action::ExchangeReserved], true) && ($variant->trashed() || ! $variant->is_active)) {
            throw new DomainException(ErrorCode::ProductUnavailable);
        }
        $owned = $history->filter(fn (InventoryMovement $entry): bool => $entry->reference_type === $intent['reference_type'] && $entry->reference_id === $intent['reference_id']);
        $this->validateSource($variant, $owned, $intent);
        $deltas = $this->deltas($action, $intent['quantity']);
        if (($deltas['reserved'] < 0 && $owned->sum('reserved_delta') < -$deltas['reserved'])
            || ($deltas['quarantine'] < 0 && $owned->sum('quarantine_delta') < -$deltas['quarantine'])) {
            throw new DomainException(ErrorCode::StateConflict);
        }
        $basis = $intent['unit_cost_basis'];
        if ($deltas['stock'] < 0) {
            $basis = $variant->average_cost;
        } elseif ($deltas['quarantine'] < 0) {
            $receipt = $owned->first(fn (InventoryMovement $entry): bool => $entry->quarantine_delta > 0);
            if ($receipt === null || ($basis !== null && $basis !== $receipt->unit_cost_basis)) {
                throw new DomainException(ErrorCode::InvalidCost);
            }
            $basis = $receipt->unit_cost_basis;
        }
        $movement = new InventoryMovement;
        foreach (['operation_key', 'variant_id', 'action', 'reference_type', 'reference_id', 'actor_id', 'reason'] as $field) {
            $movement->setAttribute($field, $intent[$field]);
        }
        $movement->actor_name_snapshot = $actor?->full_name;
        $movement->unit_cost_basis = $basis;
        $movement->average_cost_before = $variant->average_cost;
        foreach ($deltas as $bucket => $delta) {
            $before = $variant->getAttribute($bucket.'_qty');
            $after = $before + $delta;
            if ($after < 0 || $after > 4294967295) {
                throw new DomainException(ErrorCode::StockUnavailable);
            }
            $movement->setAttribute($bucket.'_before', $before);
            $movement->setAttribute($bucket.'_delta', $delta);
            $movement->setAttribute($bucket.'_after', $after);
            $variant->setAttribute($bucket.'_qty', $after);
        }
        if ($variant->reserved_qty > $variant->stock_qty) {
            throw new DomainException(ErrorCode::StockUnavailable);
        }
        if ($deltas['stock'] > 0) {
            $variant->average_cost = Cost::weightedAverage($movement->stock_before, $movement->average_cost_before, $intent['quantity'], $basis);
        }
        $movement->average_cost_after = $variant->average_cost;
        $movement->created_at = $this->clock->now();
        $movement->save();
        $variant->save();
        if ($action === Action::ManualAdjustOut) {
            $this->ledger->postUnique('inventory-loss:'.hash('sha256', $intent['operation_key']), SalesLedgerType::InventoryLoss,
                ['expense_delta' => Cost::cogs($basis, $intent['quantity'])], ['inventory_movement_id' => $movement->id], $movement->created_at);
        }

        return $movement->refresh();
    }

    /** @param Collection<int, InventoryMovement> $owned @param array<string, mixed> $intent */
    private function validateSource(ProductVariant $variant, Collection $owned, array $intent): void
    {
        $action = $intent['action'];
        $reference = $intent['reference_type'];
        $quantity = $intent['quantity'];
        $allowed = match ($action) {
            Action::ImportPosted => [Reference::StockImport],
            Action::OrderReserved, Action::OrderReleased, Action::OrderDispatched => [Reference::Order],
            Action::ShipmentReturnReceived => [Reference::ShipmentReturnItem],
            Action::ExchangeReserved, Action::ExchangeReleased, Action::ExchangeOldReceived, Action::ExchangeCompletedIn,
            Action::ExchangeCompletedOut, Action::DefectReceived => [Reference::AfterSalesRequest],
            Action::ReturnRestocked, Action::ReturnWrittenOff, Action::QuarantineWriteoff => [Reference::ShipmentReturnItem, Reference::AfterSalesRequest],
            Action::ManualAdjustIn, Action::ManualAdjustOut => [Reference::ManualAdjustment],
        };
        if (! in_array($reference, $allowed, true)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        if ($reference === Reference::ManualAdjustment) {
            return;
        }
        if ($reference === Reference::StockImport) {
            $item = StockImportItem::where('stock_import_id', $intent['reference_id'])->where('variant_id', $variant->id)->lockForUpdate()->first();
            if ($item === null || $item->stockImport()->lockForUpdate()->first()->status !== StockImportStatus::Draft
                || $owned->sum('stock_delta') + $quantity > $item->quantity) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            if (Cost::normalize($item->unit_cost_amount) !== $intent['unit_cost_basis']) {
                throw new DomainException(ErrorCode::InvalidCost);
            }

            return;
        }
        if ($reference === Reference::Order) {
            $item = OrderItem::where('order_id', $intent['reference_id'])->where('variant_id', $variant->id)->lockForUpdate()->first();
            if ($item === null) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            $issued = -$owned->sum('stock_delta');
            if ($action === Action::OrderReserved && $owned->sum('reserved_delta') + $issued + $quantity > $item->quantity) {
                throw new DomainException(ErrorCode::StateConflict);
            }

            return;
        }
        $source = $reference === Reference::ShipmentReturnItem ? ShipmentReturnItem::whereKey($intent['reference_id'])->lockForUpdate()->first() : AfterSalesRequest::whereKey($intent['reference_id'])->lockForUpdate()->first();
        if ($source === null || $quantity !== 1) {
            throw new DomainException(ErrorCode::StateConflict);
        }
        $targetAction = in_array($action, [Action::ExchangeReserved, Action::ExchangeReleased, Action::ExchangeCompletedOut], true);
        if ($source instanceof AfterSalesRequest) {
            $exchangeAction = in_array($action, [Action::ExchangeReserved, Action::ExchangeReleased, Action::ExchangeCompletedOut, Action::ExchangeOldReceived, Action::ExchangeCompletedIn], true);
            if ($source->type === AfterSalesType::WarrantyRepair
                || ($exchangeAction && $source->type !== AfterSalesType::SizeExchange)
                || (in_array($action, [Action::DefectReceived, Action::ReturnRestocked], true) && $source->type !== AfterSalesType::DefectReturn)) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            $expectedVariant = $targetAction ? $source->target_variant_id : $source->source_variant_id;
            if ($targetAction) {
                $original = ProductVariant::withTrashed()->find($source->source_variant_id);
                if ($original === null || $original->id === $variant->id || $original->product_color_id !== $variant->product_color_id) {
                    throw new DomainException(ErrorCode::StateConflict);
                }
            }
            if ($action === Action::QuarantineWriteoff && ! in_array($source->status, [AfterSalesStatus::Completed, AfterSalesStatus::Rejected, AfterSalesStatus::Cancelled], true)) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            $sourceCost = $source->returned_cogs_amount;
        } else {
            $expectedVariant = $source->variant_id;
            if ($source->unit === null || $source->unit->item->order_id !== $source->shipment->order_id) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            $sourceCost = $source->cost_basis;
        }
        if ($expectedVariant !== $variant->id) {
            throw new DomainException(ErrorCode::StateConflict);
        }
        $deltas = $this->deltas($action, $quantity);
        if ($deltas['quarantine'] > 0) {
            if ($owned->where('quarantine_delta', '>', 0)->sum('quarantine_delta') + $quantity > 1) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            if ($sourceCost === null || Cost::normalize($sourceCost) !== $intent['unit_cost_basis']) {
                throw new DomainException(ErrorCode::InvalidCost);
            }
        }
        if ($targetAction && $deltas['reserved'] > 0 && $owned->sum('reserved_delta') - $owned->sum('stock_delta') + $quantity > 1) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }
}
