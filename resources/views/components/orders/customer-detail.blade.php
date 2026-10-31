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
    <header class="space-y-2"><h2 class="text-xl font-semibold">Đơn {{ $order['order_code'] ?? '' }}</h2>
        <p class="text-sm">Trạng thái {{ $order['order_status'] ?? '—' }} · Thanh toán {{ $order['payment_status'] ?? '—' }}</p>
        <p class="text-sm">Người nhận: {{ $order['shipping_name'] ?? '' }} · {{ $order['shipping_phone'] ?? '' }}</p>
        <p class="text-sm">Địa chỉ: {{ $addressDisplay !== '' ? $addressDisplay : 'Chưa có địa chỉ' }}</p>
        <p class="font-semibold">Tổng cộng: {{ is_int($order['total_amount'] ?? null) ? number_format($order['total_amount'], 0, ',', '.').' ₫' : 'Chưa khả dụng' }}</p>
    </header>
    <div class="space-y-4">
        @forelse ($items as $item)
            <article class="border border-zinc-200 p-4">
                <h3 class="font-semibold">{{ $item['product_name_snapshot'] ?? 'Sản phẩm' }}</h3>
                <p class="text-sm">{{ $item['color_name_snapshot'] ?? '' }} · Size {{ $item['size_value_snapshot'] ?? '' }} · Số lượng {{ $item['quantity'] ?? '' }}</p>
                <p class="text-sm">Thành tiền: {{ is_int($item['net_amount'] ?? null) ? number_format($item['net_amount'], 0, ',', '.').' ₫' : 'Chưa có' }}</p>
                @foreach ($item['units'] ?? [] as $unit)
                    <p class="mt-2 border-t pt-2 text-sm">Đôi số {{ $unit['unit_no'] ?? '' }} · Trạng thái {{ $unit['state'] ?? '—' }}
                        @if (isset($unit['size_value'])) · Size hiện tại {{ $unit['size_value'] }} @endif
                    </p>
                @endforeach
            </article>
        @empty<x-ui.feedback state="empty" title="Không có dòng sản phẩm" />@endforelse
    </div>
    <nav class="flex gap-4">
        @foreach (['reviewUrl' => 'Đánh giá', 'rmaUrl' => 'Yêu cầu hậu mãi'] as $key => $text)
            @if (is_string($allowedActions[$key] ?? null) && $allowedActions[$key] !== '')<a class="text-sm underline" href="{{ $allowedActions[$key] }}">{{ $text }}</a>@endif
        @endforeach
    </nav>
</section>
