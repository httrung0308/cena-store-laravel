<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only dashboard. Admin sees revenue, COGS and profit from the sales ledger; staff only sees
 * operational figures (orders, pairs sold, sellable stock).
 */
class ReportController extends Controller
{
    private const MAX_DAYS = 366;

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $today = $this->reports->today();
        $defaultFrom = CarbonImmutable::parse($today)->subDays(29)->format('Y-m-d');
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $filters['from'] ?? $defaultFrom;
        $to = $filters['to'] ?? $today;
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= self::MAX_DAYS) {
            $from = $defaultFrom;
            $to = $today;
        }
        ['start' => $start, 'end' => $end] = $this->reports->range($from, $to);
        $isAdmin = $request->user()->hasRole(RoleSlug::Admin);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'isAdmin' => $isAdmin,
            'money' => $isAdmin ? $this->reports->money($start, $end) : null,
            'daily' => $isAdmin ? $this->reports->daily($start, $end) : [],
            'orderCounts' => $this->reports->orderCounts($start, $end),
            'bestSellers' => $this->reports->bestSellers($start, $end),
            'inventory' => $this->reports->inventory(),
            'lowStockThreshold' => ReportService::LOW_STOCK_THRESHOLD,
        ]);
    }
}
