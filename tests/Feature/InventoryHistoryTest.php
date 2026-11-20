<?php

namespace Tests\Feature;

use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\ShipmentReturnItem;
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

        $adminResponse = $this->actingAs(User::factory()->administrator()->create())->get($url)->assertOk();
        $adminResponse->assertSee($variant->sku)
            ->assertSee('Biến động kho')
            ->assertSee('Xem biến động kho')
            ->assertSee('Có thể bán')
            ->assertSee('Tồn kho - Đã giữ cho đơn; không trừ trùng chưa thể bán')
            ->assertSee('Khả dụng')
            ->assertSee('3')
            ->assertSee('Tồn kho +5 đôi → 5 đôi')
            ->assertSee('Đã giữ cho đơn +2 đôi → 2 đôi')
            ->assertSee('Chưa thể bán +1 đôi → 1 đôi')
            ->assertSee(route('admin.stock-imports.show', $movement->reference_id))
            ->assertSee('321.9876')
            ->assertSee('#'.$movement->id)
            ->assertSee('Đơn vị: 321.9876 ₫');

        $staffResponse = $this->actingAs(User::factory()->staff()->create())->get($url)->assertOk();
        $staffResponse->assertSee($variant->sku)
            ->assertSee('Biến động kho')
            ->assertSee('Xem biến động kho')
            ->assertSee('Có thể bán')
            ->assertSee('Khả dụng')
            ->assertSee('3')
            ->assertSee('Tồn kho +5 đôi → 5 đôi')
            ->assertSee('Đã giữ cho đơn +2 đôi → 2 đôi')
            ->assertSee('Chưa thể bán +1 đôi → 1 đôi')
            ->assertSee('#'.$movement->id)
            ->assertDontSee(route('admin.stock-imports.show', $movement->reference_id))
            ->assertDontSee('321.9876')
            ->assertDontSee('average_cost')
            ->assertDontSee('unit_cost_basis')
            ->assertDontSee('Giá vốn')
            ->assertDontSee('Đơn vị:');
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

    public function test_simplified_layout_renders_role_scoped_document_links_reconciliation_pill_and_compact_deltas(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 4, 'reserved_qty' => 0, 'quarantine_qty' => 0])->save();

        $order = Order::factory()->create();
        $returnItem = ShipmentReturnItem::factory()->create();
        $returnOrderId = $returnItem->shipment->order_id;

        $importMovement = $this->movement($variant, [
            'action' => InventoryAction::ImportPosted,
            'reference_type' => InventoryReferenceType::StockImport,
            'reference_id' => 9001,
            'stock_before' => 0,
            'stock_delta' => 4,
            'stock_after' => 4,
        ]);

        $this->movement($variant, [
            'action' => InventoryAction::OrderReserved,
            'reference_type' => InventoryReferenceType::Order,
            'reference_id' => $order->id,
            'stock_before' => 4,
            'stock_delta' => 0,
            'stock_after' => 4,
            'reserved_before' => 0,
            'reserved_delta' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_delta' => 0,
            'quarantine_after' => 0,
        ]);

        $this->movement($variant, [
            'action' => InventoryAction::ShipmentReturnReceived,
            'reference_type' => InventoryReferenceType::ShipmentReturnItem,
            'reference_id' => $returnItem->id,
            'stock_before' => 4,
            'stock_delta' => 0,
            'stock_after' => 4,
        ]);

        $url = route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]);

        $adminResponse = $this->actingAs(User::factory()->administrator()->create())->get($url)->assertOk();
        $adminResponse->assertSee('Đối chiếu khớp toàn bộ lịch sử')
            ->assertSee('Không đổi số lượng')
            ->assertSee('Chi tiết')
            ->assertSee('Trước: 0 · Thay đổi: +4 · Sau:')
            ->assertSee(route('admin.stock-imports.show', $importMovement->reference_id))
            ->assertSee(route('admin.orders.show', $order->id))
            ->assertSee(route('admin.orders.show', $returnOrderId).'#xu-ly-hang-hoan');

        $staffResponse = $this->actingAs(User::factory()->staff()->create())->get($url)->assertOk();
        $staffResponse->assertSee('Đối chiếu khớp toàn bộ lịch sử')
            ->assertSee('Phiếu nhập kho #9001')
            ->assertDontSee(route('admin.stock-imports.show', $importMovement->reference_id))
            ->assertSee(route('admin.orders.show', $order->id))
            ->assertSee(route('admin.orders.show', $returnOrderId).'#xu-ly-hang-hoan');
    }

    public function test_product_page_defaults_to_whole_product_movements_across_colors_sizes_and_trashed_skus(): void
    {
        [$product, $blackColor, $blackVariant] = $this->productWithVariant();
        $blackVariant->forceFill(['size_value' => '40', 'stock_qty' => 2])->save();
        $whiteColor = ProductColor::factory()->for($product, 'product')->create(['color_name' => 'Trắng']);
        $whiteVariant = ProductVariant::factory()->for($whiteColor, 'color')->create([
            'size_value' => '41',
            'stock_qty' => 3,
        ]);

        $blackMovement = $this->movement($blackVariant, [
            'stock_before' => 0,
            'stock_delta' => 2,
            'stock_after' => 2,
            'unit_cost_basis' => '111.2222',
            'average_cost_after' => '111.2222',
            'created_at' => now()->subMinute(),
        ]);
        $whiteMovement = $this->movement($whiteVariant, [
            'stock_before' => 0,
            'stock_delta' => 3,
            'stock_after' => 3,
            'unit_cost_basis' => '222.3333',
            'average_cost_after' => '222.3333',
            'created_at' => now(),
        ]);

        $whiteVariant->delete();
        $whiteColor->delete();

        $staff = User::factory()->staff()->create();
        $response = $this->actingAs($staff)
            ->get(route('admin.products.show', $product))
            ->assertOk();

        $response->assertSee('Biến động kho')
            ->assertSee('Xem biến động kho')
            ->assertSee('#'.$blackMovement->id)
            ->assertSee('#'.$whiteMovement->id)
            ->assertSee('Đen · Size 40')
            ->assertSee('Trắng · Size 41')
            ->assertSee($blackVariant->sku)
            ->assertSee($whiteVariant->sku)
            ->assertSee('Đối chiếu khớp toàn bộ lịch sử')
            ->assertDontSee('111.2222')
            ->assertDontSee('222.3333')
            ->assertDontSee('Giá vốn');

        $colorFiltered = $this->actingAs($staff)
            ->get(route('admin.products.show', ['product' => $product, 'color_id' => $blackColor->id]))
            ->assertOk();

        $colorFiltered->assertViewHas('movements', fn ($paginator): bool => $paginator->total() === 1 && $paginator->items()[0]->id === $blackMovement->id);

        $variantFiltered = $this->actingAs($staff)
            ->get(route('admin.products.show', ['product' => $product, 'color_id' => $whiteColor->id, 'variant_id' => $whiteVariant->id]))
            ->assertOk();

        $variantFiltered->assertViewHas('movements', fn ($paginator): bool => $paginator->total() === 1 && $paginator->items()[0]->id === $whiteMovement->id);

        [, $foreignColor] = $this->productWithVariant('Sản phẩm khác');
        $this->actingAs($staff)
            ->get(route('admin.products.show', ['product' => $product, 'color_id' => $foreignColor->id]))
            ->assertNotFound();
    }

    public function test_whole_product_reconciliation_reports_issues_per_sku_without_merging_balances(): void
    {
        [$product, $color, $okVariant] = $this->productWithVariant();
        $okVariant->forceFill(['size_value' => '39', 'stock_qty' => 2])->save();
        $this->movement($okVariant, [
            'stock_before' => 0,
            'stock_delta' => 2,
            'stock_after' => 2,
        ]);

        $diffVariant = ProductVariant::factory()->for($color, 'color')->create([
            'size_value' => '42',
            'stock_qty' => 5,
        ]);
        $this->movement($diffVariant, [
            'stock_before' => 0,
            'stock_delta' => 2,
            'stock_after' => 2,
        ]);

        $emptyVariant = ProductVariant::factory()->for($color, 'color')->create([
            'size_value' => '43',
            'stock_qty' => 0,
        ]);

        $response = $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.products.show', $product))
            ->assertOk();

        $response->assertSee('OBSERVED_BALANCE_DIFF')
            ->assertSee('HISTORY_INCOMPLETE')
            ->assertSee($diffVariant->sku)
            ->assertSee($emptyVariant->sku)
            ->assertDontSee('Đối chiếu khớp toàn bộ lịch sử');

        $response->assertViewHas('productReconciliationIssues', function (array $issues) use ($okVariant, $diffVariant, $emptyVariant): bool {
            $byId = collect($issues)->keyBy('variant_id');

            return ! $byId->has($okVariant->id)
                && ($byId->get($diffVariant->id)['observed_balance_diff'] ?? false) === true
                && ($byId->get($emptyVariant->id)['history_incomplete'] ?? false) === true;
        });
    }

    public function test_size_filter_matches_across_colors_intersects_with_color_and_handles_empty_combinations(): void
    {
        [$product, $blackColor, $black40] = $this->productWithVariant();
        $black40->forceFill(['size_value' => '40', 'size_numeric' => 40.0])->save();
        $black405 = ProductVariant::factory()->for($blackColor, 'color')->create([
            'size_value' => '40.5',
            'size_numeric' => 40.5,
        ]);

        $whiteColor = ProductColor::factory()->for($product, 'product')->create(['color_name' => 'Trắng']);
        $white40 = ProductVariant::factory()->for($whiteColor, 'color')->create([
            'size_value' => '40',
            'size_numeric' => 40.0,
        ]);
        $white41 = ProductVariant::factory()->for($whiteColor, 'color')->create([
            'size_value' => '41',
            'size_numeric' => 41.0,
        ]);

        $black40Movement = $this->movement($black40, ['created_at' => now()->subMinutes(4)]);
        $white40Movement = $this->movement($white40, ['created_at' => now()->subMinutes(3)]);
        $black405Movement = $this->movement($black405, ['created_at' => now()->subMinutes(2)]);
        $white41Movement = $this->movement($white41, ['created_at' => now()->subMinute()]);

        $staff = User::factory()->staff()->create();

        $size40Response = $this->actingAs($staff)
            ->get(route('admin.products.show', ['product' => $product, 'size' => '40']))
            ->assertOk();

        $size40Response->assertViewHas('historySizes', ['40', '40.5', '41']);
        $size40Response->assertViewHas('movements', function ($paginator) use ($black40Movement, $white40Movement): bool {
            $ids = collect($paginator->items())->pluck('id')->all();

            return $paginator->total() === 2
                && in_array($black40Movement->id, $ids, true)
                && in_array($white40Movement->id, $ids, true);
        });

        $intersectionResponse = $this->actingAs($staff)
            ->get(route('admin.products.show', [
                'product' => $product,
                'color_id' => $blackColor->id,
                'size' => '40',
            ]))
            ->assertOk();

        $intersectionResponse->assertViewHas('movements', fn ($paginator): bool => $paginator->total() === 1
            && $paginator->items()[0]->id === $black40Movement->id);

        $emptyIntersection = $this->actingAs($staff)
            ->get(route('admin.products.show', [
                'product' => $product,
                'color_id' => $blackColor->id,
                'size' => '41',
            ]))
            ->assertOk();

        $emptyIntersection->assertViewHas('movements', fn ($paginator): bool => $paginator->total() === 0)
            ->assertSee('Không có biến động phù hợp với bộ lọc.');

        // Backward-compatible ?variant_id= derives selectedColorId and selectedSize
        $variantParamResponse = $this->actingAs($staff)
            ->get(route('admin.products.show', ['product' => $product, 'variant_id' => $black405->id]))
            ->assertOk();

        $variantParamResponse->assertViewHas('selectedColorId', $blackColor->id)
            ->assertViewHas('selectedSize', '40.5')
            ->assertViewHas('movements', fn ($paginator): bool => $paginator->total() === 1
                && $paginator->items()[0]->id === $black405Movement->id);

        $this->assertNotNull($white41Movement->id);
    }

    public function test_activity_group_filtering_covers_all_groups_and_keeps_order_reserved_out_of_dispatch(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $staff = User::factory()->staff()->create();

        $importMovement = $this->movement($variant, ['action' => InventoryAction::ImportPosted]);
        $dispatchMovement = $this->movement($variant, ['action' => InventoryAction::OrderDispatched]);
        $returnMovement = $this->movement($variant, ['action' => InventoryAction::ShipmentReturnReceived]);
        $writeOffMovement = $this->movement($variant, ['action' => InventoryAction::QuarantineWriteoff]);
        $reservedMovement = $this->movement($variant, ['action' => InventoryAction::OrderReserved]);

        $cases = [
            'import' => [$importMovement->id],
            'dispatch' => [$dispatchMovement->id],
            'return' => [$returnMovement->id],
            'write_off' => [$writeOffMovement->id],
            'reservation' => [$reservedMovement->id],
        ];

        foreach ($cases as $group => $expectedIds) {
            $response = $this->actingAs($staff)
                ->get(route('admin.products.show', ['product' => $product, 'action_group' => $group]))
                ->assertOk();

            $response->assertViewHas('movements', function ($paginator) use ($expectedIds): bool {
                $actualIds = collect($paginator->items())->pluck('id')->all();

                return $paginator->total() === count($expectedIds) && $actualIds === $expectedIds;
            });
        }

        // Empty action_group ("Tất cả") returns all 5 movements
        $allResponse = $this->actingAs($staff)
            ->get(route('admin.products.show', ['product' => $product, 'action_group' => '']))
            ->assertOk();

        $allResponse->assertViewHas('movements', fn ($paginator): bool => $paginator->total() === 5);
    }

    public function test_date_range_is_inclusive_of_end_day_and_rejects_invalid_range(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $staff = User::factory()->staff()->create();
        $tz = config('store.business_timezone');
        $day = CarbonImmutable::parse('2026-10-15', $tz)->startOfDay();

        $insideStart = $this->movement($variant, ['created_at' => $day->setTime(0, 0, 0)->utc()]);
        $insideEnd = $this->movement($variant, ['created_at' => $day->setTime(23, 59, 59)->utc()]);
        $outsideNextDay = $this->movement($variant, ['created_at' => $day->addDay()->setTime(0, 0, 0)->utc()]);

        $validResponse = $this->actingAs($staff)
            ->get(route('admin.products.show', [
                'product' => $product,
                'from' => '2026-10-15',
                'to' => '2026-10-15',
            ]))
            ->assertOk();

        $validResponse->assertViewHas('movements', function ($paginator) use ($insideStart, $insideEnd, $outsideNextDay): bool {
            $ids = collect($paginator->items())->pluck('id')->all();

            return $paginator->total() === 2
                && in_array($insideStart->id, $ids, true)
                && in_array($insideEnd->id, $ids, true)
                && ! in_array($outsideNextDay->id, $ids, true);
        });

        $this->actingAs($staff)
            ->from(route('admin.products.show', $product))
            ->get(route('admin.products.show', [
                'product' => $product,
                'from' => '2026-10-16',
                'to' => '2026-10-15',
            ]))
            ->assertSessionHasErrors(['to']);
    }

    public function test_staff_receives_zero_cost_in_html_and_ajax_and_simplified_toolbar_is_outside_results(): void
    {
        [$product, , $variant] = $this->productWithVariant();
        $variant->forceFill(['stock_qty' => 2, 'average_cost' => '456.7890'])->save();
        $movement = $this->movement($variant, [
            'unit_cost_basis' => '456.7890',
            'average_cost_before' => '0.0000',
            'average_cost_after' => '456.7890',
        ]);

        $staff = User::factory()->staff()->create();
        $url = route('admin.products.show', ['product' => $product, 'size' => $variant->size_value]);

        foreach ([$this->actingAs($staff)->get($url), $this->actingAs($staff)->get($url, ['X-Requested-With' => 'XMLHttpRequest'])] as $response) {
            $response->assertOk()
                ->assertSee('#'.$movement->id)
                ->assertSee('id="inventory-history-toolbar"', false)
                ->assertSee('id="inventory-history-results"', false)
                ->assertDontSee('Lọc thêm')
                ->assertDontSee('movement-reference-type')
                ->assertDontSee('456.7890')
                ->assertDontSee('average_cost')
                ->assertDontSee('unit_cost_basis')
                ->assertDontSee('Đơn vị:');

            $html = $response->getContent();
            $this->assertLessThan(
                strpos($html, 'id="inventory-history-results"'),
                strpos($html, 'id="inventory-history-toolbar"')
            );
        }
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
