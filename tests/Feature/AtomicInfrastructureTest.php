<?php

namespace Tests\Feature;

use App\Enums\ErrorCode;
use App\Enums\OutboxAggregateType;
use App\Enums\SalesLedgerType;
use App\Exceptions\DomainException;
use App\Models\AfterSalesRequest;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItemUnit;
use App\Models\Refund;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use App\Services\OutboxService;
use App\Services\SalesLedgerService;
use App\Support\BusinessClock;
use Closure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AtomicInfrastructureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ledger_replay_normalizes_key_order_integer_strings_and_utc_without_mutating_history(): void
    {
        $unit = OrderItemUnit::factory()->create();
        $sources = ['order_id' => $unit->item->order_id, 'order_item_unit_id' => $unit->id];
        $service = app(SalesLedgerService::class);
        $first = $service->postUnique('sale:one', SalesLedgerType::SaleRecognized, ['merchandise_revenue_delta' => '00100', 'cogs_delta' => 40], $sources, '2026-10-31T10:00:00.123456+07:00');
        $before = $first->getAttributes();
        app(BusinessClock::class)->freeze('2030-01-01T00:00:00Z');
        $again = $service->postUnique('sale:one', SalesLedgerType::SaleRecognized, ['cogs_delta' => '40', 'merchandise_revenue_delta' => 100], array_reverse($sources, true), '2026-10-31T03:00:00.123456Z');
        $this->assertSame($first->id, $again->id);
        $this->assertSame($before, $again->getAttributes());
        $this->assertDatabaseCount('sales_ledger_entries', 1);
    }

    #[DataProvider('ledgerMismatches')]
    public function test_same_operation_key_with_different_intent_is_a_conflict(string $change): void
    {
        $unit = OrderItemUnit::factory()->create();
        $order = Order::factory()->create();
        $sources = ['order_id' => $unit->item->order_id, 'order_item_unit_id' => $unit->id];
        $service = app(SalesLedgerService::class);
        $type = SalesLedgerType::SaleRecognized;
        $deltas = ['merchandise_revenue_delta' => 100, 'cogs_delta' => 40];
        $at = '2026-10-31T03:00:00Z';
        $service->postUnique('sale:key', $type, $deltas, $sources, $at);
        $key = 'sale:key';
        match ($change) {
            'amount' => $deltas['cogs_delta'] = 41,
            'type' => $type = SalesLedgerType::SaleReversed,
            'source' => $sources['order_id'] = $order->id,
            'time' => $at = '2026-10-31T03:00:00.000001Z',
            'case' => $key = 'SALE:key',
        };
        $this->domain(fn () => $service->postUnique($key, $type, $deltas, $sources, $at), ErrorCode::IdempotencyMismatch);
        $this->assertDatabaseCount('sales_ledger_entries', 1);
        $this->assertSame(40, SalesLedgerEntry::sole()->cogs_delta);
    }

    public static function ledgerMismatches(): array
    {
        return [['amount'], ['type'], ['source'], ['time'], ['case']];
    }

    public function test_reversals_append_new_signed_entries_and_shipping_is_kept_separate(): void
    {
        $unit = OrderItemUnit::factory()->create();
        $sources = ['order_id' => $unit->item->order_id, 'order_item_unit_id' => $unit->id];
        $s = app(SalesLedgerService::class);
        $sale = $s->postUnique('sale:unit', SalesLedgerType::SaleRecognized, ['merchandise_revenue_delta' => 100, 'cogs_delta' => 40], $sources, '2026-10-31T03:00:00Z');
        $before = $sale->getAttributes();
        $s->postUnique('reverse:unit', SalesLedgerType::SaleReversed, ['merchandise_revenue_delta' => -100, 'cogs_delta' => -40], $sources, '2026-11-01T03:00:00Z');
        $s->postUnique('ship:order', SalesLedgerType::ShippingRecognized, ['shipping_revenue_delta' => 30000], ['order_id' => $sources['order_id']], '2026-10-31T03:00:00Z');
        $s->postUnique('ship:reverse', SalesLedgerType::ShippingReversed, ['shipping_revenue_delta' => -30000], ['order_id' => $sources['order_id']], '2026-11-01T03:00:00Z');
        $this->assertSame($before, $sale->fresh()->getAttributes());
        $this->assertDatabaseCount('sales_ledger_entries', 4);
        $this->assertSame('0', (string) SalesLedgerEntry::sum('merchandise_revenue_delta'));
        $this->assertSame('0', (string) SalesLedgerEntry::sum('shipping_revenue_delta'));
        $this->assertSame('0', (string) SalesLedgerEntry::sum('cogs_delta'));
    }

    public function test_signed_bigint_limits_and_zero_value_events_are_recorded_exactly(): void
    {
        $movement = InventoryMovement::factory()->create();
        $s = app(SalesLedgerService::class);
        foreach (['min' => (string) PHP_INT_MIN, 'max' => (string) PHP_INT_MAX, 'zero' => '0'] as $key => $amount) {
            $entry = $s->postUnique('loss:'.$key, SalesLedgerType::InventoryLoss, ['expense_delta' => $amount], ['inventory_movement_id' => $movement->id], '2026-10-31T03:00:00Z');
            $this->assertSame((int) $amount, $entry->expense_delta);
        }
        $this->assertDatabaseCount('sales_ledger_entries', 3);
    }

    #[DataProvider('invalidDeltas')]
    public function test_invalid_or_overflow_delta_is_not_coerced(mixed $amount, ErrorCode $code): void
    {
        $order = Order::factory()->create();
        $this->domain(fn () => app(SalesLedgerService::class)->postUnique('bad:delta', SalesLedgerType::ShippingRecognized, ['shipping_revenue_delta' => $amount], ['order_id' => $order->id], '2026-10-31T03:00:00Z'), $code);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public static function invalidDeltas(): array
    {
        return [[null, ErrorCode::InvalidAmount], [1.5, ErrorCode::InvalidAmount], [true, ErrorCode::InvalidAmount], ['1e3', ErrorCode::InvalidAmount], ['+1', ErrorCode::InvalidAmount], [' 1', ErrorCode::InvalidAmount], ['9223372036854775808', ErrorCode::AmountOverflow], ['-9223372036854775809', ErrorCode::AmountOverflow]];
    }

    public function test_source_chains_and_posting_shape_are_checked_without_accepting_unknown_fields(): void
    {
        $unit = OrderItemUnit::factory()->create();
        $other = Order::factory()->create();
        $s = app(SalesLedgerService::class);
        $at = '2026-10-31T03:00:00Z';
        $this->domain(fn () => $s->postUnique('bad:owner', SalesLedgerType::SaleRecognized, [], ['order_id' => $other->id, 'order_item_unit_id' => $unit->id], $at), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->postUnique('bad:type', SalesLedgerType::ShippingRecognized, ['cogs_delta' => 1], ['order_id' => $other->id], $at), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->postUnique('bad:field', SalesLedgerType::ShippingRecognized, ['paid_amount' => 100], ['order_id' => $other->id], $at), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->postUnique('bad:id', SalesLedgerType::ShippingRecognized, [], ['order_id' => (string) $other->id], $at), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->postUnique('bad:missing', SalesLedgerType::ShippingRecognized, [], [], $at), ErrorCode::InvalidPayload);
        $rma = AfterSalesRequest::factory()->create();
        $this->domain(fn () => $s->postUnique('bad:rma', SalesLedgerType::RmaExpense, ['expense_delta' => 10], ['order_id' => $other->id, 'after_sales_request_id' => $rma->id], $at), ErrorCode::InvalidPayload);
        $refund = Refund::factory()->create();
        $this->domain(fn () => $s->postUnique('bad:refund', SalesLedgerType::ShippingReversed, ['shipping_revenue_delta' => -10], ['order_id' => $other->id, 'refund_id' => $refund->id], $at), ErrorCode::InvalidPayload);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public function test_exchange_and_rma_expenses_accept_matching_historical_source_chains(): void
    {
        $rma = AfterSalesRequest::factory()->create()->load('unit.item');
        $sources = ['order_id' => $rma->unit->item->order_id, 'order_item_unit_id' => $rma->order_item_unit_id, 'after_sales_request_id' => $rma->id];
        $s = app(SalesLedgerService::class);
        $adjustment = $s->postUnique('exchange:cost', SalesLedgerType::ExchangeCogsAdjustment, ['cogs_delta' => -15], $sources, '2026-10-31T03:00:00Z');
        $expense = $s->postUnique('repair:expense', SalesLedgerType::RmaExpense, ['expense_delta' => 50], $sources, '2026-10-31T03:00:00Z');
        $this->assertSame(-15, $adjustment->cogs_delta);
        $this->assertSame(50, $expense->expense_delta);
    }

    public function test_business_update_ledger_and_outbox_roll_back_together(): void
    {
        $order = Order::factory()->create();
        $before = $order->customer_note;
        try {
            DB::transaction(function () use ($order): void {
                $order->customer_note = 'Synthetic transaction mutation';
                $order->save();
                app(SalesLedgerService::class)->postUnique('atomic:ship', SalesLedgerType::ShippingRecognized, ['shipping_revenue_delta' => 30000], ['order_id' => $order->id], '2026-10-31T03:00:00Z');
                app(OutboxService::class)->enqueue('atomic:mail', 'ORDER_DELIVERED', OutboxAggregateType::Order, $order->id, ['order_id' => $order->id]);
                throw new RuntimeException('Intentional test rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Intentional test rollback', $exception->getMessage());
        }
        $this->assertSame($before, $order->fresh()->customer_note);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_outbox_replay_is_canonical_and_never_resets_delivery_state_or_schedule(): void
    {
        $user = User::factory()->create();
        $s = app(OutboxService::class);
        $event = $s->enqueue('event:one', 'USER_UPDATED', OutboxAggregateType::User, $user->id, ['b' => ['z' => 1, 'a' => 'x'], 'a' => null]);
        DB::table('outbox_events')->where('id', $event->id)->update(['status' => 'SENT', 'sent_at' => now(), 'attempts' => 3]);
        $before = $event->fresh()->getAttributes();
        app(BusinessClock::class)->freeze('2030-01-01T00:00:00Z');
        $again = $s->enqueue('event:one', 'USER_UPDATED', OutboxAggregateType::User, $user->id, ['a' => null, 'b' => ['a' => 'x', 'z' => 1]], '2030-02-01T00:00:00Z');
        $this->assertSame($event->id, $again->id);
        $this->assertSame($before, $again->getAttributes());
        $this->domain(fn () => $s->enqueue('event:one', 'USER_UPDATED', OutboxAggregateType::User, $user->id, ['a' => 1]), ErrorCode::IdempotencyMismatch);
        $this->domain(fn () => $s->enqueue('EVENT:one', 'USER_UPDATED', OutboxAggregateType::User, $user->id, ['a' => null, 'b' => ['a' => 'x', 'z' => 1]]), ErrorCode::IdempotencyMismatch);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    #[DataProvider('unsafePayloads')]
    public function test_outbox_rejects_unsafe_or_ambiguous_payloads(array $payload): void
    {
        $user = User::factory()->create();
        $this->domain(fn () => app(OutboxService::class)->enqueue('unsafe:key', 'USER_UPDATED', OutboxAggregateType::User, $user->id, $payload), ErrorCode::InvalidPayload);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public static function unsafePayloads(): array
    {
        return [[['amount' => 1.5]], [['nested' => ['OTP_Code' => '000001']]], [['PASSWORD' => 'private']], [['bank-account' => 'private']], [['x' => new \stdClass]], [['text' => "\xB1\x31"]], [['text' => str_repeat('x', 65537)]]];
    }

    public function test_outbox_checks_event_identity_aggregate_and_key(): void
    {
        $user = User::factory()->create();
        $s = app(OutboxService::class);
        $this->domain(fn () => $s->enqueue('bad:key', 'lowercase', OutboxAggregateType::User, $user->id, []), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->enqueue('contains space', 'USER_UPDATED', OutboxAggregateType::User, $user->id, []), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->enqueue('key', 'USER_UPDATED', OutboxAggregateType::User, 0, []), ErrorCode::InvalidPayload);
        $this->domain(fn () => $s->enqueue('key', 'USER_UPDATED', OutboxAggregateType::User, PHP_INT_MAX, []), ErrorCode::NotFound);
    }

    private function domain(Closure $callback, ErrorCode $code): void
    {
        try {
            $callback();
            $this->fail('Invalid operation was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }
}
