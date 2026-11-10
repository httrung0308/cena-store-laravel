@extends('layouts.admin')
@section('title', 'Chi tiết đơn hàng')
@section('eyebrow', 'Quản lý đơn hàng')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.orders.index') }}">Danh sách đơn</a>
@endsection
@use('App\Enums\OrderStatus')
@php
    $tz = (string) config('store.business_timezone');
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $address = $order->shipping_address_snapshot ?? [];
    $status = $order->order_status;
    $next = match ($status) {
        OrderStatus::Pending => ['confirm' => 'Xác nhận và đóng gói'],
        OrderStatus::Confirmed => ['pack' => 'Bắt đầu đóng gói'],
        default => [],
    };
    $cancellable = in_array($status, [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Packing], true);
@endphp
@section('content')
<div class="grid min-w-0 gap-6 lg:grid-cols-3">
    @if ($errors->any())<div role="alert" class="admin-card text-sm text-red-700 lg:col-span-3">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="min-w-0 space-y-6 lg:col-span-2">
        <section class="admin-card space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="break-all text-lg font-semibold">{{ $order->order_code }}</p>
                <x-orders.status-badge :status="$status" />
            </div>
            <div class="grid gap-2 text-sm text-zinc-500 sm:grid-cols-2">
                <p>Ngày đặt: <span class="font-medium text-zinc-900">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>
                <p>Thanh toán: <span class="font-medium text-zinc-900">{{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</span></p>
                <p>Khách hàng: <span class="font-medium text-zinc-900">{{ $order->user?->full_name }}</span> <span class="break-all">({{ $order->user?->email }})</span></p>
                @if ($status === OrderStatus::Pending && $order->payment_expires_at)<p>Hạn xác nhận: <span class="font-medium text-zinc-900">{{ $order->payment_expires_at->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>@endif
            </div>
            <div class="rounded-xl bg-zinc-50 p-3 text-sm">
                <p class="font-semibold">Giao đến: {{ $order->shipping_name }} · {{ $order->shipping_phone }}</p>
                <p class="text-zinc-600">{{ implode(', ', array_filter([$address['street'] ?? null, $address['ward_name'] ?? null, $address['province_name'] ?? null])) }}</p>
                @if ($order->customer_note)<p class="mt-2 whitespace-pre-wrap text-zinc-600">Ghi chú của khách: {{ $order->customer_note }}</p>@endif
                @if ($order->cancel_reason)<p class="mt-2 text-red-600">Lý do: {{ $order->cancel_reason }}</p>@endif
            </div>
        </section>

        <div class="admin-table-wrap"><table class="admin-table min-w-[560px]"><thead><tr><th>Sản phẩm</th><th>SKU</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="min-w-[12rem]"><span class="font-semibold text-zinc-900">{{ $item->product_name_snapshot }}</span><span class="block text-xs text-zinc-500">{{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }}</span>
                        <span class="mt-1 block text-xs text-zinc-500">@foreach ($item->units as $unit)Đôi #{{ $unit->unit_no }}: {{ $unit->state->label() }}@if (! $loop->last) · @endif @endforeach</span></td>
                    <td class="whitespace-nowrap">{{ $item->sku_snapshot }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="whitespace-nowrap">{{ $vnd($item->unit_price) }}</td>
                    <td class="whitespace-nowrap font-semibold text-zinc-900">{{ $vnd($item->net_amount) }}</td>
                </tr>
            @endforeach
        </tbody></table></div>
        <dl class="admin-card space-y-1 text-sm">
            <div class="flex justify-between"><dt>Tạm tính</dt><dd>{{ $vnd($order->subtotal_amount) }}</dd></div>
            @if ($order->merchandise_discount_amount > 0)<div class="flex justify-between"><dt>Coupon {{ $order->coupon_code_snapshot }}</dt><dd>−{{ $vnd($order->merchandise_discount_amount) }}</dd></div>@endif
            @if ($order->shipping_discount_amount > 0)<div class="flex justify-between"><dt>Giảm vận chuyển</dt><dd>−{{ $vnd($order->shipping_discount_amount) }}</dd></div>@endif
            <div class="flex justify-between"><dt>Phí vận chuyển</dt><dd>{{ $order->shipping_fee === 0 ? 'Miễn phí' : $vnd($order->shipping_fee) }}</dd></div>
            <div class="flex justify-between text-lg font-semibold"><dt>Tổng cộng</dt><dd>{{ $vnd($order->total_amount) }}</dd></div>
            <div class="flex justify-between text-zinc-500"><dt>Đã thu</dt><dd>{{ $vnd($order->paid_amount) }}</dd></div>
        </dl>

        <section class="admin-card">
            <h2 class="mb-3 text-xl font-semibold">Lịch sử trạng thái</h2>
            <ol class="space-y-2 text-sm">
                @foreach ($order->statusHistories as $history)
                    <li class="flex flex-wrap justify-between gap-2 border-b border-zinc-100 pb-2 last:border-b-0">
                        <span><x-orders.status-badge :status="$history->to_status" /> <span class="ml-2 text-zinc-600">{{ $history->actor_name_snapshot }}</span></span>
                        <span class="text-zinc-500">{{ $history->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <aside class="min-w-0 space-y-6">
        <section class="admin-card space-y-4">
            <h2 class="text-xl font-semibold">Xử lý đơn</h2>
            @foreach ($next as $action => $label)
                <form method="POST" action="{{ route('admin.orders.transition', [$order, $action]) }}">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <button class="btn-pill-dark w-full py-3">{{ $label }}</button>
                </form>
            @endforeach
            @if ($status === OrderStatus::Packing)
                <form method="POST" action="{{ route('admin.orders.transition', [$order, 'ship']) }}" class="space-y-3">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <x-admin.field name="carrier_name" label="Đơn vị vận chuyển" required placeholder="Ví dụ: GHN" />
                    <x-admin.field name="tracking_code" label="Mã vận đơn" placeholder="Không bắt buộc" />
                    <button class="btn-pill-dark w-full py-3">Giao cho vận chuyển (xuất kho)</button>
                </form>
            @endif
            @if ($status === OrderStatus::OnDelivery)
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.orders.transition', [$order, 'deliver']) }}" class="space-y-3">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    @if ($order->payment_method === App\Enums\PaymentMethod::Cod)
                        <x-admin.field name="proof" type="file" label="Ảnh minh chứng giao COD" required accept="image/jpeg,image/png,image/webp" hint="JPEG, PNG hoặc WebP; tối đa 5 MB. Ảnh được lưu riêng tư." />
                    @endif
                    <button class="btn-pill-dark w-full py-3">Xác nhận giao thành công</button>
                </form>
                <form method="POST" action="{{ route('admin.orders.transition', [$order, 'fail']) }}" class="space-y-2 border-t border-zinc-100 pt-4">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <label for="fail-reason" class="admin-label">Lý do giao thất bại</label>
                    <input id="fail-reason" name="reason" class="input w-full border-zinc-300" required minlength="5" maxlength="500">
                    <button class="btn-pill w-full text-red-600">Báo giao thất bại</button>
                </form>
            @endif
            @if ($cancellable)
                <form method="POST" action="{{ route('admin.orders.transition', [$order, 'cancel']) }}" class="space-y-2 border-t border-zinc-100 pt-4">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <label for="cancel-reason" class="admin-label">Lý do hủy</label>
                    <input id="cancel-reason" name="reason" class="input w-full border-zinc-300" required minlength="5" maxlength="500">
                    <button class="btn-pill w-full text-red-600">Hủy đơn (nhả hàng đang giữ)</button>
                </form>
            @endif
            @if ($next === [] && ! $cancellable && ! in_array($status, [OrderStatus::Packing, OrderStatus::OnDelivery], true))
                <p class="text-sm text-zinc-500">Đơn đã kết thúc, không còn thao tác.</p>
            @endif
        </section>

        <section class="admin-card space-y-2 text-sm">
            <h2 class="text-lg font-semibold">Vận chuyển & thanh toán</h2>
            @if ($order->shipment)
                <p>Đơn vị: <span class="font-medium">{{ $order->shipment->carrier_name }}</span>@if ($order->shipment->tracking_code) · {{ $order->shipment->tracking_code }}@endif</p>
                <p>Trạng thái giao: <span class="font-medium">{{ $order->shipment->status->label() }}</span></p>
                @if ($order->shipment->deliveryProof)
                    <a class="text-sm font-semibold underline" href="{{ route('admin.orders.delivery-proof', $order) }}" target="_blank" rel="noopener">Xem ảnh minh chứng giao COD</a>
                @endif
            @else
                <p class="text-zinc-500">Chưa bàn giao vận chuyển.</p>
            @endif
            @foreach ($order->payments as $payment)
                <p>{{ $payment->method->label() }}: <span class="font-medium">{{ $payment->status->label() }}</span>@if ($payment->provider_transaction_id && $payment->method !== App\Enums\PaymentMethod::PrepaidDemo) · {{ $payment->provider_transaction_id }}@endif</p>
            @endforeach
            @foreach ($order->refunds as $refund)
                <p>Hoàn tiền mô phỏng: <span class="font-medium">{{ $refund->status === App\Enums\RefundStatus::Approved ? 'Đã hoàn (mô phỏng)' : $refund->status->label() }}</span> · {{ $vnd($refund->amount) }}</p>
                @if ($refund->status === App\Enums\RefundStatus::Requested && auth()->user()->hasRole(App\Enums\RoleSlug::Admin))
                    <form method="POST" action="{{ route('admin.orders.refunds.approve', [$order, $refund]) }}">@csrf<button class="btn-pill w-full text-amber-800">Duyệt hoàn tiền mô phỏng</button></form>
                @endif
            @endforeach
        </section>
    </aside>
</div>
@endsection
