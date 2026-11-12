<?php

namespace Tests\Feature;

use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_and_staff_can_read_product_stock_and_cost_while_customer_and_guest_are_denied(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 5, 'reserved_qty' => 2, 'quarantine_qty' => 1, 'average_cost' => '321.9876'])->save();
        $movement = $this->movement($variant, [
            'stock_before' => 0,
            'stock_after' => 5,
            'stock_delta' => 5,
            'reserved_after' => 2,
            'reserved_delta' => 2,
            'quarantine_after' => 1,
            'quarantine_delta' => 1,
            'unit_cost_basis' => '321.9876',
            'average_cost_after' => '321.9876',
        ]);
        $url = route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create())->getJson($url)->assertForbidden();

        foreach ([User::factory()->administrator()->create(), User::factory()->staff()->create()] as $employee) {
            $response = $this->actingAs($employee)->get($url)->assertOk();
            $response->assertSee($variant->sku)
                ->assertSee('Khả dụng')
                ->assertSee('3')
                ->assertSee('321.9876 ₫')
                ->assertSee('#'.$movement->id)
                ->assertSee('Đơn vị: 321.9876 ₫');
        }
    }

    public function test_selected_variant_must_belong_to_product_and_inactive_soft_deleted_history_remains_readable(): void
    {
        [$product, $color, $variant] = $this->productWithVariant();
        [, , $foreignVariant] = $this->productWithVariant('Khác');
        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('admin.products.show', ['product' => $product, 'variant_id' => $foreignVariant->id]))->assertNotFound();

        $movement = $this->movement($variant, ['stock_before' => 0, 'stock_after' => 2, 'stock_delta' => 2]);
        $variant->forceFill(['is_active' => false])->save();
        $this->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('Ngừng bán')
            ->assertSee('#'.$movement->id);
        $variant->delete();
        $color->delete();
        $product->delete();

        $this->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('Đã xóa mềm')
            ->assertSee($variant->sku)
            ->assertSee('#'.$movement->id);
    }

    public function test_product_search_finds_sku_and_deleted_products_can_be_filtered_for_history_lookup(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $product->delete();
        $employee = User::factory()->staff()->create();

        $this->actingAs($employee)
            ->get(route('admin.products.index', ['search' => $variant->sku, 'status' => 'deleted']))
            ->assertOk()
            ->assertSee($variant->sku)
            ->assertSee(route('admin.products.show', $product));
    }

    public function test_movements_are_scoped_filtered_and_paginated_twenty_newest_first(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        [, , $foreignVariant] = $this->productWithVariant('Ngoài sản phẩm');
        $employee = User::factory()->staff()->create();
        $businessTimezone = config('store.business_timezone');
        $today = CarbonImmutable::now($businessTimezone)->startOfDay();
        $referenceMovement = $this->movement($variant, [
            'created_at' => $today->setTime(11, 59)->utc(),
        ]);
        $reference = $referenceMovement->reference_id;
        $movements = [$referenceMovement];

        for ($index = 0; $index < 25; $index++) {
            $movements[] = $this->movement($variant, [
                'action' => InventoryAction::ImportPosted,
                'reference_type' => InventoryReferenceType::StockImport,
                'reference_id' => $reference,
                'created_at' => $today->setTime(12, 0)->utc()->addMinutes($index),
            ]);
        }
        $latestMatchingMovement = $this->movement($variant, [
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => $reference,
            'created_at' => $today->setTime(23, 59, 59, 999999)->utc(),
        ]);
        $movements[] = $latestMatchingMovement;
        $this->movement($variant, [
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => $reference,
            'created_at' => $today->addDay()->setTime(0, 0)->utc(),
        ]);
        $this->movement($variant, [
            'action' => InventoryAction::OrderReserved,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => $reference,
            'created_at' => $today->setTime(12, 0)->utc()->addMinutes(25),
        ]);
        $this->movement($variant, [
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => $reference + 1000,
            'created_at' => $today->setTime(12, 0)->utc()->addMinutes(26),
        ]);
        $this->movement($variant, [
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::Order,
            'reference_id' => $reference,
            'created_at' => $today->setTime(12, 0)->utc()->addMinutes(27),
        ]);
        $this->movement($foreignVariant, [
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => $reference,
            'created_at' => $today->setTime(12, 0)->utc()->addMinutes(30),
        ]);

        $this->assertCount(27, $movements);

        $response = $this->actingAs($employee)->get(route('admin.products.show', [
            'product' => $product,
            'variant_id' => $variant->id,
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'action' => InventoryAction::ImportPosted->value,
            'reference_type' => InventoryReferenceType::StockImport->value,
            'reference_id' => $reference,
        ]))->assertOk();

        $response->assertViewHas('movements', function ($paginator) use ($latestMatchingMovement): bool {
            return $paginator->total() === 27
                && $paginator->perPage() === 20
                && $paginator->count() === 20
                && $paginator->items()[0]->id === $latestMatchingMovement->id;
        });
        $secondPage = $this->actingAs($employee)->get(route('admin.products.show', [
            'product' => $product,
            'variant_id' => $variant->id,
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'action' => InventoryAction::ImportPosted->value,
            'reference_type' => InventoryReferenceType::StockImport->value,
            'reference_id' => $reference,
            'page' => 2,
        ]))->assertOk();

        $secondPage->assertViewHas('movements', fn ($paginator): bool => $paginator->count() === 7 && $paginator->currentPage() === 2);
    }

    public function test_reconciliation_reports_incomplete_history_without_inventing_a_balance_difference(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 2, 'reserved_qty' => 1, 'quarantine_qty' => 0])->save();
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('HISTORY_INCOMPLETE')
            ->assertDontSee('OBSERVED_BALANCE_DIFF');
    }

    public function test_empty_history_with_zero_buckets_is_not_reported_as_reconciled(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 0, 'reserved_qty' => 0, 'quarantine_qty' => 0])->save();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('HISTORY_INCOMPLETE')
            ->assertDontSee('OBSERVED_BALANCE_DIFF')
            ->assertDontSee('Lịch sử liên tục từ số dư đầu tiên');
    }

    public function test_reconciliation_reports_observed_difference_independently_of_history_continuity(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 3, 'reserved_qty' => 0, 'quarantine_qty' => 0])->save();
        $this->movement($variant, [
            'stock_before' => 0,
            'stock_after' => 2,
            'stock_delta' => 2,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_after' => 0,
        ]);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('OBSERVED_BALANCE_DIFF')
            ->assertDontSee('HISTORY_INCOMPLETE');
    }

    public function test_reconciliation_can_report_both_warnings_when_the_chain_has_a_gap_and_final_balance_differs(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 4, 'reserved_qty' => 0, 'quarantine_qty' => 0])->save();
        $this->movement($variant, [
            'stock_before' => 0,
            'stock_after' => 2,
            'stock_delta' => 2,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_after' => 0,
        ]);
        $this->movement($variant, [
            'stock_before' => 3,
            'stock_after' => 3,
            'stock_delta' => 0,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_after' => 0,
        ]);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('HISTORY_INCOMPLETE')
            ->assertSee('OBSERVED_BALANCE_DIFF');
    }

    public function test_opening_the_inventory_history_does_not_create_movements(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $before = InventoryMovement::query()->count();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]))
            ->assertOk();

        $this->assertSame($before, InventoryMovement::query()->count());
    }

    /** @return array{Product, ProductColor, ProductVariant} */
    private function productWithVariant(string $name = 'Giày kiểm tra'): array
    {
        $product = Product::factory()->create(['name' => $name]);
        $color = ProductColor::factory()->for($product, 'product')->create(['color_name' => 'Đen']);
        $variant = ProductVariant::factory()->for($color, 'color')->create();

        return [$product, $color, $variant];
    }

    /** @param array<string, mixed> $overrides */
    private function movement(ProductVariant $variant, array $overrides = []): InventoryMovement
    {
        return InventoryMovement::factory()->create(array_merge([
            'variant_id' => $variant->id,
            'action' => InventoryAction::ImportPosted,
            'stock_before' => 0,
            'stock_after' => 1,
            'stock_delta' => 1,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'reserved_delta' => 0,
            'quarantine_before' => 0,
            'quarantine_after' => 0,
            'quarantine_delta' => 0,
            'average_cost_before' => '0.0000',
            'average_cost_after' => '100.0000',
            'unit_cost_basis' => '100.0000',
            'reference_type' => InventoryReferenceType::StockImport,
            'created_at' => now(),
        ], $overrides));
    }
}
