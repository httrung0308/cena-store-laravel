<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    /** @var list<string> */
    private const CALENDAR_PERIODS = ['week', 'month', 'quarter', 'year'];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $today = $this->reports->today();
        $todayDate = CarbonImmutable::parse($today);
        $defaultRange = $this->calendarRange('month', $todayDate, $todayDate);
        $isAdmin = $request->user()->hasRole(RoleSlug::Admin);
        $allowedTabs = $isAdmin
            ? ['finance', 'operations', 'inventory', 'feedback']
            : ['operations', 'inventory'];
        $requestedTab = (string) $request->input('tab', '');
        $activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : $allowedTabs[0];

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

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($request, $defaultRange, $today): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $request->filled('from') && ! $request->filled('to')) {
                return;
            }

            $from = $request->filled('from') ? (string) $request->input('from') : $defaultRange['from'];
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
            $redirectParams = $activeTab !== $allowedTabs[0] ? ['tab' => $activeTab] : [];

            throw (new ValidationException($validator))
                ->redirectTo(route('admin.reports.index', $redirectParams));
        }

        $filters = $validator->validated();
        $hasFrom = isset($filters['from']) && $filters['from'] !== '';
        $hasTo = isset($filters['to']) && $filters['to'] !== '';
        $requestedPeriod = (string) $request->input('period', '');

        if (! $hasFrom && ! $hasTo) {
            $period = in_array($requestedPeriod, self::CALENDAR_PERIODS, true) ? $requestedPeriod : 'month';
            $range = $this->calendarRange($period, $todayDate, $todayDate) ?? $defaultRange;
            $from = $range['from'];
            $to = $range['to'];
        } else {
            $from = $hasFrom ? $filters['from'] : $defaultRange['from'];
            $to = $hasTo ? $filters['to'] : $today;

            if (in_array($requestedPeriod, self::CALENDAR_PERIODS, true)) {
                $candidate = $this->calendarRange($requestedPeriod, CarbonImmutable::parse($from), $todayDate);
                if ($candidate !== null && $candidate['from'] === $from && $candidate['to'] === $to) {
                    $period = $requestedPeriod;
                    $range = $candidate;
                } else {
                    $period = 'custom';
                    $range = null;
                }
            } else {
                $period = 'custom';
                $range = null;
            }
        }

        $prevPeriod = null;
        $nextPeriod = null;
        if ($range !== null) {
            $prevAnchor = $this->shiftPeriod($period, $range['start'], -1);
            $nextAnchor = $this->shiftPeriod($period, $range['start'], 1);
            $prevRange = $this->calendarRange($period, $prevAnchor, $todayDate);
            $nextRange = $this->calendarRange($period, $nextAnchor, $todayDate);
            $prevPeriod = $prevRange !== null ? ['from' => $prevRange['from'], 'to' => $prevRange['to']] : null;
            $nextPeriod = $nextRange !== null ? ['from' => $nextRange['from'], 'to' => $nextRange['to']] : null;
        }

        ['start' => $start, 'end' => $end] = $this->reports->range($from, $to);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periodLabel' => $this->periodLabel($period, CarbonImmutable::parse($from)),
            'periodOptions' => $this->periodOptions($todayDate),
            'prevPeriod' => $prevPeriod,
            'nextPeriod' => $nextPeriod,
            'activeTab' => $activeTab,
            'allowedTabs' => $allowedTabs,
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

    /**
     * Compute a calendar period containing the anchor date, clamped to today when ongoing.
     * Returns null for future periods (start > today).
     *
     * @return array{from: string, to: string, start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function calendarRange(string $period, CarbonImmutable $anchor, CarbonImmutable $todayDate): ?array
    {
        [$periodStart, $periodEnd] = match ($period) {
            'week' => [$anchor->startOfWeek(CarbonInterface::MONDAY), $anchor->endOfWeek(CarbonInterface::SUNDAY)],
            'month' => [$anchor->startOfMonth(), $anchor->endOfMonth()],
            'quarter' => [$anchor->startOfQuarter(), $anchor->endOfQuarter()],
            'year' => [$anchor->startOfYear(), $anchor->endOfYear()],
        };

        if ($periodStart->greaterThan($todayDate)) {
            return null;
        }

        $effectiveEnd = $periodEnd->greaterThan($todayDate) ? $todayDate : $periodEnd;

        return [
            'from' => $periodStart->format('Y-m-d'),
            'to' => $effectiveEnd->format('Y-m-d'),
            'start' => $periodStart,
            'end' => $periodEnd,
        ];
    }

    private function shiftPeriod(string $period, CarbonImmutable $periodStart, int $delta): CarbonImmutable
    {
        return match ($period) {
            'week' => $delta < 0 ? $periodStart->subWeek() : $periodStart->addWeek(),
            'month' => $delta < 0 ? $periodStart->subMonthNoOverflow() : $periodStart->addMonthNoOverflow(),
            'quarter' => $delta < 0 ? $periodStart->subQuarterNoOverflow() : $periodStart->addQuarterNoOverflow(),
            'year' => $delta < 0 ? $periodStart->subYearNoOverflow() : $periodStart->addYearNoOverflow(),
        };
    }

    /** @return list<array{key: string, label: string, from: string, to: string}> */
    private function periodOptions(CarbonImmutable $todayDate): array
    {
        $labels = [
            'week' => 'Tuần',
            'month' => 'Tháng',
            'quarter' => 'Quý',
            'year' => 'Năm',
        ];

        $options = [];
        foreach ($labels as $key => $label) {
            $range = $this->calendarRange($key, $todayDate, $todayDate);
            $options[] = [
                'key' => $key,
                'label' => $label,
                'from' => $range['from'],
                'to' => $range['to'],
            ];
        }

        return $options;
    }

    private function periodLabel(string $period, CarbonImmutable $start): string
    {
        return match ($period) {
            'week' => 'Tuần '.$start->isoWeek().'/'.$start->isoWeekYear(),
            'month' => 'Tháng '.$start->format('m/Y'),
            'quarter' => 'Quý '.$start->quarter.'/'.$start->format('Y'),
            'year' => 'Năm '.$start->format('Y'),
            default => 'Khoảng tùy chọn',
        };
    }
}
