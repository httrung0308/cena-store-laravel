<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

        $validator = Validator::make(
            $request->all(),
            [
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            ],
            [
                'from.date_format' => 'Ngày bắt đầu không đúng định dạng năm-tháng-ngày (YYYY-MM-DD).',
                'to.date_format' => 'Ngày kết thúc không đúng định dạng năm-tháng-ngày (YYYY-MM-DD).',
                'to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            ]
        );

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($request, $defaultFrom, $today): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $request->filled('from') ? (string) $request->input('from') : $defaultFrom;
            $to = $request->filled('to') ? (string) $request->input('to') : $today;

            $fromDate = CarbonImmutable::parse($from);
            $toDate = CarbonImmutable::parse($to);

            if ($fromDate->greaterThan($toDate)) {
                $validator->errors()->add('to', 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.');

                return;
            }

            if ($fromDate->diffInDays($toDate) >= self::MAX_DAYS) {
                $validator->errors()->add(
                    'to',
                    'Khoảng thời gian báo cáo tối đa là '.self::MAX_DAYS.' ngày. Vui lòng thu hẹp khoảng ngày cần xem.'
                );
            }
        });

        if ($validator->fails()) {
            throw (new ValidationException($validator))
                ->redirectTo(route('admin.reports.index'));
        }

        $filters = $validator->validated();
        $from = $filters['from'] ?? $defaultFrom;
        $to = $filters['to'] ?? $today;
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
