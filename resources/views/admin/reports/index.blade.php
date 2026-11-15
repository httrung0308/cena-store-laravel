@extends('layouts.admin')
@section('title', 'Báo Cáo & Thống Kê')
@section('eyebrow', 'QUẢN TRỊ BÁO CÁO')

@php
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $day = fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m');
    $fullDate = fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m/Y');
    $percent = fn (float $value): string => number_format($value, 1, ',', '.').'%';
    /** Change against the previous period of the same length; null when there is nothing to compare with. */
    $change = fn (int $current, int $previous): ?float => $previous === 0 ? null : ($current - $previous) / abs($previous) * 100;

    $topPairs = max([1, ...array_column($bestSellers, 'pairs')]);
    $pairsSold = array_sum(array_column($operations, 'pairs'));
    $ordersPlaced = array_sum($orderCounts);
    $rangeDays = count($operations);
    $weekly = $rangeDays > 31;
    $periodWord = $weekly ? 'tuần' : 'ngày';

    $fromDateObj = \Carbon\CarbonImmutable::parse($from);
    $toDateObj = \Carbon\CarbonImmutable::parse($to);
    $fromLabel = $fromDateObj->format('d/m/Y');
    $toLabel = $toDateObj->format('d/m/Y');
    $prevLength = max(1, $rangeDays);
    $prevFromLabel = $fromDateObj->subDays($prevLength)->format('d/m/Y');
    $prevToLabel = $fromDateObj->subDay()->format('d/m/Y');

    $legend = fn (string $color, string $label): string => '<span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: '.$color.'"></span>'.e($label).'</span>';

    $hasOrdersSpark = $rangeDays > 1 && collect($operations)->contains(fn (array $row): bool => $row['orders'] !== 0);
    $hasPairsSpark = $rangeDays > 1 && collect($operations)->contains(fn (array $row): bool => $row['pairs'] !== 0);
    $hasOpsSparkRow = $hasOrdersSpark || $hasPairsSpark;

    /*
     * Orders heatmap: rows are weekdays (Monday first), columns are weeks; cells outside the range stay empty.
     * Five steps of one blue hue, scaled to the busiest day of the range.
     */
    $weekdayLabels = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    $weekdayFullNames = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ Nhật'];
    $heatPeak = max([1, ...array_column($operations, 'orders')]);
    $heatSteps = ['rgba(79, 70, 229, 0.08)', 'rgba(79, 70, 229, 0.2)', 'rgba(79, 70, 229, 0.4)', 'rgba(79, 70, 229, 0.65)', 'rgba(79, 70, 229, 0.85)', 'rgba(79, 70, 229, 1)'];
    $heatLead = $operations === [] ? 0 : \Carbon\CarbonImmutable::parse($operations[0]['day'])->dayOfWeekIso - 1;
    $heatWeeks = (int) ceil(($heatLead + count($operations)) / 7);
    $heatCells = array_fill(0, 7, array_fill(0, $heatWeeks, null));
    $defaultHeatSelection = null;
    foreach ($operations as $index => $row) {
        $position = $heatLead + $index;
        $weekdayIdx = $position % 7;
        $enrichedRow = $row + [
            'formatted' => $day($row['day']),
            'fullDate' => $fullDate($row['day']),
            'weekdayShort' => $weekdayLabels[$weekdayIdx],
            'weekdayFull' => $weekdayFullNames[$weekdayIdx],
        ];
        $heatCells[$weekdayIdx][intdiv($position, 7)] = $enrichedRow;
        if ($defaultHeatSelection === null || $enrichedRow['orders'] >= $defaultHeatSelection['orders']) {
            $defaultHeatSelection = $enrichedRow;
        }
    }
    $heatColor = fn (int $orders): string => $heatSteps[$orders === 0 ? 0 : min(5, (int) ceil($orders / $heatPeak * 5))];
@endphp

@section('actions')
    <a href="{{ route('admin.orders.index') }}" class="btn-pill">Danh sách đơn hàng</a>
    <a href="{{ route('admin.stock-imports.create') }}" class="btn-pill-dark">+ Tạo phiếu nhập hàng</a>
@endsection

