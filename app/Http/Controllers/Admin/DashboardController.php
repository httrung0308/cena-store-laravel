<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleSlug;
use App\Enums\StockImportStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StockImport;
use App\Services\ReportService;
use App\Support\BusinessClock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operational triage hub for `/admin` (route `admin.dashboard`).
 * Surfaces current actionable queues with exact counts matching their filtered index destinations.
 */
class DashboardController extends Controller
{
    public function index(Request $request, BusinessClock $clock): View
    {
        $user = $request->user();
        $isAdmin = $user?->hasRole(RoleSlug::Admin) ?? false;
        $timezone = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
        $loadedAt = $clock->now()->setTimezone($timezone);

        // 1. Order status queue counts — each matches OrderController::index(?status=...) 100%
        $rawOrderCounts = Order::query()
            ->whereIn('order_status', [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Packing->value,
                OrderStatus::OnDelivery->value,
            ])
            ->selectRaw('order_status, COUNT(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        $pendingOrdersCount = (int) ($rawOrderCounts[OrderStatus::Pending->value] ?? 0);
        $confirmedOrdersCount = (int) ($rawOrderCounts[OrderStatus::Confirmed->value] ?? 0);
        $packingOrdersCount = (int) ($rawOrderCounts[OrderStatus::Packing->value] ?? 0);
        $onDeliveryOrdersCount = (int) ($rawOrderCounts[OrderStatus::OnDelivery->value] ?? 0);

        // 2. Stock import draft count — matches StockImportController::index(?status=DRAFT) 100%
        $draftImportsCount = StockImport::query()
            ->where('status', StockImportStatus::Draft->value)
            ->count();

        // 3. Out-of-stock products count — matches ProductController::index(?stock=out_of_stock) 100%
        $saleableVariantExists = static function (QueryBuilder $query): void {
            $query->selectRaw('1')
                ->from('product_variants as v')
                ->join('product_colors as c', 'c.id', '=', 'v.product_color_id')
                ->whereColumn('c.product_id', 'products.id')
                ->whereNull('v.deleted_at')
                ->whereNull('c.deleted_at')
                ->where('v.is_active', true)
                ->where('c.is_active', true)
                ->whereColumn('v.stock_qty', '>', 'v.reserved_qty');
        };

        $outOfStockProductsCount = Product::query()
            ->whereNotExists($saleableVariantExists)
            ->count();

        // 4. Low/out-of-stock active variants (SKU/size level, sellable <= 5) for direct "+ Nhập kho" action
        $lowStockThreshold = ReportService::LOW_STOCK_THRESHOLD;
        $lowVariantsBaseQuery = DB::table('product_variants')
            ->join('product_colors', 'product_colors.id', '=', 'product_variants.product_color_id')
            ->join('products', 'products.id', '=', 'product_colors.product_id')
            ->where('product_variants.is_active', true)
            ->whereNull('product_variants.deleted_at')
            ->where('product_colors.is_active', true)
            ->whereNull('product_colors.deleted_at')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->whereRaw('product_variants.stock_qty - product_variants.reserved_qty <= ?', [$lowStockThreshold]);

        $lowStockSummary = (clone $lowVariantsBaseQuery)
            ->selectRaw('COUNT(*) as total_low, COALESCE(SUM(CASE WHEN product_variants.stock_qty - product_variants.reserved_qty <= 0 THEN 1 ELSE 0 END), 0) as depleted_count')
            ->first();

        $lowStockVariantsCount = (int) ($lowStockSummary->total_low ?? 0);
        $depletedVariantsCount = (int) ($lowStockSummary->depleted_count ?? 0);

        $lowStockVariants = (clone $lowVariantsBaseQuery)
            ->selectRaw('product_variants.id as variant_id, products.id as product_id, products.name as product, product_colors.color_name as color, product_variants.size_value as size, product_variants.sku as sku, (product_variants.stock_qty - product_variants.reserved_qty) as sellable')
            ->orderBy('sellable')
            ->orderBy('products.name')
            ->orderBy('product_variants.id')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'variant_id' => (int) $row->variant_id,
                'product_id' => (int) $row->product_id,
                'product' => (string) $row->product,
                'color' => (string) $row->color,
                'size' => (string) $row->size,
                'sku' => (string) $row->sku,
                'sellable' => (int) $row->sellable,
            ])
            ->all();

        // 5. Priority orders queue (PENDING -> CONFIRMED -> PACKING, oldest waiting first)
        $actionableOrders = Order::query()
            ->with('items')
            ->whereIn('order_status', [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Packing->value,
            ])
            ->orderByRaw("CASE order_status WHEN 'PENDING' THEN 1 WHEN 'CONFIRMED' THEN 2 WHEN 'PACKING' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(8)
            ->get();

        // 6. Draft stock imports list for quick posting/completion
        $draftImports = StockImport::query()
            ->where('status', StockImportStatus::Draft->value)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // 7. Admin-only: refund requests awaiting approval & orders with REFUND_PENDING payment status
        $pendingRefundOrdersCount = 0;
        $requestedRefundsCount = 0;
        $pendingRefunds = collect();

        if ($isAdmin) {
            // Matches OrderController::index(?payment_status=REFUND_PENDING) 100%
            $pendingRefundOrdersCount = Order::query()
                ->where('payment_status', OrderPaymentStatus::RefundPending->value)
                ->count();

            $requestedRefundsCount = Refund::query()
                ->where('status', RefundStatus::Requested->value)
                ->count();

            $pendingRefunds = Refund::query()
                ->with(['order', 'requestedBy'])
                ->where('status', RefundStatus::Requested->value)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(5)
                ->get();
        }

        $totalActionableCount = $pendingOrdersCount
            + $confirmedOrdersCount
            + $packingOrdersCount
            + $draftImportsCount
            + ($isAdmin ? $requestedRefundsCount : 0);

        return view('admin.dashboard', [
            'isAdmin' => $isAdmin,
            'timezone' => $timezone,
            'loadedAt' => $loadedAt,
            'pendingOrdersCount' => $pendingOrdersCount,
            'confirmedOrdersCount' => $confirmedOrdersCount,
            'packingOrdersCount' => $packingOrdersCount,
            'onDeliveryOrdersCount' => $onDeliveryOrdersCount,
            'draftImportsCount' => $draftImportsCount,
            'outOfStockProductsCount' => $outOfStockProductsCount,
            'lowStockThreshold' => $lowStockThreshold,
            'lowStockVariantsCount' => $lowStockVariantsCount,
            'depletedVariantsCount' => $depletedVariantsCount,
            'lowStockVariants' => $lowStockVariants,
            'actionableOrders' => $actionableOrders,
            'draftImports' => $draftImports,
            'pendingRefundOrdersCount' => $pendingRefundOrdersCount,
            'requestedRefundsCount' => $requestedRefundsCount,
            'pendingRefunds' => $pendingRefunds,
            'totalActionableCount' => $totalActionableCount,
        ]);
    }
}
