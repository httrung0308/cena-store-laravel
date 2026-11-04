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

    private const PRESET_DAYS = [7, 30, 90];

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
            'presets' => $this->presets($today),
            'isAdmin' => $isAdmin,
            'money' => $isAdmin ? $this->reports->money($start, $end) : null,
            'previousMoney' => $isAdmin ? $this->previousMoney($from, $to) : null,
            'daily' => $isAdmin ? $this->reports->daily($start, $end) : [],
            'operations' => $this->reports->dailyOperations($start, $end),
            'orderCounts' => $this->reports->orderCounts($start, $end),
            'bestSellers' => $this->reports->bestSellers($start, $end, 8),
            'inventory' => $this->reports->inventory(),
            'articleFeedback' => $isAdmin ? $this->reports->articleFeedback($start, $end) : null,
            'lowStockThreshold' => ReportService::LOW_STOCK_THRESHOLD,
        ]);
    }

    /**
     * Money for the period of the same length right before the selected one, to show the trend.
     *
     * @return array{revenue: int, merchandise: int, shipping: int, cogs: int, expense: int, profit: int}
     */
    private function previousMoney(string $from, string $to): array
    {
        $first = CarbonImmutable::parse($from);
        $length = $first->diffInDays(CarbonImmutable::parse($to)) + 1;
        ['start' => $start, 'end' => $end] = $this->reports->range($first->subDays($length)->format('Y-m-d'), $first->subDay()->format('Y-m-d'));

        return $this->reports->money($start, $end);
    }

    /** @return list<array{label: string, from: string, to: string}> */
    private function presets(string $today): array
    {
        $last = CarbonImmutable::parse($today);
        $presets = array_map(fn (int $days): array => ['label' => $days.' ngày', 'from' => $last->subDays($days - 1)->format('Y-m-d'), 'to' => $today], self::PRESET_DAYS);
        $presets[] = ['label' => 'Tháng này', 'from' => $last->startOfMonth()->format('Y-m-d'), 'to' => $today];

        return $presets;
    }
}
