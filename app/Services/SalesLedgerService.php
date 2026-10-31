<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\SalesLedgerType;
use App\Exceptions\DomainException;
use App\Models\AfterSalesRequest;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItemUnit;
use App\Models\Refund;
use App\Models\SalesLedgerEntry;
use App\Support\BusinessClock;
use App\Support\CanonicalPayload;
use App\Support\OperationKey;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class SalesLedgerService
{
    private const DELTAS = ['merchandise_revenue_delta', 'shipping_revenue_delta', 'cogs_delta', 'expense_delta'];

    private const SOURCES = ['order_id', 'order_item_unit_id', 'after_sales_request_id', 'refund_id', 'inventory_movement_id'];

    public function __construct(private readonly BusinessClock $clock) {}

    /**
     * @param  array<string, int|string>  $deltas  Signed integer VND, never floats.
     * @param  array<string, int|null>  $sources  Server-authorized source identifiers.
     */
    public function postUnique(string $operationKey, SalesLedgerType $type, array $deltas, array $sources, DateTimeInterface|string $occurredAt): SalesLedgerEntry
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Ledger posting requires the surrounding business transaction.');
        }
        if (array_diff(array_keys($deltas), self::DELTAS) || array_diff(array_keys($sources), self::SOURCES)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $data = ['operation_key' => OperationKey::validate($operationKey), 'type' => $type->value, 'occurred_at' => $this->clock->instant($occurredAt)->format('Y-m-d H:i:s.u')];
        foreach (self::DELTAS as $field) {
            $data[$field] = $this->signedInteger(array_key_exists($field, $deltas) ? $deltas[$field] : 0);
        }
        foreach (self::SOURCES as $field) {
            $value = $sources[$field] ?? null;
            if ($value !== null && (! is_int($value) || $value < 1)) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
            $data[$field] = $value;
        }
        try {
            return DB::transaction(function () use ($operationKey, $type, $data): SalesLedgerEntry {
                if ($existing = SalesLedgerEntry::where('operation_key', $operationKey)->first()) {
                    return $this->replay($existing, $data);
                }
                $this->validateShape($type, $data);
                $this->validateSources($data);
                $entry = new SalesLedgerEntry;
                foreach ($data as $field => $value) {
                    $entry->setAttribute($field, $value);
                }
                $entry->created_at = $this->clock->now();
                $entry->save();

                return $entry->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = SalesLedgerEntry::where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $data);
        }
    }

    private function signedInteger(mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^-?[0-9]{1,20}$/D', (string) $value)) {
            throw new DomainException(ErrorCode::InvalidAmount);
        }
        if (bccomp((string) $value, (string) PHP_INT_MIN, 0) < 0 || bccomp((string) $value, (string) PHP_INT_MAX, 0) > 0) {
            throw new DomainException(ErrorCode::AmountOverflow);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $data */
    private function replay(SalesLedgerEntry $entry, array $data): SalesLedgerEntry
    {
        $stored = [];
        foreach ($data as $field => $value) {
            $stored[$field] = match ($field) {
                'type' => $entry->type->value,
                'occurred_at' => $entry->occurred_at->utc()->format('Y-m-d H:i:s.u'),
                default => $entry->getAttribute($field),
            };
        }
        if (CanonicalPayload::fingerprint($stored) !== CanonicalPayload::fingerprint($data)) {
            throw new DomainException(ErrorCode::IdempotencyMismatch);
        }

        return $entry;
    }

    /** @param array<string, mixed> $data */
    private function validateShape(SalesLedgerType $type, array $data): void
    {
        $merchandise = $data['merchandise_revenue_delta'];
        $shipping = $data['shipping_revenue_delta'];
        $cogs = $data['cogs_delta'];
        $expense = $data['expense_delta'];
        $valid = match ($type) {
            SalesLedgerType::SaleRecognized => $data['order_item_unit_id'] !== null && $merchandise >= 0 && $cogs >= 0 && $shipping === 0 && $expense === 0,
            SalesLedgerType::SaleReversed => $data['order_item_unit_id'] !== null && $merchandise <= 0 && $cogs <= 0 && $shipping === 0 && $expense === 0,
            SalesLedgerType::ShippingRecognized => $data['order_item_unit_id'] === null && $shipping >= 0 && $merchandise === 0 && $cogs === 0 && $expense === 0,
            SalesLedgerType::ShippingReversed => $data['order_item_unit_id'] === null && $shipping <= 0 && $merchandise === 0 && $cogs === 0 && $expense === 0,
            SalesLedgerType::ExchangeCogsAdjustment => $data['order_item_unit_id'] !== null && $data['after_sales_request_id'] !== null && $merchandise === 0 && $shipping === 0 && $expense === 0,
            SalesLedgerType::RmaExpense => $data['after_sales_request_id'] !== null && $merchandise === 0 && $shipping === 0 && $cogs === 0,
            SalesLedgerType::InventoryLoss => $data['inventory_movement_id'] !== null && $merchandise === 0 && $shipping === 0 && $cogs === 0,
        };
        if (! $valid || ($type !== SalesLedgerType::InventoryLoss && $data['order_id'] === null)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateSources(array $data): void
    {
        if ($data['order_id'] !== null && ! Order::whereKey($data['order_id'])->exists()) {
            throw new DomainException(ErrorCode::NotFound);
        }
        if ($data['order_item_unit_id'] !== null) {
            $unit = OrderItemUnit::with('item')->find($data['order_item_unit_id']);
            if ($unit === null || $unit->item->order_id !== $data['order_id']) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
        }
        if ($data['after_sales_request_id'] !== null) {
            $rma = AfterSalesRequest::with('unit.item')->find($data['after_sales_request_id']);
            if ($rma === null || $rma->unit->item->order_id !== $data['order_id']
                || ($data['order_item_unit_id'] !== null && $rma->order_item_unit_id !== $data['order_item_unit_id'])) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
        }
        if ($data['refund_id'] !== null) {
            $refund = Refund::find($data['refund_id']);
            if ($refund === null || $refund->order_id !== $data['order_id']
                || ($refund->order_item_unit_id !== null && $refund->order_item_unit_id !== $data['order_item_unit_id'])
                || ($refund->after_sales_request_id !== null && $refund->after_sales_request_id !== $data['after_sales_request_id'])) {
                throw new DomainException(ErrorCode::InvalidPayload);
            }
        }
        if ($data['inventory_movement_id'] !== null && ! InventoryMovement::whereKey($data['inventory_movement_id'])->exists()) {
            throw new DomainException(ErrorCode::NotFound);
        }
    }
}
