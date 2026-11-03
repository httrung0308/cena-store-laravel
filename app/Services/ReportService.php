<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read-only figures for the back-office dashboard. Money comes from the sales ledger (signed deltas summed
 * algebraically, so reversals reduce the totals); revenue is only recognised there when an order is delivered.
 * Nothing here writes to stock or the ledger.
 */
final class ReportService
{
    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(private readonly BusinessClock $clock) {}

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    public function range(string $firstDay, string $lastDay): array
    {
        return $this->clock->vietnameseDateRange($firstDay, $lastDay);
    }

    public function today(): string
    {
        return $this->clock->now()->setTimezone($this->timezone())->format('Y-m-d');
    }

    /** @return array{revenue: int, merchandise: int, shipping: int, cogs: int, expense: int, profit: int} */
    public function money(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sums = DB::table('sales_ledger_entries')
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw('COALESCE(SUM(merchandise_revenue_delta), 0) AS merchandise, COALESCE(SUM(shipping_revenue_delta), 0) AS shipping, COALESCE(SUM(cogs_delta), 0) AS cogs, COALESCE(SUM(expense_delta), 0) AS expense')
            ->first();
        $merchandise = (int) $sums->merchandise;
        $shipping = (int) $sums->shipping;
        $cogs = (int) $sums->cogs;
        $expense = (int) $sums->expense;

        return [
            'revenue' => $merchandise + $shipping,
            'merchandise' => $merchandise,
            'shipping' => $shipping,
            'cogs' => $cogs,
            'expense' => $expense,
            'profit' => $merchandise + $shipping - $cogs - $expense,
        ];
    }

    /**
     * Revenue and profit per Vietnamese calendar day, including days without sales.
     *
     * @return list<array{day: string, revenue: int, profit: int}>
     */
    public function daily(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $offset = $start->setTimezone($this->timezone())->format('P');
        $rows = DB::table('sales_ledger_entries')
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw("DATE(CONVERT_TZ(occurred_at, '+00:00', ?)) AS day", [$offset])
            ->selectRaw('SUM(merchandise_revenue_delta + shipping_revenue_delta) AS revenue')
            ->selectRaw('SUM(merchandise_revenue_delta + shipping_revenue_delta - cogs_delta - expense_delta) AS profit')
            ->groupBy('day')->get()->keyBy('day');

        $days = [];
        for ($day = $start->setTimezone($this->timezone()); $day->utc()->lessThan($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days[] = ['day' => $key, 'revenue' => (int) ($rows[$key]->revenue ?? 0), 'profit' => (int) ($rows[$key]->profit ?? 0)];
        }

        return $days;
    }

    /**
     * Products ranked by pairs in orders delivered within the range.
     *
     * @return list<array{product_id: int, name: string, pairs: int}>
     */
    public function bestSellers(CarbonImmutable $start, CarbonImmutable $end, int $limit = 5): array
    {
        return DB::table('order_item_units')
            ->join('order_items', 'order_items.id', '=', 'order_item_units.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->join('product_colors', 'product_colors.id', '=', 'product_variants.product_color_id')
            ->join('products', 'products.id', '=', 'product_colors.product_id')
            ->where('orders.order_status', OrderStatus::Delivered->value)
            ->where('orders.delivered_at', '>=', $start)->where('orders.delivered_at', '<', $end)
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id AS product_id, products.name AS name, COUNT(*) AS pairs')
            ->orderByDesc('pairs')->orderBy('products.id')
            ->limit($limit)->get()
            ->map(fn (object $row): array => ['product_id' => (int) $row->product_id, 'name' => (string) $row->name, 'pairs' => (int) $row->pairs])
            ->all();
    }

    /** @return array<string, int> Orders placed within the range, by status value. */
    public function orderCounts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = DB::table('orders')->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->groupBy('order_status')->selectRaw('order_status, COUNT(*) AS total')->pluck('total', 'order_status');

        return collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])->all();
    }

    /**
     * Sellable stock (on hand minus reserved; quarantined pairs are a separate bucket and never count).
     *
     * @return array{sellable: int, variants: int, low: list<array{product: string, color: string, size: string, sku: string, sellable: int}>}
     */
    public function inventory(int $threshold = self::LOW_STOCK_THRESHOLD, int $limit = 10): array
    {
        $variants = DB::table('product_variants')
            ->join('product_colors', 'product_colors.id', '=', 'product_variants.product_color_id')
            ->join('products', 'products.id', '=', 'product_colors.product_id')
            ->where('product_variants.is_active', true)->whereNull('product_variants.deleted_at')
            ->where('product_colors.is_active', true)->whereNull('product_colors.deleted_at')
            ->where('products.is_active', true)->whereNull('products.deleted_at');
        $totals = (clone $variants)->selectRaw('COALESCE(SUM(product_variants.stock_qty - product_variants.reserved_qty), 0) AS sellable, COUNT(*) AS variants')->first();
        $low = (clone $variants)
            ->whereRaw('product_variants.stock_qty - product_variants.reserved_qty <= ?', [$threshold])
            ->selectRaw('products.name AS product, product_colors.color_name AS color, product_variants.size_value AS size, product_variants.sku AS sku, product_variants.stock_qty - product_variants.reserved_qty AS sellable')
            ->orderBy('sellable')->orderBy('products.name')->orderBy('product_variants.id')
            ->limit($limit)->get()
            ->map(fn (object $row): array => ['product' => (string) $row->product, 'color' => (string) $row->color, 'size' => (string) $row->size, 'sku' => (string) $row->sku, 'sellable' => (int) $row->sellable])
            ->all();

        return ['sellable' => (int) $totals->sellable, 'variants' => (int) $totals->variants, 'low' => $low];
    }

    private function timezone(): string
    {
        return (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
    }
}
