@extends('layouts.admin')
@section('title', 'Báo cáo')
@section('eyebrow', 'Dashboard và báo cáo')

@php
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $day = fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m');
    $percent = fn (float $value): string => number_format($value, 1, ',', '.').'%';
    /** Change against the previous period of the same length; null when there is nothing to compare with. */
    $change = fn (int $current, int $previous): ?float => $previous === 0 ? null : ($current - $previous) / abs($previous) * 100;
    $topPairs = max([1, ...array_column($bestSellers, 'pairs')]);
    $pairsSold = array_sum(array_column($operations, 'pairs'));
    $ordersPlaced = array_sum($orderCounts);
    $rangeDays = count($operations);
    $weekly = $rangeDays > 31;
    $periodWord = $weekly ? 'tuần' : 'ngày';
    $legend = fn (string $color, string $label): string => '<span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: '.$color.'"></span>'.e($label).'</span>';

    /*
     * Orders heatmap: rows are weekdays (Monday first), columns are weeks; cells outside the range stay empty.
     * Five steps of one blue hue, scaled to the busiest day of the range.
     */
    $heatPeak = max([1, ...array_column($operations, 'orders')]);
    $heatSteps = ['rgba(79, 70, 229, 0.08)', 'rgba(79, 70, 229, 0.2)', 'rgba(79, 70, 229, 0.4)', 'rgba(79, 70, 229, 0.65)', 'rgba(79, 70, 229, 0.85)', 'rgba(79, 70, 229, 1)'];
    $heatLead = $operations === [] ? 0 : \Carbon\CarbonImmutable::parse($operations[0]['day'])->dayOfWeekIso - 1;
    $heatWeeks = (int) ceil(($heatLead + count($operations)) / 7);
    $heatCells = array_fill(0, 7, array_fill(0, $heatWeeks, null));
    foreach ($operations as $index => $row) {
        $position = $heatLead + $index;
        $heatCells[$position % 7][intdiv($position, 7)] = $row;
    }
    $heatColor = fn (int $orders): string => $heatSteps[$orders === 0 ? 0 : min(5, (int) ceil($orders / $heatPeak * 5))];
@endphp

