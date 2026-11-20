@props(['orders' => [], 'paginator' => null])
<section class="space-y-6" aria-label="Lịch sử đơn hàng">
    <h2 class="text-xl font-bold text-main">Đơn hàng của tôi</h2>
    @forelse ($orders as $entry)
        @php($order = $entry['order'] ?? [])
        <article class="space-y-3 rounded-2xl border border-border bg-surface-card p-5 shadow-card transition-colors duration-150 hover:border-accent/40 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold text-main text-base">Đơn {{ $order['order_code'] ?? '—' }}</h3>
                <p class="text-xs text-muted">{{ $order['created_at'] ?? '' }}</p>
            </div>
            <p class="text-xs text-muted">Trạng thái: <span class="font-medium text-main">{{ $order['order_status'] ?? 'Chưa cập nhật' }}</span> · Thanh toán: <span class="font-medium text-main">{{ $order['payment_status'] ?? 'Chưa cập nhật' }}</span></p>
            <p class="text-base font-bold text-accent">Tổng tiền: {{ is_int($order['total_amount'] ?? null) ? number_format($order['total_amount'], 0, ',', '.').' ₫' : 'Chưa khả dụng' }}</p>
            @if ($entry['detailUrl'] ?? null)
                <div class="pt-2 border-t border-border/60">
                    <a href="{{ $entry['detailUrl'] }}" class="inline-flex items-center gap-1 text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Xem chi tiết →</a>
                </div>
            @endif
        </article>
    @empty
        <x-ui.feedback state="empty" title="Chưa có đơn hàng" />
    @endforelse
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