@section('content')
    <div class="flex flex-col gap-6"
         x-data="reportCharts(@js($isAdmin ? 'finance' : 'operations'), @js($isAdmin ? ['finance', 'operations', 'inventory', 'feedback'] : ['operations', 'inventory']))">
        <script type="application/json" x-ref="data">@json(['daily' => $daily, 'operations' => $operations, 'feedback' => $articleFeedback['articles'] ?? []])</script>

        {{-- Thanh điều khiển kỳ báo cáo & chuyển tab --}}
        <div class="admin-card !p-4 sm:!p-5 flex flex-col gap-3.5">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-col lg:flex-row lg:items-end justify-between gap-3.5" aria-label="Lọc theo ngày">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="report-from" class="admin-label text-muted !mb-1">Từ ngày</label>
                        <input id="report-from" type="date" name="from" value="{{ old('from', $from) }}"
                               @class(['input bg-surface text-main !py-1.5 text-xs sm:text-sm', 'border-error focus:border-error focus:ring-error/20' => $errors->has('from'), 'border-border' => ! $errors->has('from')])>
                    </div>
                    <div>
                        <label for="report-to" class="admin-label text-muted !mb-1">Đến ngày</label>
                        <input id="report-to" type="date" name="to" value="{{ old('to', $to) }}"
                               @class(['input bg-surface text-main !py-1.5 text-xs sm:text-sm', 'border-error focus:border-error focus:ring-error/20' => $errors->has('to'), 'border-border' => ! $errors->has('to')])>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm">
                        Xem báo cáo
                    </button>
                </div>

                <nav class="flex flex-wrap items-center gap-1.5" aria-label="Khoảng ngày nhanh">
                    @foreach ($presets as $preset)
                        @php $isActivePreset = $preset['from'] === $from && $preset['to'] === $to && ! $errors->any(); @endphp
                        <a href="{{ route('admin.reports.index', ['from' => $preset['from'], 'to' => $preset['to']]) }}"
                           @class([
                               'px-3 py-1.5 text-xs font-bold rounded-xl border transition-colors',
                               '!border-accent !bg-accent !text-accent-text shadow-sm' => $isActivePreset,
                               'border-border bg-surface-card text-muted hover:bg-surface hover:text-main' => ! $isActivePreset,
                           ])
                           @if ($isActivePreset) aria-current="true" @endif>{{ $preset['label'] }}</a>
                    @endforeach
                </nav>
            </form>

            @if ($errors->has('from') || $errors->has('to'))
                <div role="alert" class="rounded-xl border border-error/30 bg-error-bg px-3.5 py-2.5 text-xs font-semibold text-error-text flex flex-wrap items-center justify-between gap-2">
                    <span>Khoảng ngày không hợp lệ ({{ $errors->first('from') ?: $errors->first('to') }}). Đang hiển thị kỳ {{ $fromLabel }} – {{ $toLabel }}.</span>
                    <a href="{{ route('admin.reports.index') }}" class="underline font-bold hover:opacity-80">Đặt lại 30 ngày gần nhất</a>
                </div>
            @endif

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pt-3 border-t border-border/70 text-xs">
                <div class="flex flex-wrap items-center gap-1.5 text-muted">
                    <span class="font-semibold text-main">Kỳ: {{ $fromLabel }} – {{ $toLabel }} · {{ $rangeDays }} ngày</span>
                    <x-admin.tooltip
                        text="Số liệu tính theo giờ Việt Nam (GMT+7). Khoảng trên 31 ngày tự động gộp theo tuần (T2–CN)."
                        label="Quy ước kỳ báo cáo"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="Chuyển vùng phân tích báo cáo">
                    @if ($isAdmin)
                        <button type="button"
                                role="tab"
                                id="tab-finance"
                                data-report-tab="finance"
                                aria-controls="vung-tai-chinh"
                                :aria-selected="activeTab === 'finance' ? 'true' : 'false'"
                                :tabindex="activeTab === 'finance' ? 0 : -1"
                                @click="setTab('finance')"
                                @keydown="handleTabKeydown($event, 'finance')"
                                :class="activeTab === 'finance' ? 'border-accent bg-accent text-accent-text shadow-sm' : 'border-border bg-surface text-main hover:border-accent hover:text-accent'"
                                class="px-3 py-1.5 rounded-xl border font-bold transition-colors cursor-pointer">
                            Tài chính
                        </button>
                    @endif
                    <button type="button"
                            role="tab"
                            id="tab-operations"
                            data-report-tab="operations"
                            aria-controls="vung-van-hanh"
                            :aria-selected="activeTab === 'operations' ? 'true' : 'false'"
                            :tabindex="activeTab === 'operations' ? 0 : -1"
                            @click="setTab('operations')"
                            @keydown="handleTabKeydown($event, 'operations')"
                            :class="activeTab === 'operations' ? 'border-accent bg-accent text-accent-text shadow-sm' : 'border-border bg-surface text-main hover:border-accent hover:text-accent'"
                            class="px-3 py-1.5 rounded-xl border font-bold transition-colors cursor-pointer">
                        Vận hành &amp; Đơn hàng
                    </button>
                    <button type="button"
                            role="tab"
                            id="tab-inventory"
                            data-report-tab="inventory"
                            aria-controls="vung-ton-kho"
                            :aria-selected="activeTab === 'inventory' ? 'true' : 'false'"
                            :tabindex="activeTab === 'inventory' ? 0 : -1"
                            @click="setTab('inventory')"
                            @keydown="handleTabKeydown($event, 'inventory')"
                            :class="activeTab === 'inventory' ? 'border-accent bg-accent text-accent-text shadow-sm' : 'border-border bg-surface text-main hover:border-accent hover:text-accent'"
                            class="px-3 py-1.5 rounded-xl border font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                        <span>Tồn kho hiện tại</span>
                        @if ($inventory['low'] !== [])
                            <span class="inline-flex items-center justify-center rounded-full bg-error-bg text-error-text px-1.5 py-0.2 text-[10px] font-bold">{{ count($inventory['low']) }}</span>
                        @endif
                    </button>
                    @if ($isAdmin)
                        <button type="button"
                                role="tab"
                                id="tab-feedback"
                                data-report-tab="feedback"
                                aria-controls="vung-bai-viet"
                                :aria-selected="activeTab === 'feedback' ? 'true' : 'false'"
                                :tabindex="activeTab === 'feedback' ? 0 : -1"
                                @click="setTab('feedback')"
                                @keydown="handleTabKeydown($event, 'feedback')"
                                :class="activeTab === 'feedback' ? 'border-accent bg-accent text-accent-text shadow-sm' : 'border-border bg-surface text-main hover:border-accent hover:text-accent'"
                                class="px-3 py-1.5 rounded-xl border font-bold transition-colors cursor-pointer">
                            Đánh giá bài viết
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- TAB 1: TÀI CHÍNH (ADMIN) --}}
        @if ($isAdmin)
            @php
                $margin = $money['revenue'] > 0 ? $money['profit'] / $money['revenue'] * 100 : null;
                $hasFinanceActivity = collect($daily)->contains(
                    fn (array $row): bool => $row['revenue'] !== 0 || $row['cogs'] !== 0 || ($row['expense'] ?? 0) !== 0 || $row['profit'] !== 0
                );
                $cards = [
                    [
                        'label' => 'Doanh thu',
                        'spark' => 'revenue',
                        'value' => $money['revenue'],
                        'previous' => $previousMoney['revenue'],
                        'higherIsBetter' => true,
                        'note' => $money['shipping'] > 0 ? 'Gồm '.$vnd($money['shipping']).' phí ship' : null,
                        'tooltip' => 'Tổng tiền hàng và phí ship của các đơn giao thành công trong kỳ.',
                        'hasSpark' => $rangeDays > 1 && collect($daily)->contains(fn (array $r): bool => $r['revenue'] !== 0),
                    ],
                    [
                        'label' => 'Giá vốn',
                        'spark' => 'cogs',
                        'value' => $money['cogs'],
                        'previous' => $previousMoney['cogs'],
                        'higherIsBetter' => null,
                        'note' => null,
                        'tooltip' => 'Giá vốn nhập kho của số giày đã giao thành công trong kỳ.',
                        'hasSpark' => $rangeDays > 1 && collect($daily)->contains(fn (array $r): bool => $r['cogs'] !== 0),
                    ],
                    [
                        'label' => 'Chi phí khác',
                        'spark' => 'expense',
                        'value' => $money['expense'],
                        'previous' => $previousMoney['expense'],
                        'higherIsBetter' => false,
                        'note' => null,
                        'tooltip' => 'Chi phí phát sinh từ bảo hành và mất hàng.',
                        'hasSpark' => $rangeDays > 1 && collect($daily)->contains(fn (array $r): bool => ($r['expense'] ?? 0) !== 0),
                    ],
                    [
                        'label' => 'Lợi nhuận',
                        'spark' => 'profit',
                        'value' => $money['profit'],
                        'previous' => $previousMoney['profit'],
                        'higherIsBetter' => true,
                        'note' => $margin === null ? null : 'Lãi '.$percent($margin).' trên doanh thu',
                        'tooltip' => 'Doanh thu − Giá vốn − Chi phí khác = Lợi nhuận (theo sổ cái bán hàng).',
                        'hasSpark' => $rangeDays > 1 && collect($daily)->contains(fn (array $r): bool => $r['profit'] !== 0),
                    ],
                ];
            @endphp

            <div id="vung-tai-chinh" role="tabpanel" aria-labelledby="tab-finance" x-show="activeTab === 'finance'" class="flex flex-col gap-6 scroll-mt-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($cards as $card)
                        @php
                            $delta = $change($card['value'], $card['previous']);
                            $good = $delta === null || $card['higherIsBetter'] === null ? null : ($delta >= 0) === $card['higherIsBetter'];
                        @endphp
                        <article class="admin-card !p-5 flex flex-col justify-between" data-metric="{{ $card['label'] }}">
                            <div>
                                <div class="flex items-center gap-1">
                                    <h3 class="text-sm font-semibold text-muted">{{ $card['label'] }}</h3>
                                    @if (! empty($card['tooltip']))
                                        <x-admin.tooltip :text="$card['tooltip']" :label="'Giải thích '.$card['label']" />
                                    @endif
                                </div>
                                <p @class(['mt-1.5 text-2xl font-bold tabular-nums text-main', 'text-error' => $card['value'] < 0])>{{ $vnd($card['value']) }}</p>
                                @if (! empty($card['note']))
                                    <p class="mt-1 text-xs text-muted">{{ $card['note'] }}</p>
                                @endif
                            </div>
                            <div class="mt-3 pt-2.5 border-t border-border/50">
                                <p class="flex items-center gap-1.5 text-xs">
                                    @if ($delta === null)
                                        <span class="text-muted/70">Chưa có số kỳ trước ({{ $prevFromLabel }} – {{ $prevToLabel }})</span>
                                    @else
                                        <span @class([
                                            'badge !px-2 !py-0.5 tabular-nums',
                                            'bg-success-bg text-success-text border border-success/30' => $good === true,
                                            'bg-error-bg text-error-text border border-error/30' => $good === false,
                                            'bg-surface text-muted border border-border' => $good === null,
                                        ])>
                                            <span aria-hidden="true">{{ $delta >= 0 ? '▲' : '▼' }}</span> {{ $percent(abs($delta)) }}
                                        </span>
                                        <span class="text-muted">so với {{ $rangeDays }} ngày trước đó</span>
                                    @endif
                                </p>
                                <div @class(['relative mt-2.5 h-9', 'hidden' => ! $card['hasSpark']]) aria-hidden="true">
                                    <canvas data-sparkline="{{ $card['spark'] }}"></canvas>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="admin-card xl:col-span-2 flex flex-col justify-between" aria-labelledby="chart-title">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-1">
                                    <h2 id="chart-title" class="text-lg font-semibold text-main">Doanh thu theo {{ $periodWord }}</h2>
                                    <x-admin.tooltip
                                        text="Mỗi cột gộp giá vốn, chi phí khác và lợi nhuận. Rê chuột hoặc chạm vào cột để xem chi tiết."
                                        label="Cách đọc biểu đồ doanh thu"
                                    />
                                </div>
                                <p class="flex flex-wrap gap-4 text-xs text-muted">
                                    {!! $legend('var(--text-muted)', 'Giá vốn') !!}
                                    {!! $legend('var(--accent-sub)', 'Chi phí khác') !!}
                                    {!! $legend('var(--success)', 'Lợi nhuận') !!}
                                </p>
                            </div>

                            <div class="relative mt-4 h-72" data-report-chart>
                                @if ($hasFinanceActivity)
                                    <canvas x-ref="money" role="img" aria-label="Biểu đồ cột chồng doanh thu theo {{ $periodWord }} từ {{ $day($from) }} đến {{ $day($to) }}: giá vốn, chi phí khác và lợi nhuận"></canvas>
                                @else
                                    <div class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-surface/40 p-6 text-center">
                                        <p class="text-sm font-semibold text-main">Chưa có phát sinh tài chính trong khoảng {{ $fromLabel }} – {{ $toLabel }}</p>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6 border-t border-border pt-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-1">
                                        <h3 class="font-semibold text-main">Biên lợi nhuận theo {{ $periodWord }}</h3>
                                        <x-admin.tooltip
                                            text="Tỉ lệ lợi nhuận trên doanh thu; đường đứt là mức trung bình cả kỳ."
                                            label="Cách đọc biên lợi nhuận"
                                        />
                                    </div>
                                    <p class="flex flex-wrap gap-4 text-xs text-muted">
                                        {!! $legend('var(--success)', 'Biên lợi nhuận') !!}
                                        {!! $legend('var(--text-muted)', 'Trung bình cả kỳ') !!}
                                    </p>
                                </div>
                                <div class="relative mt-3 h-36" data-margin-chart>
                                    @if ($hasFinanceActivity)
                                        <canvas x-ref="margin" role="img" aria-label="Biểu đồ biên lợi nhuận theo {{ $periodWord }} so với trung bình cả kỳ"></canvas>
                                    @else
                                        <div class="flex h-full items-center justify-center rounded-2xl border border-dashed border-border bg-surface/40 p-4 text-center text-xs text-muted">
                                            Chưa có phát sinh tài chính trong kỳ.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <details class="mt-5 border-t border-border pt-4 text-sm group">
                            <summary class="cursor-pointer font-semibold text-muted hover:text-main flex items-center justify-between gap-2">
                                <span>Bảng đối chiếu từng ngày ({{ count($daily) }} ngày)</span>
                                <span class="text-xs font-normal text-muted">Doanh thu − Giá vốn − Chi phí khác = Lợi nhuận</span>
                            </summary>
                            <div class="admin-table-wrap mt-3 max-h-80 overflow-y-auto">
                                <table class="admin-table">
                                    <thead class="sticky top-0 z-10 bg-surface">
                                        <tr>
                                            <th>Ngày</th>
                                            <th class="text-right">Doanh thu</th>
                                            <th class="text-right">Giá vốn</th>
                                            <th class="text-right">Chi phí khác</th>
                                            <th class="text-right">Lợi nhuận</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($daily as $row)
                                            @php $rowExpense = $row['expense'] ?? 0; @endphp
                                            <tr class="hover:bg-surface/60 transition-colors">
                                                <td class="whitespace-nowrap font-medium text-main">{{ $day($row['day']) }}</td>
                                                <td class="text-right tabular-nums text-main">{{ $vnd($row['revenue']) }}</td>
                                                <td class="text-right tabular-nums text-muted">{{ $vnd($row['cogs']) }}</td>
                                                <td @class(['text-right tabular-nums', 'text-error font-semibold' => $rowExpense > 0, 'text-muted' => $rowExpense <= 0])>{{ $vnd($rowExpense) }}</td>
                                                <td @class(['text-right tabular-nums font-semibold', 'text-error' => $row['profit'] < 0, 'text-main' => $row['profit'] >= 0])>{{ $vnd($row['profit']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="sticky bottom-0 z-10 bg-surface border-t-2 border-border font-bold text-main">
                                        <tr>
                                            <td class="p-4">Tổng cộng ({{ $rangeDays }} ngày)</td>
                                            <td class="p-4 text-right tabular-nums text-main">{{ $vnd($money['revenue']) }}</td>
                                            <td class="p-4 text-right tabular-nums text-main">{{ $vnd($money['cogs']) }}</td>
                                            <td class="p-4 text-right tabular-nums text-main">{{ $vnd($money['expense']) }}</td>
                                            <td @class(['p-4 text-right tabular-nums', 'text-error' => $money['profit'] < 0, 'text-main' => $money['profit'] >= 0])>{{ $vnd($money['profit']) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </details>
                    </section>

                    <section class="admin-card flex flex-col justify-between" aria-labelledby="structure-title">
                        <div>
                            <h2 id="structure-title" class="text-lg font-semibold text-main">Mỗi 100 đồng doanh thu</h2>

                            @if ($money['revenue'] > 0)
                                @php
                                    $parts = [
                                        ['label' => 'Giá vốn', 'value' => $money['cogs'], 'color' => 'var(--text-muted)'],
                                        ['label' => 'Chi phí khác', 'value' => $money['expense'], 'color' => 'var(--accent-sub)'],
                                        ['label' => 'Lợi nhuận', 'value' => max($money['profit'], 0), 'color' => 'var(--success)'],
                                    ];
                                    $whole = max(1, array_sum(array_column($parts, 'value')));
                                @endphp
                                <div class="mt-5 flex h-4 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                                    @foreach ($parts as $part)
                                        @if ($part['value'] > 0)
                                            <span class="h-full first:rounded-l-full last:rounded-r-full" style="width: {{ round($part['value'] / $whole * 100, 2) }}%; background: {{ $part['color'] }}"></span>
                                        @endif
                                    @endforeach
                                </div>
                                <dl class="mt-6 space-y-4">
                                    @foreach ($parts as $part)
                                        <div class="flex items-center gap-3">
                                            <span class="h-3 w-3 shrink-0 rounded-sm" style="background: {{ $part['color'] }}" aria-hidden="true"></span>
                                            <dt class="flex-1 text-sm text-muted">{{ $part['label'] }}</dt>
                                            <dd class="text-right text-sm">
                                                <span class="font-semibold tabular-nums text-main">{{ $percent($part['value'] / $money['revenue'] * 100) }}</span>
                                                <span class="block text-xs tabular-nums text-muted">{{ $vnd($part['value']) }}</span>
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                                @if ($money['profit'] < 0)
                                    <p class="mt-4 rounded-xl border border-error/30 bg-error-bg px-3 py-2 text-xs font-semibold text-error-text">
                                        Kỳ này lỗ {{ $vnd(-$money['profit']) }}.
                                    </p>
                                @endif
                            @else
                                <div class="py-8 text-center">
                                    <p class="text-sm text-muted">Chưa có doanh thu trong khoảng này.</p>
                                    @if ($hasFinanceActivity)
                                        <div class="mt-4 rounded-2xl border border-border bg-surface p-4 text-left space-y-2 text-xs">
                                            <p class="font-semibold text-main">Chi phí phát sinh khi chưa có doanh thu</p>
                                            <div class="flex justify-between text-muted">
                                                <span>Giá vốn:</span>
                                                <span class="font-semibold tabular-nums text-main">{{ $vnd($money['cogs']) }}</span>
                                            </div>
                                            <div class="flex justify-between text-muted">
                                                <span>Chi phí khác:</span>
                                                <span class="font-semibold tabular-nums text-main">{{ $vnd($money['expense']) }}</span>
                                            </div>
                                            <div class="flex justify-between border-t border-border pt-2 font-bold">
                                                <span class="text-main">Lợi nhuận:</span>
                                                <span @class(['tabular-nums', 'text-error' => $money['profit'] < 0, 'text-main' => $money['profit'] >= 0])>{{ $vnd($money['profit']) }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 rounded-xl bg-surface p-3.5 text-xs text-muted space-y-1.5 border border-border/60">
                            <p class="font-semibold text-main">Cơ cấu doanh thu</p>
                            <div class="flex justify-between">
                                <span>Tiền hàng đã giao:</span>
                                <span class="font-semibold tabular-nums text-main">{{ $vnd($money['merchandise']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Phí vận chuyển:</span>
                                <span class="font-semibold tabular-nums text-main">{{ $vnd($money['shipping']) }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        @endif

        {{-- VÙNG 2: VẬN HÀNH & ĐƠN HÀNG THEO KỲ ĐÃ CHỌN --}}
        {{-- TAB 2: VẬN HÀNH & ĐƠN HÀNG THEO KỲ ĐÃ CHỌN --}}
        <div id="vung-van-hanh" role="tabpanel" aria-labelledby="tab-operations" x-show="activeTab === 'operations'" @if($isAdmin) x-cloak @endif class="flex flex-col gap-6 scroll-mt-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <article class="admin-card !p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1">
                            <h2 class="text-sm font-semibold text-muted">Đơn mới đặt</h2>
                            <x-admin.tooltip text="Tính theo thời điểm khách đặt đơn trong kỳ." label="Giải thích Đơn mới đặt" />
                        </div>
                        <p class="mt-1.5 text-2xl font-bold tabular-nums text-main">{{ number_format($ordersPlaced, 0, ',', '.') }}</p>
                    </div>
                    <div @class(['relative mt-3 h-9', 'hidden' => ! $hasOrdersSpark]) aria-hidden="true">
                        <canvas data-sparkline="orders"></canvas>
                    </div>
                </article>
                <article class="admin-card !p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1">
                            <h2 class="text-sm font-semibold text-muted">Đôi đã giao</h2>
                            <x-admin.tooltip text="Tổng số đôi giày thuộc các đơn giao thành công trong kỳ." label="Giải thích Đôi đã giao" />
                        </div>
                        <p class="mt-1.5 text-2xl font-bold tabular-nums text-main">{{ number_format($pairsSold, 0, ',', '.') }} đôi</p>
                    </div>
                    <div @class(['relative mt-3 h-9', 'hidden' => ! $hasPairsSpark]) aria-hidden="true">
                        <canvas data-sparkline="pairs"></canvas>
                    </div>
                </article>
            </div>

            <section class="admin-card" aria-labelledby="status-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1">
                        <h2 id="status-title" class="text-lg font-semibold text-main">Đơn theo trạng thái</h2>
                        <x-admin.tooltip text="Đơn đặt trong kỳ theo trạng thái hiện tại. Bấm vào từng thẻ để lọc danh sách đơn hàng." label="Giải thích Đơn theo trạng thái" />
                    </div>
                    <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-accent hover:underline">Mở toàn bộ đơn hàng →</a>
                </div>
                <dl class="mt-4 grid gap-3 sm:grid-cols-4 lg:grid-cols-7">
                    @foreach (App\Enums\OrderStatus::cases() as $status)
                        @php $statusCount = $orderCounts[$status->value]; @endphp
                        <div class="rounded-2xl bg-surface/60 border border-border/70 transition-colors hover:border-accent hover:bg-surface">
                            <a href="{{ route('admin.orders.index', ['status' => $status->value]) }}"
                               class="block p-3 rounded-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent group">
                                <dt class="flex items-center justify-between gap-1 text-xs text-muted group-hover:text-accent font-medium">
                                    <span>{{ $status->label() }}</span>
                                    <span aria-hidden="true" class="opacity-60 group-hover:opacity-100 transition-opacity">→</span>
                                </dt>
                                <dd class="mt-1 text-lg font-bold tabular-nums text-main">{{ $statusCount }}</dd>
                                <dd class="mt-2 h-1 rounded-full bg-border" aria-hidden="true">
                                    <div class="h-full rounded-full bg-accent" style="width: {{ $ordersPlaced > 0 ? round($statusCount / $ordersPlaced * 100, 2) : 0 }}%"></div>
                                </dd>
                            </a>
                        </div>
                    @endforeach
                </dl>
            </section>

            <div class="grid gap-6 lg:grid-cols-12">
                <section class="admin-card lg:col-span-7 flex flex-col justify-between" aria-labelledby="operations-title"
                         x-data="{ selectedCell: @js($defaultHeatSelection) }">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-1">
                                <h2 id="operations-title" class="text-lg font-semibold text-main">Lịch số đơn mỗi ngày</h2>
                                <x-admin.tooltip text="Hàng là thứ, cột là tuần. Ô càng đậm càng nhiều đơn; chọn ô để xem số đơn mới đặt và số đôi đã giao." label="Cách đọc lịch số đơn" />
                            </div>
                            <p class="flex items-center gap-1.5 text-xs text-muted" aria-hidden="true">
                                Ít
                                @foreach ($heatSteps as $color)
                                    <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ $color }}"></span>
                                @endforeach
                                Nhiều
                            </p>
                        </div>

                        <div class="mt-4 overflow-x-auto pb-1" data-orders-heatmap>
                            <table class="border-separate text-xs text-muted" style="border-spacing: 4px" aria-label="Số đơn đặt theo ngày từ {{ $day($from) }} đến {{ $day($to) }}, tối đa {{ $heatPeak }} đơn một ngày">
                                <thead>
                                    <tr>
                                        <th scope="col"><span class="sr-only">Thứ</span></th>
                                        @for ($week = 0; $week < $heatWeeks; $week++)
                                            @php $monday = collect(array_column($heatCells, $week))->filter()->first(); @endphp
                                            <th scope="col" class="h-4 min-w-[1.75rem] text-left font-normal text-muted">{{ $monday !== null && ($week % 2 === 0 || $heatWeeks <= 8) ? $day($monday['day']) : '' }}</th>
                                        @endfor
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($weekdayLabels as $weekday => $name)
                                        <tr>
                                            <th scope="row" class="pr-2 text-left font-normal text-muted">{{ $name }}</th>
                                            @foreach ($heatCells[$weekday] as $cell)
                                                @if ($cell === null)
                                                    <td class="h-7 min-w-[1.75rem]"></td>
                                                @else
                                                    <td class="h-7 min-w-[1.75rem] rounded-md cursor-pointer transition-all hover:opacity-85 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent"
                                                        :class="selectedCell && selectedCell.day === '{{ $cell['day'] }}' ? 'ring-2 ring-accent ring-offset-1 ring-offset-surface-card' : ''"
                                                        style="background: {{ $heatColor($cell['orders']) }}"
                                                        title="{{ $name }} {{ $cell['formatted'] }}: {{ $cell['orders'] }} đơn, {{ $cell['pairs'] }} đôi đã giao"
                                                        role="button"
                                                        tabindex="0"
                                                        x-on:click="selectedCell = @js($cell)"
                                                        x-on:keydown.enter.prevent="selectedCell = @js($cell)"
                                                        x-on:keydown.space.prevent="selectedCell = @js($cell)">
                                                        <span class="sr-only">{{ $cell['formatted'] }}: {{ $cell['orders'] }} đơn, {{ $cell['pairs'] }} đôi đã giao</span>
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Dòng thông tin chi tiết ngày đang chọn (hỗ trợ màn hình cảm ứng & bàn phím) --}}
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border/80 bg-surface px-3.5 py-2.5 text-xs" aria-live="polite">
                            <template x-if="selectedCell">
                                <div class="flex flex-wrap items-center gap-2 w-full justify-between">
                                    <span class="font-semibold text-main">
                                        Ngày đang chọn: <span x-text="`${selectedCell.weekdayFull}, ${selectedCell.fullDate}`"></span>
                                    </span>
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="badge bg-surface-card border border-border text-main tabular-nums" x-text="`${selectedCell.orders} đơn mới đặt`"></span>
                                        <span class="badge bg-accent/10 border border-accent/20 text-accent tabular-nums" x-text="`${selectedCell.pairs} đôi đã giao`"></span>
                                    </span>
                                </div>
                            </template>
                            <template x-if="!selectedCell">
                                <span class="text-muted">Chạm hoặc bấm vào một ô ngày trên lịch để xem số đơn mới đặt và số đôi đã giao.</span>
                            </template>
                        </div>
                    </div>

                    <details class="mt-4 border-t border-border pt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-muted hover:text-main">Xem bảng số liệu ({{ count($operations) }} ngày)</summary>
                        <div class="admin-table-wrap mt-3 max-h-80 overflow-y-auto">
                            <table class="admin-table">
                                <thead class="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th>Ngày</th>
                                        <th class="text-right">Đơn mới đặt</th>
                                        <th class="text-right">Đôi đã giao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($operations as $row)
                                        <tr class="hover:bg-surface/60 transition-colors">
                                            <td class="font-medium text-main">{{ $day($row['day']) }}</td>
                                            <td class="text-right tabular-nums text-main">{{ $row['orders'] }}</td>
                                            <td class="text-right tabular-nums text-main">{{ $row['pairs'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="sticky bottom-0 z-10 bg-surface border-t-2 border-border font-bold text-main">
                                    <tr>
                                        <td class="p-4">Tổng cộng ({{ $rangeDays }} ngày)</td>
                                        <td class="p-4 text-right tabular-nums text-main">{{ number_format($ordersPlaced, 0, ',', '.') }}</td>
                                        <td class="p-4 text-right tabular-nums text-main">{{ number_format($pairsSold, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </details>
                </section>

                <section class="admin-card lg:col-span-5" aria-labelledby="best-title">
                    <div class="flex items-center gap-1">
                        <h2 id="best-title" class="text-lg font-semibold text-main">Sản phẩm bán chạy</h2>
                        <x-admin.tooltip text="Xếp hạng theo số đôi đã giao thành công trong kỳ." label="Giải thích Sản phẩm bán chạy" />
                    </div>
                    <ol class="mt-5 space-y-4">
                        @forelse ($bestSellers as $index => $row)
                            <li class="flex items-center gap-3" data-best-seller>
                                <span @class([
                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                    'bg-accent text-accent-text' => $index === 0,
                                    'bg-surface text-main border border-border' => $index > 0,
                                ])>{{ $index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="truncate font-medium text-main">{{ $row['name'] }}</span>
                                        <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-main">{{ $row['pairs'] }} đôi</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 rounded-full bg-surface overflow-hidden border border-border/50" aria-hidden="true">
                                        <div class="h-full rounded-full bg-accent" style="width: {{ round($row['pairs'] / $topPairs * 100, 2) }}%"></div>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="py-10 text-center text-sm text-muted">Chưa có đơn giao thành công trong khoảng này.</li>
                        @endforelse
                    </ol>
                </section>
            </div>
        </div>

        {{-- TAB 3: TỒN KHO HIỆN TẠI (TẠI THỜI ĐIỂM TẢI TRANG) --}}
        <div id="vung-ton-kho" role="tabpanel" aria-labelledby="tab-inventory" x-show="activeTab === 'inventory'" x-cloak class="flex flex-col gap-6 scroll-mt-6">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-border bg-surface/60 px-4 py-2.5 text-xs">
                <div class="flex items-center gap-1.5 text-muted">
                    <span class="font-semibold text-main">Tại thời điểm tải trang · Không theo bộ lọc ngày</span>
                    <x-admin.tooltip text="Số liệu tồn kho phản ánh trạng thái hiện tại khi mở trang, không thay đổi theo bộ lọc ngày." label="Lưu ý tồn kho hiện tại" />
                </div>
                <span class="text-muted">{{ number_format($inventory['variants'], 0, ',', '.') }} size đang mở bán</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <article class="admin-card !p-5 flex flex-col justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-muted">Tồn kho còn bán được</h2>
                        <p class="mt-1.5 text-2xl font-bold tabular-nums text-main" data-sellable>{{ number_format($inventory['sellable'], 0, ',', '.') }} đôi</p>
                    </div>
                    <div class="mt-2 space-y-0.5 text-xs text-muted">
                        @if ($inventory['reserved'] > 0)
                            <p>Đã trừ {{ $inventory['reserved'] }} đôi khách đặt chưa giao</p>
                        @else
                            <p>Trên {{ number_format($inventory['variants'], 0, ',', '.') }} size đang mở bán</p>
                        @endif
                        @if ($inventory['quarantine'] > 0)
                            <p>Cách ly kiểm tra: {{ $inventory['quarantine'] }} đôi</p>
                        @endif
                    </div>
                </article>
                <button type="button" x-on:click="$dispatch('open-low-stock', { trigger: $el })" aria-haspopup="dialog" aria-controls="low-stock-dialog" data-low-stock-card
                        class="admin-card !p-5 text-left transition hover:border-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent flex flex-col justify-between cursor-pointer">
                    <div>
                        <span class="flex items-center justify-between gap-2 text-sm font-semibold text-muted">
                            <span>Sắp hết hàng</span>
                            <span aria-hidden="true" class="text-accent font-bold">→</span>
                        </span>
                        <span @class(['mt-1.5 block text-2xl font-bold tabular-nums', 'text-error' => $inventory['low'] !== [], 'text-main' => $inventory['low'] === []])>
                            {{ count($inventory['low']) }} size
                        </span>
                    </div>
                    <span class="mt-2 block text-xs text-muted">Còn {{ $lowStockThreshold }} đôi trở xuống · bấm để xem</span>
                </button>
            </div>

            <section class="admin-card" aria-labelledby="low-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1">
                        <h2 id="low-title" class="text-lg font-semibold text-main">Cảnh báo sắp hết hàng</h2>
                        <x-admin.tooltip :text="'Size còn '.$lowStockThreshold.' đôi bán được trở xuống, xếp từ ít nhất.'" label="Giải thích Cảnh báo sắp hết hàng" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if (count($inventory['low']) > 5)
                            <button type="button" x-on:click="$dispatch('open-low-stock', { trigger: $el })" class="btn-pill">
                                Xem tất cả {{ count($inventory['low']) }} size
                            </button>
                        @endif
                        <a href="{{ route('admin.stock-imports.create') }}" class="btn-pill-dark">+ Tạo phiếu nhập hàng</a>
                    </div>
                </div>

                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <thead class="sticky top-0 z-10 bg-surface">
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Màu / size</th>
                                <th class="text-right">Còn lại</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (array_slice($inventory['low'], 0, 5) as $row)
                                <tr data-low-stock class="hover:bg-surface/60 transition-colors">
                                    <td class="font-semibold text-main">
                                        {{ $row['product'] }}
                                        <span class="block text-xs font-normal text-muted">{{ $row['sku'] }}</span>
                                    </td>
                                    <td class="text-main">{{ $row['color'] }} · {{ $row['size'] }}</td>
                                    <td class="text-right">
                                        <span @class([
                                            'badge',
                                            'bg-error-bg text-error-text border border-error/30' => $row['sellable'] <= 0,
                                            'bg-warning-bg text-warning-text border border-warning/30' => $row['sellable'] > 0,
                                        ])>{{ $row['sellable'] <= 0 ? 'Hết hàng' : $row['sellable'].' đôi' }}</span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if (! empty($row['variant_id']))
                                            <a href="{{ route('admin.stock-imports.create', ['variant_id' => $row['variant_id']]) }}"
                                               class="inline-flex items-center px-2.5 py-1 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors">
                                                + Nhập kho
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-10 text-center text-muted">Không có size nào sắp hết.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- Dialog xem toàn bộ size sắp hết hàng --}}
        <div x-data="dialogController()" x-init="attach({ panel: $refs.panel, body: document.body, document, scheduleFocus: (callback) => $nextTick(callback) })"
             x-on:open-low-stock.window="openDialog($event.detail?.trigger)">
            <div x-cloak x-show="opened" x-on:keydown="onKeydown($event)" x-on:click.self="onBackdrop($event)" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="presentation">
                <section x-ref="panel" id="low-stock-dialog" role="dialog" aria-modal="true" aria-labelledby="low-stock-dialog-title" tabindex="-1" class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-3xl bg-surface border border-border shadow-xl">
                    <header class="flex items-start justify-between gap-4 border-b border-border p-6">
                        <div>
                            <h2 id="low-stock-dialog-title" class="text-xl font-semibold text-main">Size sắp hết hàng ({{ count($inventory['low']) }})</h2>
                            <p class="mt-1 text-sm text-muted">Tồn kho hiện tại tại thời điểm tải trang · Còn {{ $lowStockThreshold }} đôi trở xuống, ít nhất xếp trước</p>
                        </div>
                        <button type="button" x-on:click="closeDialog()" class="btn-pill !px-3" aria-label="Đóng">✕</button>
                    </header>
                    <div class="overflow-y-auto px-6 py-2">
                        <table class="admin-table">
                            <thead class="sticky top-0 z-10 bg-surface">
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Màu · size</th>
                                    <th class="text-right">Còn lại</th>
                                    <th class="text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($inventory['low'] as $row)
                                    <tr data-low-stock-all class="hover:bg-surface/60 transition-colors">
                                        <td class="font-semibold text-main">
                                            {{ $row['product'] }}
                                            <span class="block text-xs font-normal text-muted">{{ $row['sku'] }}</span>
                                        </td>
                                        <td class="text-main">{{ $row['color'] }} · {{ $row['size'] }}</td>
                                        <td class="text-right">
                                            <span @class([
                                                'badge',
                                                'bg-error-bg text-error-text border border-error/30' => $row['sellable'] <= 0,
                                                'bg-warning-bg text-warning-text border border-warning/30' => $row['sellable'] > 0,
                                            ])>{{ $row['sellable'] <= 0 ? 'Hết hàng' : $row['sellable'].' đôi' }}</span>
                                        </td>
                                        <td class="text-right whitespace-nowrap">
                                            @if (! empty($row['variant_id']))
                                                <a href="{{ route('admin.stock-imports.create', ['variant_id' => $row['variant_id']]) }}"
                                                   class="inline-flex items-center px-2.5 py-1 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors">
                                                    Nhập kho
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-10 text-center text-muted">Không có size nào sắp hết.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <footer class="flex justify-end gap-3 border-t border-border p-6">
                        <button type="button" x-on:click="closeDialog()" class="btn-pill">Đóng</button>
                        <a href="{{ route('admin.stock-imports.create') }}" class="btn-pill-dark">Tạo phiếu nhập hàng</a>
                    </footer>
                </section>
            </div>
        </div>

        {{-- TAB 4: ĐÁNH GIÁ BÀI VIẾT THEO KỲ ĐÃ CHỌN (ADMIN) --}}
        @if ($isAdmin)
            @php
                $answers = $articleFeedback['helpful'] + $articleFeedback['unhelpful'];
                $helpfulShare = $answers > 0 ? $articleFeedback['helpful'] / $answers * 100 : 0;
            @endphp
            <section id="vung-bai-viet" role="tabpanel" aria-labelledby="tab-feedback" x-show="activeTab === 'feedback'" x-cloak class="admin-card scroll-mt-6" data-feedback-report>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1">
                        <h2 id="feedback-title" class="text-lg font-semibold text-main">Đánh giá bài viết</h2>
                        <x-admin.tooltip text="Tổng hợp phản hồi hữu ích của người đọc trong kỳ đã chọn." label="Giải thích Đánh giá bài viết" />
                    </div>
                    <p class="flex flex-wrap gap-4 text-xs text-muted">
                        {!! $legend('var(--success)', 'Hữu ích') !!}
                        {!! $legend('var(--accent-sub)', 'Không hữu ích') !!}
                    </p>
                </div>
                @if ($answers === 0)
                    <p class="py-10 text-center text-sm text-muted">Chưa có người đọc nào đánh giá trong khoảng này.</p>
                @else
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl bg-surface/60 border border-border/50 p-4">
                            <p class="text-xs text-muted">Hữu ích</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-main">{{ $articleFeedback['helpful'] }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface/60 border border-border/50 p-4">
                            <p class="text-xs text-muted">Không hữu ích</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-main">{{ $articleFeedback['unhelpful'] }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface/60 border border-border/50 p-4">
                            <p class="text-xs text-muted">Tỉ lệ hữu ích</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-main">{{ $percent($helpfulShare) }}</p>
                            <div class="mt-2 flex h-2 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                                @if ($articleFeedback['helpful'] > 0)
                                    <span class="h-full" style="width: {{ round($helpfulShare, 2) }}%; background: var(--success)"></span>
                                @endif
                                @if ($articleFeedback['unhelpful'] > 0)
                                    <span class="h-full flex-1" style="background: var(--accent-sub)"></span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <h3 class="mt-6 text-sm font-semibold text-main">Theo bài viết (nhiều lượt đánh giá nhất)</h3>
                    <div class="relative mt-3" style="height: {{ count($articleFeedback['articles']) * 44 + 48 }}px">
                        <canvas x-ref="feedback" role="img" aria-label="Biểu đồ số lượt hữu ích và không hữu ích của từng bài viết"></canvas>
                    </div>
                    <details class="mt-4 border-t border-border pt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-muted hover:text-main">Xem bảng số liệu ({{ count($articleFeedback['articles']) }} bài viết)</summary>
                        <div class="admin-table-wrap mt-3 max-h-80 overflow-y-auto">
                            <table class="admin-table">
                                <thead class="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th>Bài viết</th>
                                        <th class="text-right">Hữu ích</th>
                                        <th class="text-right">Không hữu ích</th>
                                        <th class="text-right">Tỉ lệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($articleFeedback['articles'] as $row)
                                        <tr data-feedback-row class="hover:bg-surface/60 transition-colors">
                                            <td>
                                                <a href="{{ route('news.show', $row['slug']) }}" class="font-semibold text-accent hover:underline">{{ $row['title'] }}</a>
                                            </td>
                                            <td class="text-right tabular-nums text-main">{{ $row['helpful'] }}</td>
                                            <td class="text-right tabular-nums text-main">{{ $row['unhelpful'] }}</td>
                                            <td class="text-right tabular-nums text-muted">{{ $percent($row['helpful'] / max(1, $row['helpful'] + $row['unhelpful']) * 100) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>
        @endif
    </div>
@endsection
