@props(['orders' => [], 'paginator' => null])
<section class="space-y-6" aria-label="Lịch sử đơn hàng">
    <h2 class="text-xl font-semibold">Đơn hàng của tôi</h2>
    @forelse ($orders as $entry)
        @php($order = $entry['order'] ?? [])
        <article class="space-y-2 border border-zinc-200 p-4 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">Đơn {{ $order['order_code'] ?? '—' }}</h3>
                <p>{{ $order['created_at'] ?? '' }}</p>
            </div>
            <p>Trạng thái: {{ $order['order_status'] ?? 'Chưa cập nhật' }} · Thanh toán: {{ $order['payment_status'] ?? 'Chưa cập nhật' }}</p>
            <p>Tổng tiền: {{ is_int($order['total_amount'] ?? null) ? number_format($order['total_amount'], 0, ',', '.').' ₫' : 'Chưa khả dụng' }}</p>
            @if ($entry['detailUrl'] ?? null)<a href="{{ $entry['detailUrl'] }}" class="inline-block underline">Xem chi tiết</a>@endif
        </article>
    @empty
        <x-ui.feedback state="empty" title="Chưa có đơn hàng" />
    @endforelse
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
