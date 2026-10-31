<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\AfterSalesStatus;
use App\Enums\AfterSalesType;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction as Action;
use App\Enums\InventoryReferenceType as Reference;
use App\Enums\OutboxAggregateType;
use App\Enums\StockImportStatus;
use App\Exceptions\DomainException;
use App\Models\AfterSalesRequest;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesLedgerEntry;
use App\Models\ShipmentReturnItem;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OutboxService;
use Closure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private InventoryService $inventory;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        $this->admin = User::factory()->create();
        $role = Role::where('slug', 'admin')->first() ?? Role::factory()->administrator()->create();
        DB::table('user_roles')->insert(['user_id' => $this->admin->id, 'role_id' => $role->id, 'assigned_at' => now()]);
    }

    public function test_receive_reserve_release_and_issue_preserve_before_after_and_historical_cost(): void
    {
        $variant = ProductVariant::factory()->create();
        $receipt = $this->receive($variant, 5, 100);
        $item = $this->item($variant, 3);
        $reserved = $this->inventory->reserve($this->orderChange('reserve', $item, Action::OrderReserved, 3));
        $released = $this->inventory->release($this->orderChange('release', $item, Action::OrderReleased, 1));
        $this->receive($variant, 5, 300);
        $issued = $this->inventory->issue($this->orderChange('dispatch', $item, Action::OrderDispatched, 2));
        $this->assertSame([5, 0, 0, 0], [$receipt->stock_after, $receipt->reserved_after, $receipt->quarantine_after, $reserved->reserved_before]);
        $this->assertSame([0, 3, 0], [$reserved->stock_delta, $reserved->reserved_delta, $reserved->quarantine_delta]);
        $this->assertSame([3, 2, 0], [$released->reserved_before, $released->reserved_after, $released->stock_delta]);
        $this->assertSame([-2, -2, 0, 8], [$issued->stock_delta, $issued->reserved_delta, $issued->reserved_after, $issued->stock_after]);
        $this->assertSame('200.0000', $issued->unit_cost_basis);
        $this->assertSame('100.0000', $receipt->fresh()->average_cost_after);
        $this->assertSame('200.0000', $variant->fresh()->average_cost);
        $this->inventory->assertConsistent([$variant->id]);
    }

    public function test_wac_includes_reserved_stock_excludes_quarantine_and_rounds_four_decimals(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 2, 100);
        $item = $this->item($variant, 2);
        $this->inventory->reserve($this->orderChange('held', $item, Action::OrderReserved, 2));
        $return = $this->shipmentReturn($variant, '900.0000');
        $this->inventory->receiveQuarantine($this->returnChange('quarantine', $return, Action::ShipmentReturnReceived, '900.0000'));
        $this->receive($variant, 1, 101);
        $this->assertSame([3, 2, 1, '100.3333'], $this->balances($variant));
        $this->inventory->restock($this->returnChange('restock', $return, Action::ReturnRestocked, '900.0000'));
        $this->assertSame([4, 2, 0, '300.2500'], $this->balances($variant));
    }

    public function test_zero_stock_keeps_last_cost_and_next_import_uses_only_new_cost(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 1, 100);
        $item = $this->item($variant);
        $this->inventory->reserve($this->orderChange('hold', $item, Action::OrderReserved));
        $this->inventory->issue($this->orderChange('ship', $item, Action::OrderDispatched));
        $this->assertSame([0, 0, 0, '100.0000'], $this->balances($variant));
        $this->receive($variant, 1, 300);
        $this->assertSame([1, 0, 0, '300.0000'], $this->balances($variant));
    }

    public function test_replay_is_immutable_even_after_source_posted_actor_renamed_and_variant_deleted(): void
    {
        $variant = ProductVariant::factory()->create();
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 2, 'subtotal_amount' => 200]);
        $change = new InventoryChange('import:fixed', $variant->id, Action::ImportPosted, Reference::StockImport, $line->stock_import_id, '02', '00100.00', $this->admin->id, ' receipt ');
        $first = $this->inventory->receive($change);
        $this->assertSame($this->admin->full_name, $first->actor_name_snapshot);
        $before = $first->getAttributes();
        $line->stockImport->forceFill(['status' => StockImportStatus::Posted])->save();
        $this->admin->forceFill(['full_name' => 'Renamed', 'is_active' => false])->save();
        $variant->delete();
        $again = $this->inventory->receive($change);
        $this->assertSame($first->id, $again->id);
        $this->assertSame($before, $again->getAttributes());
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    #[DataProvider('replayMismatches')]
    public function test_same_key_rejects_changed_intent(string $field): void
    {
        $variant = ProductVariant::factory()->create();
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 2, 'subtotal_amount' => 200]);
        $values = ['operationKey' => 'import:key', 'variantId' => $variant->id, 'action' => Action::ImportPosted,
            'referenceType' => Reference::StockImport, 'referenceId' => $line->stock_import_id, 'quantity' => 1,
            'unitCostBasis' => '100', 'actorId' => $this->admin->id, 'reason' => 'Original receipt'];
        $this->inventory->receive(new InventoryChange(...$values));
        $values[$field] = match ($field) {
            'operationKey' => 'IMPORT:key', 'variantId' => ProductVariant::factory()->create()->id,
            'action' => Action::ManualAdjustIn, 'referenceType' => Reference::ManualAdjustment,
            'referenceId' => $line->stock_import_id + 1, 'quantity' => 2, 'unitCostBasis' => '101',
            'actorId' => null, 'reason' => 'A different receipt with evidence',
        };
        if ($field === 'action') {
            $values['reason'] = 'A different receipt with evidence';
        }
        $this->domain(fn () => $this->inventory->applyBatch([new InventoryChange(...$values)]), ErrorCode::IdempotencyMismatch);
        $this->assertSame(1, $variant->fresh()->stock_qty);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public static function replayMismatches(): array
    {
        return array_map(fn (string $field): array => [$field], ['operationKey', 'variantId', 'action', 'referenceType', 'referenceId', 'quantity', 'unitCostBasis', 'actorId', 'reason']);
    }

    public function test_issue_replay_returns_original_cost_after_later_receipt_changed_wac(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 2, 100);
        $item = $this->item($variant);
        $this->inventory->reserve($this->orderChange('hold', $item, Action::OrderReserved));
        $change = $this->orderChange('issue', $item, Action::OrderDispatched);
        $first = $this->inventory->issue($change);
        $this->receive($variant, 1, 300);
        $again = $this->inventory->issue($change);
        $this->assertSame($first->getAttributes(), $again->getAttributes());
        $this->assertSame('100.0000', $again->unit_cost_basis);
        $this->assertSame('200.0000', $variant->fresh()->average_cost);
    }

    #[DataProvider('invalidQuantities')]
    public function test_quantity_rejects_invalid_types_zero_and_signed_delta_overflow(mixed $quantity): void
    {
        $variant = ProductVariant::factory()->create();
        $this->domain(fn () => $this->inventory->applyBatch([new InventoryChange('invalid:q', $variant->id, Action::ImportPosted, Reference::StockImport, 1, $quantity, '100')]), ErrorCode::InvalidQuantity);
        $this->assertSame(0, $variant->fresh()->stock_qty);
    }

    public static function invalidQuantities(): array
    {
        return array_map(fn (mixed $q): array => [$q], [0, -1, '0', '1.0', 1.0, true, null, [], '1e1', '+1', ' 1', '2147483648', '99999999999']);
    }

    #[DataProvider('heldActions')]
    public function test_release_or_issue_cannot_take_another_orders_reservation(Action $action): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 2, 100);
        $owner = $this->item($variant);
        $other = $this->item($variant);
        $this->inventory->reserve($this->orderChange('held', $owner, Action::OrderReserved));
        $this->domain(fn () => $this->inventory->applyBatch([$this->orderChange('steal', $other, $action)]), ErrorCode::StateConflict);
        $this->assertSame([2, 1, 0, '100.0000'], $this->balances($variant));
    }

    public static function heldActions(): array
    {
        return [[Action::OrderReleased], [Action::OrderDispatched]];
    }

    public function test_cannot_reserve_more_than_available_or_order_quantity_or_reserve_again_after_issue(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 1, 100);
        $item = $this->item($variant, 2);
        $this->domain(fn () => $this->inventory->reserve($this->orderChange('too-many', $item, Action::OrderReserved, 2)), ErrorCode::StockUnavailable);
        $this->receive($variant, 3, 100);
        $this->domain(fn () => $this->inventory->reserve($this->orderChange('line-cap', $item, Action::OrderReserved, 3)), ErrorCode::StateConflict);
        $this->inventory->reserve($this->orderChange('held', $item, Action::OrderReserved, 2));
        $this->inventory->issue($this->orderChange('issue', $item, Action::OrderDispatched, 2));
        $this->domain(fn () => $this->inventory->reserve($this->orderChange('duplicate-business', $item, Action::OrderReserved)), ErrorCode::StateConflict);
    }

    public function test_release_allows_a_fresh_reservation_without_growing_physical_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 1, 100);
        $item = $this->item($variant);
        $this->inventory->reserve($this->orderChange('held', $item, Action::OrderReserved));
        $this->inventory->release($this->orderChange('released', $item, Action::OrderReleased));
        $this->inventory->reserve($this->orderChange('held-again', $item, Action::OrderReserved));
        $this->assertSame([1, 1, 0, '100.0000'], $this->balances($variant));
    }

    public function test_bad_import_cost_quantity_source_or_cancelled_document_cannot_change_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id]);
        $change = fn (string $key, int $qty, string $cost): InventoryChange => new InventoryChange($key, $variant->id, Action::ImportPosted, Reference::StockImport, $line->stock_import_id, $qty, $cost);
        $this->domain(fn () => $this->inventory->receive($change('wrong-cost', 1, '101')), ErrorCode::InvalidCost);
        $this->domain(fn () => $this->inventory->receive($change('wrong-qty', 2, '100')), ErrorCode::StateConflict);
        $this->inventory->receive($change('correct', 1, '100'));
        $this->domain(fn () => $this->inventory->receive($change('new-key-same-import', 1, '100')), ErrorCode::StateConflict);
        $line->stockImport->forceFill(['status' => StockImportStatus::Cancelled])->save();
        $this->domain(fn () => $this->inventory->receive($change('cancelled', 1, '100')), ErrorCode::StateConflict);
        $this->assertSame(1, $variant->fresh()->stock_qty);
    }

    public function test_quarantine_is_owned_by_return_and_cannot_be_received_or_disposed_twice(): void
    {
        $variant = ProductVariant::factory()->create();
        $return = $this->shipmentReturn($variant, '80.1250');
        $other = $this->shipmentReturn($variant, '80.1250');
        $this->inventory->receiveQuarantine($this->returnChange('received', $return, Action::ShipmentReturnReceived, '80.1250'));
        $this->domain(fn () => $this->inventory->restock($this->returnChange('steal', $other, Action::ReturnRestocked, '80.1250')), ErrorCode::StateConflict);
        $this->domain(fn () => $this->inventory->receiveQuarantine($this->returnChange('receive-again', $return, Action::ShipmentReturnReceived, '80.1250')), ErrorCode::StateConflict);
        $this->domain(fn () => $this->inventory->restock($this->returnChange('wrong-cost', $return, Action::ReturnRestocked, '90')), ErrorCode::InvalidCost);
        $this->inventory->restock($this->returnChange('restock', $return, Action::ReturnRestocked, '80.1250'));
        $this->domain(fn () => $this->inventory->writeOff($this->returnChange('dispose-again', $return, Action::ReturnWrittenOff, null, 'Inspection failed')), ErrorCode::StateConflict);
        $this->assertSame([1, 0, 0, '80.1250'], $this->balances($variant));
    }

    public function test_writeoff_removes_quarantine_only_using_original_receipt_cost(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 2, 100);
        $return = $this->shipmentReturn($variant, '80.1250');
        $this->inventory->receiveQuarantine($this->returnChange('received', $return, Action::ShipmentReturnReceived, '80.1250'));
        $return->forceFill(['cost_basis' => '999'])->save();
        $entry = $this->inventory->writeOff($this->returnChange('dispose', $return, Action::ReturnWrittenOff, null, 'Inspection failed'));
        $this->assertSame('80.1250', $entry->unit_cost_basis);
        $this->assertSame([2, 0, 0, '100.0000'], $this->balances($variant));
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public function test_size_exchange_completes_both_variants_atomically_and_replay_does_not_duplicate(): void
    {
        $source = ProductVariant::factory()->create();
        $target = ProductVariant::factory()->create(['product_color_id' => $source->product_color_id, 'size_value' => '43', 'size_numeric' => '43.0']);
        $this->receive($target, 1, 150);
        $rma = $this->rma($source, AfterSalesType::SizeExchange, $target);
        $this->inventory->reserve($this->rmaChange('target-held', $rma, Action::ExchangeReserved, $target));
        $this->inventory->receiveQuarantine($this->rmaChange('old-received', $rma, Action::ExchangeOldReceived, $source, '80'));
        $changes = [$this->rmaChange('new-out', $rma, Action::ExchangeCompletedOut, $target), $this->rmaChange('old-in', $rma, Action::ExchangeCompletedIn, $source, '80')];
        $first = $this->inventory->applyBatch($changes);
        $again = $this->inventory->applyBatch($changes);
        $this->assertSame([$first[0]->id, $first[1]->id], [$again[0]->id, $again[1]->id]);
        $this->assertSame([1, 0, 0, '80.0000'], $this->balances($source));
        $this->assertSame([0, 0, 0, '150.0000'], $this->balances($target));
        $this->domain(fn () => $this->inventory->reserve($this->rmaChange('duplicate-target', $rma, Action::ExchangeReserved, $target)), ErrorCode::StateConflict);
    }

    public function test_exchange_reservation_can_be_released_and_wrong_target_or_warranty_cannot_touch_stock(): void
    {
        $source = ProductVariant::factory()->create();
        $target = ProductVariant::factory()->create(['product_color_id' => $source->product_color_id, 'size_value' => '43', 'size_numeric' => '43.0']);
        $this->receive($target, 2, 100);
        $rma = $this->rma($source, AfterSalesType::SizeExchange, $target);
        $this->inventory->reserve($this->rmaChange('held', $rma, Action::ExchangeReserved, $target));
        $this->inventory->release($this->rmaChange('released', $rma, Action::ExchangeReleased, $target));
        $this->assertSame([2, 0, 0, '100.0000'], $this->balances($target));
        $this->domain(fn () => $this->inventory->reserve($this->rmaChange('wrong-target', $rma, Action::ExchangeReserved, $source)), ErrorCode::StateConflict);
        $warranty = $this->rma($source, AfterSalesType::WarrantyRepair);
        $this->domain(fn () => $this->inventory->receiveQuarantine($this->rmaChange('repair', $warranty, Action::DefectReceived, $source, '80')), ErrorCode::StateConflict);
    }

    public function test_defect_receipt_restock_and_manual_quarantine_writeoff_require_correct_rma_ownership(): void
    {
        $variant = ProductVariant::factory()->create();
        $rma = $this->rma($variant, AfterSalesType::DefectReturn);
        $this->inventory->receiveQuarantine($this->rmaChange('defect', $rma, Action::DefectReceived, $variant, '80'));
        $manual = $this->rmaChange('manual-dispose', $rma, Action::QuarantineWriteoff, $variant, null, 'Biên bản hàng hỏng được duyệt');
        $this->domain(fn () => $this->inventory->writeOff($manual), ErrorCode::StateConflict);
        $this->inventory->restock($this->rmaChange('restock', $rma, Action::ReturnRestocked, $variant, '80'));
        $this->assertSame([1, 0, 0, '80.0000'], $this->balances($variant));
        $closed = $this->rma($variant, AfterSalesType::DefectReturn);
        $this->inventory->receiveQuarantine($this->rmaChange('second-defect', $closed, Action::DefectReceived, $variant, '80'));
        $closed->forceFill(['status' => AfterSalesStatus::Completed])->save();
        $this->inventory->writeOff($this->rmaChange('closed-dispose', $closed, Action::QuarantineWriteoff, $variant, null, 'Biên bản hàng hỏng được duyệt'));
        $this->assertSame([1, 0, 0, '80.0000'], $this->balances($variant));
    }

    public function test_manual_adjustments_require_current_admin_evidence_and_record_one_inventory_loss(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->inventory->adjust($this->manual('count-in', $variant, Action::ManualAdjustIn, 3, '80.1667'));
        $change = $this->manual('count-out', $variant, Action::ManualAdjustOut, 3);
        $entry = $this->inventory->adjust($change);
        $this->inventory->adjust($change);
        $loss = SalesLedgerEntry::sole();
        $this->assertSame($entry->id, $loss->inventory_movement_id);
        $this->assertNull($loss->order_id);
        $this->assertSame([0, 0, 0, 241], [$loss->merchandise_revenue_delta, $loss->shipping_revenue_delta, $loss->cogs_delta, $loss->expense_delta]);
        $this->assertSame([0, 0, 0, '80.1667'], $this->balances($variant));
        DB::table('user_roles')->where('user_id', $this->admin->id)->delete();
        $this->domain(fn () => $this->inventory->adjust($this->manual('revoked', $variant, Action::ManualAdjustIn, 1, '100')), ErrorCode::Forbidden);
    }

    public function test_manual_adjustment_never_consumes_reserved_stock_and_ledger_overflow_rolls_back(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 1, 100);
        $item = $this->item($variant);
        $this->inventory->reserve($this->orderChange('held', $item, Action::OrderReserved));
        $this->domain(fn () => $this->inventory->adjust($this->manual('count-out', $variant, Action::ManualAdjustOut, 1)), ErrorCode::StockUnavailable);
        $expensive = ProductVariant::factory()->create();
        $this->inventory->adjust($this->manual('high-cost-in', $expensive, Action::ManualAdjustIn, 1000, '9999999999999999.9999'));
        $this->domain(fn () => $this->inventory->adjust($this->manual('overflow-loss', $expensive, Action::ManualAdjustOut, 1000)), ErrorCode::AmountOverflow);
        $this->assertSame(1000, $expensive->fresh()->stock_qty);
        $this->assertDatabaseMissing('inventory_movements', ['operation_key' => 'overflow-loss']);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public function test_bucket_uint32_limit_is_checked_separately_from_each_signed_delta(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->inventory->adjust($this->manual('large-1', $variant, Action::ManualAdjustIn, 2147483647, '0'));
        $this->inventory->adjust($this->manual('large-2', $variant, Action::ManualAdjustIn, 2147483647, '0'));
        $this->inventory->adjust($this->manual('large-3', $variant, Action::ManualAdjustIn, 1, '0'));
        $this->domain(fn () => $this->inventory->adjust($this->manual('overflow', $variant, Action::ManualAdjustIn, 1, '0')), ErrorCode::StockUnavailable);
        $this->assertSame(4294967295, $variant->fresh()->stock_qty);
        $this->inventory->assertConsistent([$variant->id]);
    }

    public function test_batch_failure_rolls_back_earlier_movements_even_if_caller_catches_exception(): void
    {
        $first = ProductVariant::factory()->create();
        $second = ProductVariant::factory()->create();
        $this->receive($first, 1, 100);
        $item1 = $this->item($first);
        $item2 = $this->item($second);
        $this->domain(fn () => $this->inventory->applyBatch([$this->orderChange('batch-1', $item1, Action::OrderReserved), $this->orderChange('batch-2', $item2, Action::OrderReserved)]), ErrorCode::StockUnavailable);
        $this->assertSame(0, $first->fresh()->reserved_qty);
        $this->assertDatabaseMissing('inventory_movements', ['operation_key' => 'batch-1']);
    }

    public function test_outer_rollback_removes_stock_movement_loss_and_outbox_together(): void
    {
        $variant = ProductVariant::factory()->create();
        $before = $variant->fresh()->getAttributes();
        try {
            DB::transaction(function () use ($variant): void {
                $this->inventory->adjust($this->manual('atomic-in', $variant, Action::ManualAdjustIn, 2, '100'));
                $this->inventory->adjust($this->manual('atomic-loss', $variant, Action::ManualAdjustOut, 1));
                app(OutboxService::class)->enqueue('atomic:stock', 'INVENTORY_ADJUSTED', OutboxAggregateType::User, $this->admin->id, ['variant_id' => $variant->id]);
                throw new RuntimeException('Abort business operation');
            });
            $this->fail('Expected business failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Abort business operation', $exception->getMessage());
        }
        $this->assertSame($before, $variant->fresh()->getAttributes());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    #[DataProvider('drifts')]
    public function test_reconciliation_blocks_new_writes_without_repairing_drift(string $field): void
    {
        $variant = ProductVariant::factory()->create();
        $movement = $this->receive($variant, 2, 100);
        match ($field) {
            'stock' => DB::table('product_variants')->where('id', $variant->id)->update(['stock_qty' => 3]),
            'cost' => DB::table('product_variants')->where('id', $variant->id)->update(['average_cost' => '101']),
            'history' => DB::table('inventory_movements')->where('id', $movement->id)->update(['average_cost_before' => '99']),
            'basis' => DB::table('inventory_movements')->where('id', $movement->id)->update(['unit_cost_basis' => null]),
        };
        $before = $variant->fresh()->getAttributes();
        $item = $this->item($variant);
        $this->domain(fn () => $this->inventory->assertConsistent([$variant->id]), ErrorCode::StateConflict);
        $this->domain(fn () => $this->inventory->reserve($this->orderChange('blocked', $item, Action::OrderReserved)), ErrorCode::StateConflict);
        $this->assertSame($before, $variant->fresh()->getAttributes());
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public static function drifts(): array
    {
        return [['stock'], ['cost'], ['history'], ['basis']];
    }

    public function test_hidden_variant_rejects_new_reservation_but_allows_release_of_historical_hold(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 2, 100);
        $item = $this->item($variant, 2);
        $this->inventory->reserve($this->orderChange('held', $item, Action::OrderReserved));
        $variant->delete();
        $this->domain(fn () => $this->inventory->reserve($this->orderChange('new', $item, Action::OrderReserved)), ErrorCode::ProductUnavailable);
        $this->inventory->release($this->orderChange('release-old', $item, Action::OrderReleased));
        $this->assertSame(0, ProductVariant::withTrashed()->findOrFail($variant->id)->reserved_qty);
    }

    public function test_batch_rejects_duplicate_keys_wrong_wrappers_missing_evidence_and_unknown_variants(): void
    {
        $variant = ProductVariant::factory()->create();
        $change = $this->manual('duplicate', $variant, Action::ManualAdjustIn, 1, '100');
        $this->domain(fn () => $this->inventory->applyBatch([$change, $change]), ErrorCode::InvalidPayload);
        $this->domain(fn () => $this->inventory->reserve($change), ErrorCode::InvalidPayload);
        $this->domain(fn () => $this->inventory->applyBatch([]), ErrorCode::InvalidPayload);
        $this->domain(fn () => $this->inventory->adjust(new InventoryChange('no-evidence', $variant->id, Action::ManualAdjustIn, Reference::ManualAdjustment, null, 1, '100', $this->admin->id, 'short')), ErrorCode::InvalidPayload);
        $this->domain(fn () => $this->inventory->applyBatch([new InventoryChange('missing', PHP_INT_MAX, Action::ImportPosted, Reference::StockImport, 1, 1, '100')]), ErrorCode::NotFound);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    #[DataProvider('invalidIntents')]
    public function test_invalid_intents_fail_before_any_bucket_or_ledger_write(string $field, mixed $value, ErrorCode $code): void
    {
        $variant = ProductVariant::factory()->create();
        $values = ['operationKey' => 'manual:valid', 'variantId' => $variant->id, 'action' => Action::ManualAdjustIn,
            'referenceType' => Reference::ManualAdjustment, 'referenceId' => 123, 'quantity' => 1,
            'unitCostBasis' => '100', 'actorId' => $this->admin->id, 'reason' => 'Biên bản kiểm kê được phê duyệt'];
        $values[$field] = $value;
        $this->domain(fn () => $this->inventory->adjust(new InventoryChange(...$values)), $code);
        $this->assertSame([0, 0, 0, '0.0000'], $this->balances($variant));
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public static function invalidIntents(): array
    {
        return [
            ['operationKey', '', ErrorCode::InvalidPayload], ['operationKey', ' invalid', ErrorCode::InvalidPayload],
            ['operationKey', str_repeat('a', 181), ErrorCode::InvalidPayload], ['operationKey', 'khóa:1', ErrorCode::InvalidPayload],
            ['variantId', 0, ErrorCode::InvalidPayload], ['referenceId', null, ErrorCode::InvalidPayload],
            ['referenceId', -1, ErrorCode::InvalidPayload], ['actorId', 0, ErrorCode::InvalidPayload],
            ['actorId', null, ErrorCode::InvalidPayload], ['reason', 'too short', ErrorCode::InvalidPayload],
            ['reason', str_repeat('ă', 501), ErrorCode::InvalidPayload], ['reason', "bad\xFF", ErrorCode::InvalidPayload],
            ['unitCostBasis', null, ErrorCode::InvalidCost], ['unitCostBasis', 100.0, ErrorCode::InvalidCost],
            ['unitCostBasis', '-1', ErrorCode::InvalidCost], ['unitCostBasis', '0.00001', ErrorCode::InvalidCost],
            ['referenceType', Reference::Order, ErrorCode::InvalidPayload],
        ];
    }

    #[DataProvider('actorStates')]
    public function test_new_manual_writes_reject_inactive_deleted_or_non_admin_actor(string $state): void
    {
        match ($state) {
            'inactive' => $this->admin->forceFill(['is_active' => false])->save(),
            'deleted' => $this->admin->delete(),
            'revoked' => DB::table('user_roles')->where('user_id', $this->admin->id)->delete(),
        };
        $variant = ProductVariant::factory()->create();
        $this->domain(fn () => $this->inventory->adjust($this->manual('forbidden', $variant, Action::ManualAdjustIn, 1, '100')), ErrorCode::Forbidden);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function actorStates(): array
    {
        return [['inactive'], ['deleted'], ['revoked']];
    }

    public function test_receipt_cost_must_match_snapshot_and_wrong_rma_product_color_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create();
        $return = $this->shipmentReturn($variant, '80');
        $this->domain(fn () => $this->inventory->receiveQuarantine($this->returnChange('cost-forged', $return, Action::ShipmentReturnReceived, '81')), ErrorCode::InvalidCost);
        $target = ProductVariant::factory()->create();
        $this->receive($target, 1, 100);
        $rma = $this->rma($variant, AfterSalesType::SizeExchange, $target);
        $this->domain(fn () => $this->inventory->reserve($this->rmaChange('different-color', $rma, Action::ExchangeReserved, $target)), ErrorCode::StateConflict);
        $this->assertSame(0, $target->fresh()->reserved_qty);
    }

    private function receive(ProductVariant $variant, int $quantity, int $cost): InventoryMovement
    {
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost_amount' => $cost, 'subtotal_amount' => $quantity * $cost]);

        return $this->inventory->receive(new InventoryChange('import:'.$line->stock_import_id, $variant->id, Action::ImportPosted, Reference::StockImport, $line->stock_import_id, $quantity, $cost));
    }

    private function item(ProductVariant $variant, int $quantity = 1): OrderItem
    {
        return OrderItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $quantity, 'subtotal_amount' => $quantity * 100, 'net_amount' => $quantity * 100]);
    }

    private function orderChange(string $key, OrderItem $item, Action $action, int $quantity = 1): InventoryChange
    {
        return new InventoryChange($key, $item->variant_id, $action, Reference::Order, $item->order_id, $quantity);
    }

    private function shipmentReturn(ProductVariant $variant, string $cost): ShipmentReturnItem
    {
        $unit = OrderItemUnit::factory()->create(['order_item_id' => $this->item($variant)->id]);

        return ShipmentReturnItem::factory()->create(['order_item_unit_id' => $unit->id, 'cost_basis' => $cost]);
    }

    private function returnChange(string $key, ShipmentReturnItem $return, Action $action, ?string $cost = null, ?string $reason = null): InventoryChange
    {
        return new InventoryChange($key, $return->variant_id, $action, Reference::ShipmentReturnItem, $return->id, 1, $cost, null, $reason);
    }

    private function rma(ProductVariant $source, AfterSalesType $type, ?ProductVariant $target = null): AfterSalesRequest
    {
        $unit = OrderItemUnit::factory()->create(['order_item_id' => $this->item($source)->id]);

        return AfterSalesRequest::factory()->create(['order_item_unit_id' => $unit->id, 'type' => $type, 'target_variant_id' => $target?->id, 'returned_cogs_amount' => 80]);
    }

    private function rmaChange(string $key, AfterSalesRequest $rma, Action $action, ProductVariant $variant, ?string $cost = null, ?string $reason = null): InventoryChange
    {
        return new InventoryChange($key, $variant->id, $action, Reference::AfterSalesRequest, $rma->id, 1, $cost, $this->admin->id, $reason);
    }

    private function manual(string $key, ProductVariant $variant, Action $action, int $quantity, ?string $cost = null): InventoryChange
    {
        return new InventoryChange($key, $variant->id, $action, Reference::ManualAdjustment, 123, $quantity, $cost, $this->admin->id, 'Biên bản kiểm kê được Admin phê duyệt');
    }

    private function balances(ProductVariant $variant): array
    {
        $fresh = $variant->fresh();

        return [$fresh->stock_qty, $fresh->reserved_qty, $fresh->quarantine_qty, $fresh->average_cost];
    }

    private function domain(Closure $operation, ErrorCode $expected): void
    {
        try {
            $operation();
            $this->fail('Expected domain rejection.');
        } catch (DomainException $exception) {
            $this->assertSame($expected, $exception->errorCode);
        }
    }
}
