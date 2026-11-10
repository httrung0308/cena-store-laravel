@extends('layouts.admin')
@section('title', 'Chi Tiết Đơn Hàng')
@section('eyebrow', 'QUẢN TRỊ BÁN HÀNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.orders.index') }}">← Danh sách đơn</a>
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
                <p class="break-all text-lg font-semibold text-main">{{ $order->order_code }}</p>
                <x-orders.status-badge :status="$status" />
            </div>
            <div class="grid gap-2 text-sm text-muted sm:grid-cols-2">
                <p>Ngày đặt: <span class="font-medium text-main">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>
                <p>Thanh toán: <span class="font-medium text-main">{{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</span></p>
                <p>Khách hàng: <span class="font-medium text-main">{{ $order->user?->full_name }}</span> <span class="break-all">({{ $order->user?->email }})</span></p>
                @if ($status === OrderStatus::Pending && $order->payment_expires_at)<p>Hạn xác nhận: <span class="font-medium text-main">{{ $order->payment_expires_at->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>@endif
            </div>
            <div class="rounded-xl bg-surface-alt/60 border border-border/50 p-3 text-sm">
                <p class="font-semibold text-main">Giao đến: {{ $order->shipping_name }} · {{ $order->shipping_phone }}</p>
                <p class="text-muted">{{ implode(', ', array_filter([$address['street'] ?? null, $address['ward_name'] ?? null, $address['province_name'] ?? null])) }}</p>
                @if ($order->customer_note)<p class="mt-2 whitespace-pre-wrap text-muted">Ghi chú của khách: {{ $order->customer_note }}</p>@endif
                @if ($order->cancel_reason)<p class="mt-2 text-error font-medium">Lý do: {{ $order->cancel_reason }}</p>@endif
            </div>
        </section>

        <div class="admin-table-wrap"><table class="admin-table min-w-[560px]"><thead><tr><th>Sản phẩm</th><th>SKU</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>
            @foreach ($order->items as $item)
                <tr class="hover:bg-surface-alt/40 transition-colors">
                    <td class="min-w-[12rem]"><span class="font-semibold text-main">{{ $item->product_name_snapshot }}</span><span class="block text-xs text-muted">{{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }}</span>
                        <span class="mt-1 block text-xs text-muted">@foreach ($item->units as $unit)Đôi #{{ $unit->unit_no }}: {{ $unit->state->label() }}@if (! $loop->last) · @endif @endforeach</span></td>
                    <td class="whitespace-nowrap text-main">{{ $item->sku_snapshot }}</td>
                    <td class="text-main">{{ $item->quantity }}</td>
                    <td class="whitespace-nowrap text-main">{{ $vnd($item->unit_price) }}</td>
                    <td class="whitespace-nowrap font-semibold text-main">{{ $vnd($item->net_amount) }}</td>
                </tr>
            @endforeach
        </tbody></table></div>
        <dl class="admin-card space-y-1 text-sm">
        <dl class="admin-card space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Tạm tính</dt><dd class="text-main">{{ $vnd($order->subtotal_amount) }}</dd></div>
            @if ($order->merchandise_discount_amount > 0)<div class="flex justify-between"><dt class="text-muted">Coupon {{ $order->coupon_code_snapshot }}</dt><dd class="text-accent font-medium">−{{ $vnd($order->merchandise_discount_amount) }}</dd></div>@endif
            @if ($order->shipping_discount_amount > 0)<div class="flex justify-between"><dt class="text-muted">Giảm vận chuyển</dt><dd class="text-accent font-medium">−{{ $vnd($order->shipping_discount_amount) }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-muted">Phí vận chuyển</dt><dd class="text-main">{{ $order->shipping_fee === 0 ? 'Miễn phí' : $vnd($order->shipping_fee) }}</dd></div>
            <div class="flex justify-between text-lg font-semibold"><dt class="text-main">Tổng cộng</dt><dd class="text-main font-bold">{{ $vnd($order->total_amount) }}</dd></div>
            <div class="flex justify-between text-muted"><dt>Đã thu</dt><dd>{{ $vnd($order->paid_amount) }}</dd></div>
        </dl>
        </dl>

        <section class="admin-card">
            <h2 class="mb-3 text-xl font-semibold text-main">Lịch sử trạng thái</h2>
            <ol class="space-y-2 text-sm">
                @foreach ($order->statusHistories as $history)
                    <li class="flex flex-wrap justify-between gap-2 border-b border-border pb-2 last:border-b-0">
                        <span><x-orders.status-badge :status="$history->to_status" /> <span class="ml-2 text-muted">{{ $history->actor_name_snapshot }}</span></span>
                        <span class="text-muted">{{ $history->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <aside class="min-w-0 space-y-6">
        <section class="admin-card space-y-4">
            <h2 class="text-xl font-semibold text-main">Xử lý đơn</h2>
            @foreach ($next as $action => $label)
                <form method="POST" action="{{ route('admin.orders.transition', [$order, $action]) }}" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <button :disabled="busy" class="btn-pill-dark w-full py-3 disabled:opacity-50">
                        <span x-show="!busy">{{ $label }}</span>
                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                    </button>
                </form>
            @endforeach
            @if ($status === OrderStatus::Packing)
                <form method="POST" action="{{ route('admin.orders.transition', [$order, 'ship']) }}" class="space-y-3" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <x-admin.field name="carrier_name" label="Đơn vị vận chuyển" required placeholder="Ví dụ: GHN" />
                    <x-admin.field name="tracking_code" label="Mã vận đơn" placeholder="Không bắt buộc" />
                    <button :disabled="busy" class="btn-pill-dark w-full py-3 disabled:opacity-50">
                        <span x-show="!busy">Giao cho vận chuyển (xuất kho)</span>
                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xuất kho...</span>
                    </button>
                </form>
            @endif
            @if ($status === OrderStatus::OnDelivery)
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.orders.transition', [$order, 'deliver']) }}" class="space-y-3" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    @if ($order->payment_method === App\Enums\PaymentMethod::Cod)
                        <x-admin.field name="proof" type="file" label="Ảnh minh chứng giao COD" required accept="image/jpeg,image/png,image/webp" hint="JPEG, PNG hoặc WebP; tối đa 5 MB. Ảnh được lưu riêng tư." />
                    @endif
                    <button :disabled="busy" class="btn-pill-dark w-full py-3 disabled:opacity-50">
                        <span x-show="!busy">Xác nhận giao thành công</span>
                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang ghi nhận...</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.orders.transition', [$order, 'fail']) }}" class="space-y-2 border-t border-border pt-4">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <label for="fail-reason" class="admin-label text-muted">Lý do giao thất bại</label>
                    <input id="fail-reason" name="reason" class="input w-full border-border bg-surface text-main" required minlength="5" maxlength="500">
                    <button class="btn-pill w-full text-error hover:bg-error-bg">Báo giao thất bại</button>
                </form>
            @endif
            @if ($cancellable)
                <form method="POST" action="{{ route('admin.orders.transition', [$order, 'cancel']) }}" class="space-y-2 border-t border-border pt-4">@csrf
                    <input type="hidden" name="expected_status" value="{{ $status->value }}">
                    <label for="cancel-reason" class="admin-label text-muted">Lý do hủy</label>
                    <input id="cancel-reason" name="reason" class="input w-full border-border bg-surface text-main" required minlength="5" maxlength="500">
                    <button class="btn-pill w-full text-error hover:bg-error-bg">Hủy đơn (nhả hàng đang giữ)</button>
                </form>
            @endif
            @if ($next === [] && ! $cancellable && ! in_array($status, [OrderStatus::Packing, OrderStatus::OnDelivery], true))
                <p class="text-sm text-muted">Đơn đã kết thúc, không còn thao tác.</p>
            @endif
        </section>

        <section class="admin-card space-y-2 text-sm">
            <h2 class="text-lg font-semibold text-main">Vận chuyển & thanh toán</h2>
            @if ($order->shipment)
                <p class="text-main">Đơn vị: <span class="font-medium">{{ $order->shipment->carrier_name }}</span>@if ($order->shipment->tracking_code) · {{ $order->shipment->tracking_code }}@endif</p>
                <p class="text-main">Trạng thái giao: <span class="font-medium">{{ $order->shipment->status->label() }}</span></p>
                @if ($order->shipment->deliveryProof)
                    <a class="text-xs font-semibold text-accent underline hover:text-accent-hover inline-block mt-1" href="{{ route('admin.orders.delivery-proof', $order) }}" target="_blank" rel="noopener">Xem ảnh minh chứng giao COD</a>
                @endif
            @else
                <p class="text-muted">Chưa bàn giao vận chuyển.</p>
            @endif
            @foreach ($order->payments as $payment)
                <p class="text-main">{{ $payment->method->label() }}: <span class="font-medium">{{ $payment->status->label() }}</span>@if ($payment->provider_transaction_id && $payment->method !== App\Enums\PaymentMethod::PrepaidDemo) · {{ $payment->provider_transaction_id }}@endif</p>
            @endforeach
            @foreach ($order->refunds as $refund)
                <p class="text-main">Hoàn tiền mô phỏng: <span class="font-medium">{{ $refund->status === App\Enums\RefundStatus::Approved ? 'Đã hoàn (mô phỏng)' : $refund->status->label() }}</span> · {{ $vnd($refund->amount) }}</p>
                @if ($refund->status === App\Enums\RefundStatus::Requested && auth()->user()->hasRole(App\Enums\RoleSlug::Admin))
                    <form method="POST" action="{{ route('admin.orders.refunds.approve', [$order, $refund]) }}">
                        @csrf
                        <button class="btn-pill w-full text-warning-text hover:bg-warning-bg mt-1">Duyệt hoàn tiền mô phỏng</button>
                    </form>
                @endif
            @endforeach
        </section>
    </aside>
</div>
@endsection
