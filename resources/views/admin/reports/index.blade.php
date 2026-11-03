@extends('layouts.admin')
@section('title', 'Báo cáo')
@section('eyebrow', 'Dashboard và báo cáo')

@php
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $day = fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->format('d/m');
    $peak = max(1, ...array_map(fn (array $row): int => max($row['revenue'], 0), $daily ?: [['revenue' => 0]]));
    $pairsSold = array_sum(array_column($bestSellers, 'pairs'));
@endphp

@section('content')
    <div class="flex flex-col gap-8">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="admin-card flex flex-wrap items-end gap-4" aria-label="Lọc theo ngày">
            <div>
                <label for="report-from" class="admin-label">Từ ngày</label>
                <input id="report-from" type="date" name="from" value="{{ $from }}" class="input border-zinc-300">
            </div>
            <div>
                <label for="report-to" class="admin-label">Đến ngày</label>
                <input id="report-to" type="date" name="to" value="{{ $to }}" class="input border-zinc-300">
            </div>
            <button type="submit" class="btn-pill-dark">Xem báo cáo</button>
            <p class="w-full text-xs text-zinc-500">Ngày theo giờ Việt Nam. Doanh thu chỉ tính đơn đã giao thành công, lấy từ sổ cái bán hàng.</p>
        </form>

        @if ($isAdmin)
            <section aria-label="Tài chính" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'Doanh thu', 'value' => $money['revenue'], 'note' => 'Hàng '.$vnd($money['merchandise']).' · phí ship '.$vnd($money['shipping'])],
                    ['label' => 'Giá vốn', 'value' => $money['cogs'], 'note' => 'Giá vốn hàng đã giao'],
                    ['label' => 'Chi phí khác', 'value' => $money['expense'], 'note' => 'Bảo hành, hao hụt kho'],
                    ['label' => 'Lợi nhuận', 'value' => $money['profit'], 'note' => 'Doanh thu − giá vốn − chi phí'],
                ] as $card)
                    <article class="admin-card" data-metric="{{ $card['label'] }}">
                        <h2 class="text-sm font-semibold text-zinc-500">{{ $card['label'] }}</h2>
                        <p @class(['mt-2 text-2xl font-bold', 'text-red-600' => $card['value'] < 0])>{{ $vnd($card['value']) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ $card['note'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="admin-card" aria-labelledby="chart-title">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="chart-title" class="text-xl font-semibold">Doanh thu theo ngày</h2>
                    <p class="flex items-center gap-4 text-xs text-zinc-500">
                        <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-zinc-300"></span>Doanh thu</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-black"></span>Lợi nhuận</span>
                    </p>
                </div>
                <div class="mt-6 flex h-56 items-end gap-px overflow-x-auto border-b border-zinc-200" role="img" aria-label="Biểu đồ doanh thu và lợi nhuận theo ngày từ {{ $day($from) }} đến {{ $day($to) }}" data-report-chart>
                    @foreach ($daily as $row)
                        <div class="relative flex h-full min-w-[6px] flex-1 items-end" title="{{ $day($row['day']) }}: doanh thu {{ $vnd($row['revenue']) }}, lợi nhuận {{ $vnd($row['profit']) }}">
                            <div class="w-full rounded-t-sm bg-zinc-300" style="height: {{ round(max($row['revenue'], 0) / $peak * 100, 2) }}%"></div>
                            <div class="absolute bottom-0 left-1/4 w-1/2 rounded-t-sm bg-black" style="height: {{ round(max(min($row['profit'], $row['revenue']), 0) / $peak * 100, 2) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-xs text-zinc-500"><span>{{ $day($from) }}</span><span>Cao nhất {{ $vnd($peak) }}</span><span>{{ $day($to) }}</span></div>
            </section>
        @else
            <p class="rounded-3xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">Nhân viên chỉ xem số liệu vận hành. Doanh thu, giá vốn và lợi nhuận chỉ Admin xem được.</p>
        @endif

        <section aria-label="Vận hành" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-zinc-500">Đơn mới đặt</h2>
                <p class="mt-2 text-2xl font-bold">{{ array_sum($orderCounts) }}</p>
                <p class="mt-1 text-xs text-zinc-500">Đặt trong khoảng ngày đã chọn</p>
            </article>
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-zinc-500">Đơn đã giao</h2>
                <p class="mt-2 text-2xl font-bold">{{ $orderCounts[App\Enums\OrderStatus::Delivered->value] }}</p>
                <p class="mt-1 text-xs text-zinc-500">Trong số đơn đặt ở trên</p>
            </article>
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-zinc-500">Tồn kho còn bán được</h2>
                <p class="mt-2 text-2xl font-bold" data-sellable>{{ number_format($inventory['sellable'], 0, ',', '.') }} đôi</p>
                <p class="mt-1 text-xs text-zinc-500">Tồn trừ hàng đang giữ, không tính hàng cách ly · {{ $inventory['variants'] }} size</p>
            </article>
            <article class="admin-card">
                <h2 class="text-sm font-semibold text-zinc-500">Sắp hết hàng</h2>
                <p @class(['mt-2 text-2xl font-bold', 'text-red-600' => $inventory['low'] !== []])>{{ count($inventory['low']) }}</p>
                <p class="mt-1 text-xs text-zinc-500">Size còn ≤ {{ $lowStockThreshold }} đôi (tối đa 10 dòng)</p>
            </article>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="admin-card" aria-labelledby="best-title">
                <h2 id="best-title" class="text-xl font-semibold">Sản phẩm bán chạy</h2>
                <p class="mt-1 text-xs text-zinc-500">Theo số đôi đã giao thành công · tổng {{ $pairsSold }} đôi trong top</p>
                <ol class="mt-4 space-y-3">
                    @forelse ($bestSellers as $index => $row)
                        <li class="flex items-center gap-3" data-best-seller>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate font-medium">{{ $row['name'] }}</span>
                            <span class="whitespace-nowrap text-sm font-semibold">{{ $row['pairs'] }} đôi</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-zinc-500">Chưa có đơn giao thành công trong khoảng này.</li>
                    @endforelse
                </ol>
            </section>

            <section class="admin-card" aria-labelledby="low-title">
                <h2 id="low-title" class="text-xl font-semibold">Cảnh báo sắp hết hàng</h2>
                <div class="admin-table-wrap mt-4"><table class="admin-table"><thead><tr><th>Sản phẩm</th><th>Màu / size</th><th>Còn bán được</th></tr></thead><tbody>
                    @forelse ($inventory['low'] as $row)
                        <tr data-low-stock>
                            <td class="font-semibold text-zinc-900">{{ $row['product'] }}<span class="block text-xs font-normal text-zinc-500">{{ $row['sku'] }}</span></td>
                            <td>{{ $row['color'] }} · {{ $row['size'] }}</td>
                            <td><span @class(['badge', 'bg-red-100 text-red-600' => $row['sellable'] <= 0, 'bg-yellow-100 text-yellow-700' => $row['sellable'] > 0])>{{ $row['sellable'] }} đôi</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-10 text-center text-zinc-500">Không có size nào sắp hết.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        </div>

        <section class="admin-card" aria-labelledby="status-title">
            <h2 id="status-title" class="text-xl font-semibold">Đơn theo trạng thái</h2>
            <dl class="mt-4 grid gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach (App\Enums\OrderStatus::cases() as $status)
                    <div class="rounded-2xl bg-zinc-50 p-3">
                        <dt class="text-xs text-zinc-500">{{ $status->label() }}</dt>
                        <dd class="mt-1 text-lg font-bold">{{ $orderCounts[$status->value] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
@endsection