@section('content')
    <div class="flex flex-col gap-8" x-data="reportCharts()">
        <script type="application/json" x-ref="data">@json(['daily' => $daily, 'operations' => $operations, 'feedback' => $articleFeedback['articles'] ?? []])</script>

        <form method="GET" action="{{ route('admin.reports.index') }}" class="admin-card flex flex-wrap items-end gap-4" aria-label="Lọc theo ngày">
            <div>
                <label for="report-from" class="admin-label text-muted">Từ ngày</label>
                <input id="report-from" type="date" name="from" value="{{ $from }}" class="input border-border bg-surface text-main">
            </div>
            <div>
                <label for="report-to" class="admin-label text-muted">Đến ngày</label>
                <input id="report-to" type="date" name="to" value="{{ $to }}" class="input border-border bg-surface text-main">
            </div>
            <button type="submit" class="btn-pill-dark">Xem báo cáo</button>
            <nav class="flex flex-wrap gap-2 sm:ml-auto" aria-label="Khoảng ngày nhanh">
                @foreach ($presets as $preset)
                    <a href="{{ route('admin.reports.index', ['from' => $preset['from'], 'to' => $preset['to']]) }}"
                       @class(['btn-pill', '!border-accent !bg-accent !text-accent-text' => $preset['from'] === $from && $preset['to'] === $to])
                       @if ($preset['from'] === $from && $preset['to'] === $to) aria-current="true" @endif>{{ $preset['label'] }}</a>
                @endforeach
            </nav>
            <p class="w-full text-xs text-muted">Ngày theo giờ Việt Nam. Doanh thu chỉ tính đơn đã giao thành công, lấy từ sổ cái bán hàng.</p>
        </form>

        @if ($isAdmin)
            @php
                $margin = $money['revenue'] > 0 ? $money['profit'] / $money['revenue'] * 100 : null;
                $cards = [
                    ['label' => 'Doanh thu', 'spark' => 'revenue', 'value' => $money['revenue'], 'previous' => $previousMoney['revenue'], 'higherIsBetter' => true, 'note' => $money['shipping'] > 0 ? 'Gồm '.$vnd($money['shipping']).' phí ship' : 'Tiền bán giày đã giao'],
                    ['label' => 'Giá vốn', 'spark' => 'cogs', 'value' => $money['cogs'], 'previous' => $previousMoney['cogs'], 'higherIsBetter' => null, 'note' => 'Tiền nhập của số giày đã bán'],
                    ['label' => 'Chi phí khác', 'spark' => 'expense', 'value' => $money['expense'], 'previous' => $previousMoney['expense'], 'higherIsBetter' => false, 'note' => 'Bảo hành, mất hàng'],
                    ['label' => 'Lợi nhuận', 'spark' => 'profit', 'value' => $money['profit'], 'previous' => $previousMoney['profit'], 'higherIsBetter' => true, 'note' => $margin === null ? 'Doanh thu trừ giá vốn và chi phí' : 'Lãi '.$percent($margin).' trên doanh thu'],
                ];
            @endphp
            <section aria-label="Tài chính" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cards as $card)
                    @php
                        $delta = $change($card['value'], $card['previous']);
                        $good = $delta === null || $card['higherIsBetter'] === null ? null : ($delta >= 0) === $card['higherIsBetter'];
                    @endphp
                    <article class="admin-card" data-metric="{{ $card['label'] }}">
                        <h2 class="text-sm font-semibold text-muted">{{ $card['label'] }}</h2>
                        <p @class(['mt-2 text-2xl font-bold tabular-nums text-main', 'text-error' => $card['value'] < 0])>{{ $vnd($card['value']) }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $card['note'] }}</p>
                        <p class="mt-3 flex items-center gap-1 text-xs">
                            @if ($delta === null)
                                <span class="text-muted/70">Chưa có số để so sánh</span>
                            @else
                                <span @class(['badge !px-2 !py-0.5 tabular-nums', 'bg-success-bg text-success-text border border-success/30' => $good === true, 'bg-error-bg text-error-text border border-error/30' => $good === false, 'bg-surface-alt text-muted border border-border' => $good === null])>
                                    <span aria-hidden="true">{{ $delta >= 0 ? '▲' : '▼' }}</span> {{ $percent(abs($delta)) }}
                                </span>
                                <span class="text-muted">so với {{ $rangeDays }} ngày trước đó</span>
                            @endif
                        </p>
                        <div class="relative mt-3 h-10" aria-hidden="true"><canvas data-sparkline="{{ $card['spark'] }}"></canvas></div>
                    </article>
                @endforeach
            </section>

            <div class="grid gap-6 xl:grid-cols-3">
                <section class="admin-card xl:col-span-2" aria-labelledby="chart-title">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="chart-title" class="text-xl font-semibold text-main">Doanh thu theo {{ $periodWord }}</h2>
                            <p class="mt-1 text-xs text-muted">Mỗi cột là doanh thu một {{ $periodWord }}, chia thành giá vốn, chi phí khác và lợi nhuận · rê chuột để xem số</p>
                        </div>
                        <p class="flex flex-wrap gap-4 text-xs text-muted">{!! $legend('#94a3b8', 'Giá vốn') !!}{!! $legend('#be123c', 'Chi phí khác') !!}{!! $legend('#15803d', 'Lợi nhuận') !!}</p>
                    </div>
                    <div class="relative mt-4 h-72" data-report-chart>
                        <canvas x-ref="money" role="img" aria-label="Biểu đồ cột chồng doanh thu theo {{ $periodWord }} từ {{ $day($from) }} đến {{ $day($to) }}: giá vốn, chi phí khác và lợi nhuận"></canvas>
                    </div>
                    <div class="mt-6 border-t border-border pt-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-main">Biên lợi nhuận theo {{ $periodWord }}</h3>
                                <p class="mt-1 text-xs text-muted">Lợi nhuận chia doanh thu; đường đứt là mức trung bình cả kỳ</p>
                            </div>
                            <p class="flex flex-wrap gap-4 text-xs text-muted">{!! $legend('#15803d', 'Biên lợi nhuận') !!}{!! $legend('#94a3b8', 'Trung bình cả kỳ') !!}</p>
                        </div>
                        <div class="relative mt-3 h-36" data-margin-chart>
                            <canvas x-ref="margin" role="img" aria-label="Biểu đồ biên lợi nhuận theo {{ $periodWord }} so với trung bình cả kỳ"></canvas>
                        </div>
                    </div>
                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-muted hover:text-main">Xem bảng số liệu</summary>
                        <div class="admin-table-wrap mt-3 max-h-80 overflow-y-auto"><table class="admin-table"><thead><tr><th>Ngày</th><th class="text-right">Doanh thu</th><th class="text-right">Giá vốn</th><th class="text-right">Lợi nhuận</th></tr></thead><tbody>
                            @foreach ($daily as $row)
                                <tr class="hover:bg-surface-alt/40 transition-colors"><td>{{ $day($row['day']) }}</td><td class="text-right tabular-nums text-main">{{ $vnd($row['revenue']) }}</td><td class="text-right tabular-nums text-muted">{{ $vnd($row['cogs']) }}</td><td @class(['text-right tabular-nums font-semibold', 'text-error' => $row['profit'] < 0, 'text-main' => $row['profit'] >= 0])>{{ $vnd($row['profit']) }}</td></tr>
                            @endforeach
                        </tbody></table></div>
                    </details>
                </section>

                <section class="admin-card" aria-labelledby="structure-title">
                    <h2 id="structure-title" class="text-xl font-semibold text-main">Mỗi 100 đồng doanh thu</h2>
                    <p class="mt-1 text-xs text-muted">Doanh thu chia thành giá vốn, chi phí khác và phần lãi còn lại</p>
                    @if ($money['revenue'] > 0)
                        @php
                            $parts = [
                                ['label' => 'Giá vốn', 'value' => $money['cogs'], 'color' => '#94a3b8'],
                                ['label' => 'Chi phí khác', 'value' => $money['expense'], 'color' => '#be123c'],
                                ['label' => 'Lợi nhuận', 'value' => max($money['profit'], 0), 'color' => '#15803d'],
                            ];
                            $whole = max(1, array_sum(array_column($parts, 'value')));
                        @endphp
                        <div class="mt-6 flex h-4 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
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
                                    <dd class="text-right text-sm"><span class="font-semibold tabular-nums text-main">{{ $percent($part['value'] / $money['revenue'] * 100) }}</span><span class="block text-xs tabular-nums text-muted">{{ $vnd($part['value']) }}</span></dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($money['profit'] < 0)
                            <p class="mt-4 text-xs font-semibold text-error">Kỳ này lỗ {{ $vnd(-$money['profit']) }}.</p>
                        @endif
                    @else
                        <p class="py-10 text-center text-sm text-muted">Chưa có doanh thu trong khoảng này.</p>
                    @endif
                </section>
            </div>
        @else
            <p class="rounded-3xl border border-border bg-surface-alt/60 p-4 text-sm text-muted">Nhân viên chỉ xem số liệu vận hành. Doanh thu, giá vốn và lợi nhuận chỉ Admin xem được.</p>
        @endif

        <section aria-label="Vận hành" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-muted">Đơn mới đặt</h2>
                <p class="mt-2 text-2xl font-bold tabular-nums text-main">{{ number_format($ordersPlaced, 0, ',', '.') }}</p>
                <div class="relative mt-3 h-10" aria-hidden="true"><canvas data-sparkline="orders"></canvas></div>
            </article>
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-muted">Đôi đã giao</h2>
                <p class="mt-2 text-2xl font-bold tabular-nums text-main">{{ number_format($pairsSold, 0, ',', '.') }} đôi</p>
                <div class="relative mt-3 h-10" aria-hidden="true"><canvas data-sparkline="pairs"></canvas></div>
            </article>
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-muted">Tồn kho còn bán được</h2>
                <p class="mt-2 text-2xl font-bold tabular-nums text-main" data-sellable>{{ number_format($inventory['sellable'], 0, ',', '.') }} đôi</p>
                @if ($inventory['reserved'] > 0)
                    <p class="mt-1 text-xs text-muted">Đã trừ {{ $inventory['reserved'] }} đôi khách đặt chưa giao</p>
                @endif
            </article>
            <button type="button" x-on:click="$dispatch('open-low-stock', { trigger: $el })" aria-haspopup="dialog" aria-controls="low-stock-dialog" data-low-stock-card
                    class="admin-card text-left transition hover:border-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent">
                <span class="flex items-center justify-between gap-2 text-sm font-semibold text-muted">Sắp hết hàng <span aria-hidden="true">→</span></span>
                <span @class(['mt-2 block text-2xl font-bold tabular-nums', 'text-error' => $inventory['low'] !== [], 'text-main' => $inventory['low'] === []])>{{ count($inventory['low']) }} size</span>
                <span class="mt-1 block text-xs text-muted">Còn {{ $lowStockThreshold }} đôi trở xuống · bấm để xem</span>
            </button>
        </section>

        <div x-data="dialogController()" x-init="attach({ panel: $refs.panel, body: document.body, document, scheduleFocus: (callback) => $nextTick(callback) })"
             x-on:open-low-stock.window="openDialog($event.detail?.trigger)">
            <div x-cloak x-show="opened" x-on:keydown="onKeydown($event)" x-on:click.self="onBackdrop($event)" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="presentation">
                <section x-ref="panel" id="low-stock-dialog" role="dialog" aria-modal="true" aria-labelledby="low-stock-dialog-title" tabindex="-1" class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-3xl bg-surface border border-border shadow-xl">
                    <header class="flex items-start justify-between gap-4 border-b border-border p-6">
                        <div>
                            <h2 id="low-stock-dialog-title" class="text-xl font-semibold text-main">Size sắp hết hàng ({{ count($inventory['low']) }})</h2>
                            <p class="mt-1 text-sm text-muted">Còn {{ $lowStockThreshold }} đôi trở xuống, ít nhất xếp trước</p>
                        </div>
                        <button type="button" x-on:click="closeDialog()" class="btn-pill !px-3" aria-label="Đóng">✕</button>
                    </header>
                    <div class="overflow-y-auto px-6 py-2">
                        <table class="admin-table">
                            <thead class="sticky top-0 bg-surface"><tr><th>Sản phẩm</th><th>Màu · size</th><th class="text-right">Còn lại</th></tr></thead>
                            <tbody>
                                @forelse ($inventory['low'] as $row)
                                    <tr data-low-stock-all class="hover:bg-surface-alt/40 transition-colors">
                                        <td class="font-semibold text-main">{{ $row['product'] }}<span class="block text-xs font-normal text-muted">{{ $row['sku'] }}</span></td>
                                        <td class="text-main">{{ $row['color'] }} · {{ $row['size'] }}</td>
                                        <td class="text-right"><span @class(['badge', 'bg-error-bg text-error-text border border-error/30' => $row['sellable'] <= 0, 'bg-warning-bg text-warning-text border border-warning/30' => $row['sellable'] > 0])>{{ $row['sellable'] <= 0 ? 'Hết hàng' : $row['sellable'].' đôi' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-10 text-center text-muted">Không có size nào sắp hết.</td></tr>
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

        <section class="admin-card" aria-labelledby="operations-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="operations-title" class="text-xl font-semibold text-main">Lịch số đơn mỗi ngày</h2>
                    <p class="mt-1 text-xs text-muted">Hàng là thứ trong tuần, cột là tuần. Ô càng đậm càng nhiều đơn · rê chuột để xem số đơn và số đôi đã giao</p>
                </div>
                <p class="flex items-center gap-1.5 text-xs text-muted" aria-hidden="true">
                    Ít
                    @foreach ($heatSteps as $color)
                        <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ $color }}"></span>
                    @endforeach
                    Nhiều
                </p>
            </div>
            <div class="mt-5 overflow-x-auto" data-orders-heatmap>
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
                        @foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $weekday => $name)
                            <tr>
                                <th scope="row" class="pr-2 text-left font-normal text-muted">{{ $name }}</th>
                                @foreach ($heatCells[$weekday] as $cell)
                                    @if ($cell === null)
                                        <td class="h-7 min-w-[1.75rem]"></td>
                                    @else
                                        <td class="h-7 min-w-[1.75rem] rounded-md transition-opacity hover:opacity-80" style="background: {{ $heatColor($cell['orders']) }}" title="{{ $name }} {{ $day($cell['day']) }}: {{ $cell['orders'] }} đơn, {{ $cell['pairs'] }} đôi đã giao"><span class="sr-only">{{ $day($cell['day']) }}: {{ $cell['orders'] }} đơn</span></td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-semibold text-muted hover:text-main">Xem bảng số liệu</summary>
                <div class="admin-table-wrap mt-3 max-h-80 overflow-y-auto"><table class="admin-table"><thead><tr><th>Ngày</th><th class="text-right">Đơn mới đặt</th><th class="text-right">Đôi đã giao</th></tr></thead><tbody>
                    @foreach ($operations as $row)
                        <tr class="hover:bg-surface-alt/40 transition-colors"><td>{{ $day($row['day']) }}</td><td class="text-right tabular-nums text-main">{{ $row['orders'] }}</td><td class="text-right tabular-nums text-main">{{ $row['pairs'] }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </details>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="admin-card" aria-labelledby="best-title">
                <h2 id="best-title" class="text-xl font-semibold text-main">Sản phẩm bán chạy</h2>
                <p class="mt-1 text-xs text-muted">Theo số đôi đã giao thành công trong khoảng ngày đã chọn</p>
                <ol class="mt-5 space-y-4">
                    @forelse ($bestSellers as $index => $row)
                        <li class="flex items-center gap-3" data-best-seller>
                            <span @class(['flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold', 'bg-accent text-accent-text' => $index === 0, 'bg-surface-alt text-main border border-border' => $index > 0])>{{ $index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="truncate font-medium text-main">{{ $row['name'] }}</span>
                                    <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-main">{{ $row['pairs'] }} đôi</span>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-surface-alt overflow-hidden border border-border/50" aria-hidden="true">
                                    <div class="h-full rounded-full bg-accent" style="width: {{ round($row['pairs'] / $topPairs * 100, 2) }}%"></div>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-muted">Chưa có đơn giao thành công trong khoảng này.</li>
                    @endforelse
                </ol>
            </section>

            <section class="admin-card" aria-labelledby="low-title">
                <h2 id="low-title" class="text-xl font-semibold text-main">Cảnh báo sắp hết hàng</h2>
                <p class="mt-1 text-xs text-muted">Còn {{ $lowStockThreshold }} đôi trở xuống, ít nhất xếp trước</p>
                <div class="admin-table-wrap mt-4"><table class="admin-table"><thead><tr><th>Sản phẩm</th><th>Màu / size</th><th>Còn lại</th></tr></thead><tbody>
                    @forelse (array_slice($inventory['low'], 0, 5) as $row)
                        <tr data-low-stock class="hover:bg-surface-alt/40 transition-colors">
                            <td class="font-semibold text-main">{{ $row['product'] }}<span class="block text-xs font-normal text-muted">{{ $row['sku'] }}</span></td>
                            <td class="text-main">{{ $row['color'] }} · {{ $row['size'] }}</td>
                            <td><span @class(['badge', 'bg-error-bg text-error-text border border-error/30' => $row['sellable'] <= 0, 'bg-warning-bg text-warning-text border border-warning/30' => $row['sellable'] > 0])>{{ $row['sellable'] <= 0 ? 'Hết hàng' : $row['sellable'].' đôi' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-10 text-center text-muted">Không có size nào sắp hết.</td></tr>
                    @endforelse
                </tbody></table></div>
                @if (count($inventory['low']) > 5)
                    <button type="button" x-on:click="$dispatch('open-low-stock', { trigger: $el })" class="btn-pill mt-4">Xem tất cả {{ count($inventory['low']) }} size</button>
                @endif
            </section>
        </div>

        <section class="admin-card" aria-labelledby="status-title">
            <h2 id="status-title" class="text-xl font-semibold text-main">Đơn theo trạng thái</h2>
            <p class="mt-1 text-xs text-muted">Đơn đặt trong khoảng ngày đã chọn, theo trạng thái hiện tại</p>
            <dl class="mt-4 grid gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach (App\Enums\OrderStatus::cases() as $status)
                    <div class="rounded-2xl bg-surface-alt/60 border border-border/50 p-3">
                        <dt class="text-xs text-muted">{{ $status->label() }}</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums text-main">{{ $orderCounts[$status->value] }}</dd>
                        <dd class="mt-2 h-1 rounded-full bg-border" aria-hidden="true"><div class="h-full rounded-full bg-accent" style="width: {{ $ordersPlaced > 0 ? round($orderCounts[$status->value] / $ordersPlaced * 100, 2) : 0 }}%"></div></dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($isAdmin)
            @php
                $answers = $articleFeedback['helpful'] + $articleFeedback['unhelpful'];
                $helpfulShare = $answers > 0 ? $articleFeedback['helpful'] / $answers * 100 : 0;
            @endphp
            <section class="admin-card" aria-labelledby="feedback-title" data-feedback-report>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="feedback-title" class="text-xl font-semibold text-main">Đánh giá bài viết</h2>
                        <p class="mt-1 text-xs text-muted">Câu trả lời "Bài viết có hữu ích không?" của người đọc trong khoảng ngày đã chọn</p>
                    </div>
                    <p class="flex flex-wrap gap-4 text-xs text-muted">{!! $legend('#15803d', 'Hữu ích') !!}{!! $legend('#be123c', 'Không hữu ích') !!}</p>
                </div>
                @if ($answers === 0)
                    <p class="py-10 text-center text-sm text-muted">Chưa có người đọc nào đánh giá trong khoảng này.</p>
                @else
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl bg-surface-alt/60 border border-border/50 p-4"><p class="text-xs text-muted">👍 Hữu ích</p><p class="mt-1 text-2xl font-bold tabular-nums text-main">{{ $articleFeedback['helpful'] }}</p></div>
                        <div class="rounded-2xl bg-surface-alt/60 border border-border/50 p-4"><p class="text-xs text-muted">👎 Không hữu ích</p><p class="mt-1 text-2xl font-bold tabular-nums text-main">{{ $articleFeedback['unhelpful'] }}</p></div>
                        <div class="rounded-2xl bg-surface-alt/60 border border-border/50 p-4">
                            <p class="text-xs text-muted">Tỉ lệ hữu ích</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-main">{{ $percent($helpfulShare) }}</p>
                            <div class="mt-2 flex h-2 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                                @if ($articleFeedback['helpful'] > 0)<span class="h-full" style="width: {{ round($helpfulShare, 2) }}%; background: #15803d"></span>@endif
                                @if ($articleFeedback['unhelpful'] > 0)<span class="h-full flex-1" style="background: #be123c"></span>@endif
                            </div>
                        </div>
                    </div>
                    <h3 class="mt-6 text-sm font-semibold text-main">Theo bài viết (nhiều lượt đánh giá nhất)</h3>
                    <div class="relative mt-3" style="height: {{ count($articleFeedback['articles']) * 44 + 48 }}px">
                        <canvas x-ref="feedback" role="img" aria-label="Biểu đồ số lượt hữu ích và không hữu ích của từng bài viết"></canvas>
                    </div>
                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-muted hover:text-main">Xem bảng số liệu</summary>
                        <div class="admin-table-wrap mt-3"><table class="admin-table"><thead><tr><th>Bài viết</th><th class="text-right">Hữu ích</th><th class="text-right">Không hữu ích</th><th class="text-right">Tỉ lệ</th></tr></thead><tbody>
                            @foreach ($articleFeedback['articles'] as $row)
                                <tr data-feedback-row class="hover:bg-surface-alt/40 transition-colors">
                                    <td><a href="{{ route('news.show', $row['slug']) }}" class="font-semibold text-accent hover:underline">{{ $row['title'] }}</a></td>
                                    <td class="text-right tabular-nums text-main">{{ $row['helpful'] }}</td>
                                    <td class="text-right tabular-nums text-main">{{ $row['unhelpful'] }}</td>
                                    <td class="text-right tabular-nums text-muted">{{ $percent($row['helpful'] / max(1, $row['helpful'] + $row['unhelpful']) * 100) }}</td>
                                </tr>
                            @endforeach
                        </tbody></table></div>
                    </details>
                @endif
            </section>
        @endif
    </div>
@endsection
