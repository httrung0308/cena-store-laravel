@props(['order' => [], 'items' => [], 'allowedActions' => []])
@php
    // The order model casts shipping_address_snapshot as JSON. Read only approved
    // display fields; never serialize an arbitrary snapshot or query live addresses.
    $snapshot = $order['shipping_address_snapshot'] ?? null;
    if ($snapshot instanceof \stdClass) {
        $snapshot = get_object_vars($snapshot);
    }
    if (is_array($snapshot)) {
        $addressParts = [];
        foreach ([
            ['street_address', 'street'],
            ['legacy_district_text', 'legacy_text'],
            ['ward_name_snapshot', 'ward_name'],
            ['province_name_snapshot', 'province_name'],
        ] as $aliases) {
            foreach ($aliases as $key) {
                $part = $snapshot[$key] ?? null;
                if (is_string($part) && trim($part) !== '') {
                    $addressParts[] = $part;
                    break;
                }
            }
        }
        $addressDisplay = implode(', ', $addressParts);
    } else {
        $addressDisplay = is_string($snapshot) ? $snapshot : '';
    }
@endphp
<section class="space-y-6" aria-label="Chi tiết đơn hàng">
    <header class="space-y-2 rounded-2xl border border-border bg-surface-card p-6 shadow-card">
        <h2 class="text-xl font-bold font-serif text-main">Đơn {{ $order['order_code'] ?? '' }}</h2>
        <p class="text-sm text-muted">Trạng thái: <span class="font-medium text-main">{{ $order['order_status'] ?? '—' }}</span> · Thanh toán: <span class="font-medium text-main">{{ $order['payment_status'] ?? '—' }}</span></p>
        <p class="text-sm text-muted">Người nhận: <span class="font-medium text-main">{{ $order['shipping_name'] ?? '' }} · {{ $order['shipping_phone'] ?? '' }}</span></p>
        <p class="text-sm text-muted">Địa chỉ: <span class="text-main">{{ $addressDisplay !== '' ? $addressDisplay : 'Chưa có địa chỉ' }}</span></p>
        <p class="text-lg font-bold text-accent pt-2">Tổng cộng: {{ is_int($order['total_amount'] ?? null) ? number_format($order['total_amount'], 0, ',', '.').' ₫' : 'Chưa khả dụng' }}</p>
    </header>
    <div class="space-y-4">
        @forelse ($items as $item)
            <article class="rounded-2xl border border-border bg-surface-card p-5 shadow-subtle">
                <h3 class="font-semibold text-main text-base">{{ $item['product_name_snapshot'] ?? 'Sản phẩm' }}</h3>
                <p class="text-sm text-muted mt-1">{{ $item['color_name_snapshot'] ?? '' }} · Size {{ $item['size_value_snapshot'] ?? '' }} · Số lượng {{ $item['quantity'] ?? '' }}</p>
                <p class="text-sm font-bold text-accent mt-1">Thành tiền: {{ is_int($item['net_amount'] ?? null) ? number_format($item['net_amount'], 0, ',', '.').' ₫' : 'Chưa có' }}</p>
                @foreach ($item['units'] ?? [] as $unit)
                    <p class="mt-3 border-t border-border/60 pt-2 text-xs text-muted">Đôi số {{ $unit['unit_no'] ?? '' }} · Trạng thái {{ $unit['state'] ?? '—' }}
                        @if (isset($unit['size_value'])) · Size hiện tại {{ $unit['size_value'] }} @endif
                    </p>
                @endforeach
            </article>
        @empty<x-ui.feedback state="empty" title="Không có dòng sản phẩm" />@endforelse
    </div>
</section>
