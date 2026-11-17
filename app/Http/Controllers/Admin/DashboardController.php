<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderPaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleSlug;
use App\Enums\StockImportStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StockImport;
use App\Services\OperationsQueueService;
use App\Services\ReportService;
use App\Support\BusinessClock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only Overview hub for `/admin` (route `admin.dashboard`).
 * Surfaces current-month revenue, 4 main KPIs, Admin approvals, prioritized work items, and low-stock variants.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly OperationsQueueService $queues) {}

    public function index(Request $request, BusinessClock $clock, ReportService $reportService): View
    {
        $user = $request->user();
        $isAdmin = $user?->hasRole(RoleSlug::Admin) ?? false;
        $timezone = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
        $now = $clock->now();
        $loadedAt = $now->setTimezone($timezone);

        // 1. Current-month revenue from ReportService ([firstDayOfMonth..today], capped at viewing time)
        $firstDayOfMonth = $loadedAt->startOfMonth()->format('Y-m-d');
        $today = $loadedAt->format('Y-m-d');
        ['start' => $monthStart, 'end' => $monthEnd] = $reportService->range($firstDayOfMonth, $today);
        if ($monthEnd->greaterThan($now)) {
            $monthEnd = $now;
        }
        $currentMonthRevenue = $reportService->money($monthStart, $monthEnd)['revenue'];

        // 2. Unified actionable queue counts & top 6 prioritized items from OperationsQueueService
        $queueCounts = $this->queues->counts($isAdmin);
        $topWorkItems = $this->queues
            ->paginateActive($isAdmin, OperationsQueueService::TYPE_ALL, null, null, 6, 1)
            ->items();

        $pendingOrdersCount = $queueCounts[OperationsQueueService::TYPE_ORDER_PENDING];
        $confirmedOrdersCount = $queueCounts[OperationsQueueService::TYPE_ORDER_CONFIRMED];
        $packingOrdersCount = $queueCounts[OperationsQueueService::TYPE_ORDER_PACKING];

        // 3. Stock import draft count — matches StockImportController::index(?status=DRAFT)
        $draftImportsCount = StockImport::query()
            ->where('status', StockImportStatus::Draft->value)
            ->count();

        // 4. Out-of-stock products count — matches ProductController::index(?stock=out_of_stock)
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

        // 5. Low/out-of-stock active variants (SKU/size level, sellable <= 5) for direct "+ Nhập kho" action
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

        // 6. Admin approvals: refund requests & write-off proposals
        $requestedRefundsCount = $queueCounts[OperationsQueueService::TYPE_REFUND_APPROVAL];
        $writeOffApprovalCount = $queueCounts[OperationsQueueService::TYPE_WRITE_OFF_APPROVAL];

        $pendingRefundOrdersCount = $isAdmin
            ? Order::query()->where('payment_status', OrderPaymentStatus::RefundPending->value)->count()
            : 0;

        $pendingRefunds = ($isAdmin && $requestedRefundsCount > 0)
            ? Refund::query()
                ->with(['order', 'requestedBy'])
                ->where('status', RefundStatus::Requested->value)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(5)
                ->get()
            : collect();

        $pendingWriteOffs = ($isAdmin && $writeOffApprovalCount > 0)
            ? $this->queues->paginateActive(true, OperationsQueueService::TYPE_WRITE_OFF_APPROVAL, null, null, 5, 1)->items()
            : [];

        $totalActionableCount = $queueCounts['total'] + $draftImportsCount;

        return view('admin.dashboard', [
            'isAdmin' => $isAdmin,
            'timezone' => $timezone,
            'loadedAt' => $loadedAt,
            'currentMonthRevenue' => $currentMonthRevenue,
            'queueCounts' => $queueCounts,
            'topWorkItems' => $topWorkItems,
            'pendingOrdersCount' => $pendingOrdersCount,
            'confirmedOrdersCount' => $confirmedOrdersCount,
            'packingOrdersCount' => $packingOrdersCount,
            'draftImportsCount' => $draftImportsCount,
            'outOfStockProductsCount' => $outOfStockProductsCount,
            'lowStockThreshold' => $lowStockThreshold,
            'lowStockVariantsCount' => $lowStockVariantsCount,
            'depletedVariantsCount' => $depletedVariantsCount,
            'lowStockVariants' => $lowStockVariants,
            'pendingRefundOrdersCount' => $pendingRefundOrdersCount,
            'requestedRefundsCount' => $requestedRefundsCount,
            'writeOffApprovalCount' => $writeOffApprovalCount,
            'pendingRefunds' => $pendingRefunds,
            'pendingWriteOffs' => $pendingWriteOffs,
            'totalActionableCount' => $totalActionableCount,
        ]);
    }
}
