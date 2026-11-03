<?php

namespace Tests\Feature;

use App\Enums\StockImportStatus;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockImportService;
use App\Support\BusinessClock;
use Database\Seeders\StockImportDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockImportTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private StockImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->staff()->create();
        $this->service = app(StockImportService::class);
        Storage::fake('public');
    }

    public function test_draft_create_edit_and_cancel_do_not_change_inventory_and_totals_are_server_authoritative(): void
    {
        $data = $this->data();
        $this->actingAs($this->actor)->post('/admin/stock-imports', $data)->assertRedirect()->assertSessionHasNoErrors();
        $import = StockImport::sole();
        $this->assertSame([3, 300, StockImportStatus::Draft], [$import->total_quantity, $import->total_cost_amount, $import->status]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(0, ProductVariant::sum('stock_qty'));
        unset($data['command_key']);
        $data['items'][0]['quantity'] = 2;
        $this->put('/admin/stock-imports/'.$import->id, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([4, 400], [$import->fresh()->total_quantity, $import->fresh()->total_cost_amount]);
        $this->post('/admin/stock-imports/'.$import->id.'/cancel', ['reason' => 'Sai nhà cung cấp'])->assertRedirect();
        $this->assertSame(StockImportStatus::Cancelled, $import->fresh()->status);
        $this->assertStringContainsString('Sai nhà cung cấp', $import->fresh()->note);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(0, ProductVariant::sum('stock_qty'));
        $this->postJson('/admin/stock-imports/'.$import->id.'/post')->assertConflict();
        $this->putJson('/admin/stock-imports/'.$import->id, $data)->assertConflict();
    }

    public function test_post_updates_each_sku_once_freezes_snapshots_and_keeps_posted_document_immutable(): void
    {
        $data = $this->data();
        $import = $this->service->saveDraft($this->actor, null, $data);
        $supplier = Supplier::findOrFail($data['supplier_id']);
        $supplier->forceFill(['name' => 'Tên lúc ghi sổ'])->save();
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-11-02T01:02:03.123456Z');
        $first = $this->service->post($this->actor, $import);
        $before = $first->getAttributes();
        $this->assertSame('2026-11-02 01:02:03.123456', $first->posted_at->format('Y-m-d H:i:s.u'));
        $this->assertSame('Tên lúc ghi sổ', $first->supplier_name_snapshot);
        $this->assertSame($this->actor->id, $first->posted_by);
        $this->assertSame(3, (int) ProductVariant::sum('stock_qty'));
        $this->assertDatabaseCount('inventory_movements', 2);
        foreach (InventoryMovement::all() as $m) {
            $this->assertSame($m->stock_before + $m->stock_delta, $m->stock_after);
            $this->assertSame('100.0000', $m->average_cost_after);
        }
        $supplier->forceFill(['is_active' => false, 'name' => 'Tên sau ghi sổ'])->save();
        $clock->freeze('2030-01-01T00:00:00Z');
        $again = $this->service->post($this->actor, $import);
        $this->assertSame($before, $again->getAttributes());
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame($import->id, $this->service->saveDraft($this->actor, null, $data)->id);
        $this->actingAs($this->actor)->postJson('/admin/stock-imports/'.$import->id.'/cancel', ['reason' => 'Không được hủy đã ghi sổ'])->assertConflict();
        unset($data['command_key']);
        $this->putJson('/admin/stock-imports/'.$import->id, $data)->assertConflict();
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public function test_weighted_average_is_exact_and_existing_reserve_is_not_changed(): void
    {
        $variant = ProductVariant::factory()->create();
        $supplier = Supplier::factory()->create();
        foreach ([[5, 100], [5, 300]] as [$quantity,$cost]) {
            $import = $this->service->saveDraft($this->actor, null, ['command_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'items' => [['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost_amount' => $cost]]]);
            $this->service->post($this->actor, $import);
        }
        $this->assertSame([10, 0, 0, '200.0000'], [$variant->fresh()->stock_qty, $variant->fresh()->reserved_qty, $variant->fresh()->quarantine_qty, $variant->fresh()->average_cost]);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_bad_last_sku_rolls_back_first_movement_balance_and_posted_metadata(): void
    {
        $data = $this->data();
        $lastId = $data['items'][1]['variant_id'];
        DB::table('product_variants')->where('id', $lastId)->update(['stock_qty' => 1]);
        $import = $this->service->saveDraft($this->actor, null, $data);
        $this->actingAs($this->actor)->postJson('/admin/stock-imports/'.$import->id.'/post')->assertConflict();
        $this->assertSame(0, ProductVariant::findOrFail($data['items'][0]['variant_id'])->stock_qty);
        $this->assertSame(1, ProductVariant::findOrFail($lastId)->stock_qty);
        $this->assertSame(StockImportStatus::Draft, $import->fresh()->status);
        $this->assertNull($import->fresh()->posted_at);
        $this->assertNull($import->fresh()->posted_by);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_creation_key_replay_has_one_document_and_different_intent_is_conflict(): void
    {
        $data = $this->data();
        $first = $this->service->saveDraft($this->actor, null, $data);
        $data['items'] = array_reverse($data['items']);
        $again = $this->service->saveDraft($this->actor, null, $data);
        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('stock_imports', 1);
        $data['items'][0]['quantity'] = 9;
        $this->actingAs($this->actor)->postJson('/admin/stock-imports', $data)->assertConflict();
        $this->assertDatabaseCount('stock_imports', 1);
        $this->assertDatabaseCount('stock_import_items', 2);
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_drafts_never_create_partial_documents(string $case): void
    {
        $data = $this->data();
        match ($case) {
            'zero-quantity' => $data['items'][0]['quantity'] = 0,
            'negative-cost' => $data['items'][0]['unit_cost_amount'] = -1,
            'float-cost' => $data['items'][0]['unit_cost_amount'] = 1.5,
            'quantity-overflow' => $data['items'][0]['quantity'] = '2147483648',
            'sum-overflow' => $data['items'] = [['variant_id' => $data['items'][0]['variant_id'], 'quantity' => 2147483647, 'unit_cost_amount' => 9999999999999999]],
            'zero-cost-staff' => $data['items'][0]['unit_cost_amount'] = 0,
            'duplicate' => $data['items'][] = $data['items'][0],
            'missing-supplier' => $data['supplier_id'] = PHP_INT_MAX,
            'missing-variant' => $data['items'][1]['variant_id'] = PHP_INT_MAX,
            'fake-total' => $data['total_cost_amount'] = 1,
            'fake-status' => $data['status'] = 'POSTED',
            'bad-key' => $data['command_key'] = 'not-uuid',
        };
        $this->actingAs($this->actor)->postJson('/admin/stock-imports', $data)->assertUnprocessable();
        $this->assertDatabaseCount('stock_imports', 0);
        $this->assertDatabaseCount('stock_import_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function invalidCases(): array
    {
        return array_map(fn ($x) => [$x], ['zero-quantity', 'negative-cost', 'float-cost', 'quantity-overflow', 'sum-overflow', 'zero-cost-staff', 'duplicate', 'missing-supplier', 'missing-variant', 'fake-total', 'fake-status', 'bad-key']);
    }

    public function test_supplier_must_still_be_active_and_soft_deleted_variant_is_rejected_at_post(): void
    {
        $data = $this->data();
        $import = $this->service->saveDraft($this->actor, null, $data);
        Supplier::whereKey($data['supplier_id'])->update(['is_active' => false]);
        $this->actingAs($this->actor)->postJson('/admin/stock-imports/'.$import->id.'/post')->assertUnprocessable();
        Supplier::whereKey($data['supplier_id'])->update(['is_active' => true]);
        ProductVariant::findOrFail($data['items'][0]['variant_id'])->delete();
        $this->postJson('/admin/stock-imports/'.$import->id.'/post')->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertNull($import->fresh()->posted_at);
    }

    public function test_admin_can_approve_free_receipt_with_meaningful_note_but_staff_cannot_post_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $data = $this->data();
        $data['items'][0]['unit_cost_amount'] = 0;
        $data['note'] = 'Duyệt nhập tặng phục vụ chương trình trưng bày';
        $import = $this->service->saveDraft($admin, null, $data);
        $this->actingAs($this->actor)->postJson('/admin/stock-imports/'.$import->id.'/post')->assertUnprocessable();
        $this->service->post($admin, $import);
        $this->assertSame(StockImportStatus::Posted, $import->fresh()->status);
        $this->assertSame('0.0000', ProductVariant::findOrFail($data['items'][0]['variant_id'])->average_cost);
        $this->assertSame($admin->id, $import->fresh()->posted_by);
    }

    public function test_post_recomputes_draft_totals_from_locked_items_and_accepts_inactive_not_deleted_sku(): void
    {
        $data = $this->data();
        ProductVariant::whereKey($data['items'][0]['variant_id'])->update(['is_active' => false]);
        $import = $this->service->saveDraft($this->actor, null, $data);
        $import->forceFill(['total_quantity' => 99, 'total_cost_amount' => 999])->save();
        $posted = $this->service->post($this->actor, $import);
        $this->assertSame([3, 300], [$posted->total_quantity, $posted->total_cost_amount]);
    }

    public function test_permissions_and_cancel_reason_validation(): void
    {
        $data = $this->data();
        $import = $this->service->saveDraft($this->actor, null, $data);
        $this->getJson('/admin/stock-imports')->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create());
        foreach (['/admin/stock-imports', '/admin/stock-imports/'.$import->id] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->postJson('/admin/stock-imports/'.$import->id.'/post')->assertForbidden();
        $this->actingAs($this->actor)->get('/admin/stock-imports/create')->assertOk();
        $this->get('/admin/stock-imports/'.$import->id)->assertOk()->assertSee('SKU snapshot');
        $this->postJson('/admin/stock-imports/'.$import->id.'/cancel', ['reason' => '   '])->assertUnprocessable();
        $this->assertSame(StockImportStatus::Draft, $import->fresh()->status);
    }

    public function test_demo_seeder_posts_two_receipts_through_services_and_replay_keeps_all_balances(): void
    {
        User::factory()->administrator()->create();
        config(['store.demo.enabled' => true]);
        $this->seed(StockImportDemoSeeder::class);
        $before = ProductVariant::orderBy('id')->get()->map(fn ($v) => [$v->id, $v->stock_qty, $v->average_cost])->all();
        $this->seed(StockImportDemoSeeder::class);
        $this->assertSame($before, ProductVariant::orderBy('id')->get()->map(fn ($v) => [$v->id, $v->stock_qty, $v->average_cost])->all());
        $this->assertDatabaseCount('products', 4);
        $this->assertDatabaseCount('stock_imports', 2);
        $this->assertDatabaseCount('inventory_movements', 48);
        $this->assertSame(168, (int) ProductVariant::sum('stock_qty'));
        // (5 × 1.080.000 + 2 × 1.140.000) / 7: a realistic cost for a shoe sold at 1.500.000 ₫.
        $this->assertSame(['1097142.8571'], ProductVariant::distinct()->pluck('average_cost')->all());
        $this->assertSame(2, StockImport::where('status', 'POSTED')->count());
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public function test_uint32_total_quantity_overflow_is_rejected_before_sql(): void
    {
        $data = $this->data();
        foreach ($data['items'] as &$line) {
            $line['quantity'] = 2147483647;
            $line['unit_cost_amount'] = 1;
        }unset($line);
        $data['items'][] = ['variant_id' => ProductVariant::factory()->create()->id, 'quantity' => 2, 'unit_cost_amount' => 1];
        $this->actingAs($this->actor)->postJson('/admin/stock-imports', $data)->assertUnprocessable();
        $this->assertDatabaseCount('stock_imports', 0);
    }

    public function test_long_draft_note_does_not_overflow_movement_reason_on_post(): void
    {
        $data = $this->data();
        $data['note'] = str_repeat('Ghi chú nhập hàng. ', 50);
        $this->assertGreaterThan(500, mb_strlen($data['note']));
        $import = $this->service->saveDraft($this->actor, null, $data);
        $posted = $this->service->post($this->actor, $import);
        $this->assertSame(trim($data['note']), $posted->note);
        $this->assertSame(StockImportStatus::Posted, $posted->status);
        foreach (InventoryMovement::all() as $movement) {
            $this->assertLessThanOrEqual(500, mb_strlen($movement->reason));
        }
    }

    private function data(): array
    {
        return ['command_key' => (string) Str::uuid(), 'supplier_id' => Supplier::factory()->create()->id, 'note' => 'Kiểm tra phiếu nhập',
            'items' => [['variant_id' => ProductVariant::factory()->create()->id, 'quantity' => 1, 'unit_cost_amount' => 100], ['variant_id' => ProductVariant::factory()->create()->id, 'quantity' => 2, 'unit_cost_amount' => 100]]];
    }
}
