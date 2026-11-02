@extends('layouts.store')

@section('title', 'Đơn '.$order->order_code)

@php
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $tz = (string) config('store.business_timezone');
    $address = $order->shipping_address_snapshot ?? [];
    $justPlaced = session('status') !== null && $order->order_status === App\Enums\OrderStatus::Pending;
@endphp

@section('content')
    <div class="mx-auto mt-10 max-w-3xl space-y-8 pb-20">
        @if ($justPlaced)
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-10 w-10 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="text-4xl font-bold">Đặt hàng thành công!</h1>
                <p class="mt-2 text-lg text-zinc-600">Cảm ơn bạn. Cửa hàng sẽ liên hệ xác nhận đơn trong vòng 24 giờ.</p>
            </div>
        @else
            <div class="space-y-4">
                <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Đơn hàng của tôi</a>
                <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Chi tiết đơn hàng</p>
                <h1 class="break-all text-3xl font-bold sm:text-4xl">{{ $order->order_code }}</h1>
                @if (session('status'))<p role="status" class="rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</p>@endif
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-600">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif

        <section class="rounded-lg border bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-2xl font-semibold">Chi tiết đơn hàng</h2>
            <div class="grid grid-cols-2 gap-4 border-b pb-4 text-sm">
                <div><p class="text-zinc-600">Mã đơn hàng</p><p class="break-all font-semibold">{{ $order->order_code }}</p></div>
                <div><p class="text-zinc-600">Ngày đặt</p><p class="font-semibold">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</p></div>
                <div><p class="text-zinc-600">Trạng thái</p><p class="font-semibold"><x-orders.status-badge :status="$order->order_status" /></p></div>
                <div><p class="text-zinc-600">Thanh toán</p><p class="font-semibold">{{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</p></div>
                <div class="col-span-2"><p class="text-zinc-600">Tổng tiền</p><p class="text-xl font-bold text-green-600">{{ $vnd($order->total_amount) }}</p></div>
            </div>
            <div class="border-b py-4 text-sm">
                <p class="font-semibold">Giao đến</p>
                <p>{{ $order->shipping_name }} · {{ $order->shipping_phone }}</p>
                <p class="text-zinc-600">{{ implode(', ', array_filter([$address['street'] ?? null, $address['ward_name'] ?? null, $address['province_name'] ?? null])) }}</p>
                @if ($order->customer_note)<p class="mt-2 whitespace-pre-wrap text-zinc-600">Ghi chú: {{ $order->customer_note }}</p>@endif
                @if ($order->cancel_reason)<p class="mt-2 text-red-600">Lý do hủy: {{ $order->cancel_reason }}</p>@endif
            </div>
            <div class="space-y-3 py-4">
                @foreach ($order->items as $item)
                    <div class="flex gap-3 border-b pb-3 last:border-b-0">
                        <img src="{{ $item->thumbnail_snapshot ?? asset('legacy/img/men.jpg') }}" alt="{{ $item->product_name_snapshot }}" class="h-20 w-20 rounded object-cover">
                        <div class="min-w-0 flex-1 break-words text-sm">
                            <p class="text-base font-semibold">{{ $item->product_name_snapshot }}</p>
                            <p class="text-zinc-600">{{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }} · SL {{ $item->quantity }} × {{ $vnd($item->unit_price) }}</p>
                            <p class="mt-1 font-semibold">{{ $vnd($item->net_amount) }}</p>
                            @stack('order-item-'.$item->id)
                        </div>
                    </div>
                @endforeach
            </div>
            <dl class="space-y-1 border-t pt-4 text-sm">
                <div class="flex justify-between"><dt>Tạm tính</dt><dd>{{ $vnd($order->subtotal_amount) }}</dd></div>
                <div class="flex justify-between"><dt>Phí vận chuyển</dt><dd>{{ $order->shipping_fee === 0 ? 'Miễn phí' : $vnd($order->shipping_fee) }}</dd></div>
                <div class="flex justify-between text-lg font-semibold"><dt>Tổng cộng</dt><dd>{{ $vnd($order->total_amount) }}</dd></div>
            </dl>
        </section>

        <section class="rounded-lg border bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-semibold">Lịch sử đơn hàng</h2>
            <ol class="space-y-3 text-sm">
                @foreach ($order->statusHistories as $history)
                    <li class="flex flex-wrap justify-between gap-2 border-b pb-2 last:border-b-0">
                        <span><x-orders.status-badge :status="$history->to_status" /> <span class="ml-2 text-zinc-600">{{ $history->actor_id === $order->user_id ? 'Bạn' : 'Cửa hàng' }}</span></span>
                        <span class="text-zinc-500">{{ $history->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        @stack('order-actions')

        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ route('orders.index') }}" class="rounded-full border border-zinc-900 px-6 py-3 font-semibold transition hover:bg-zinc-900 hover:text-white">Xem đơn hàng của tôi</a>
            <a href="{{ route('shoes.index') }}" class="btn rounded-full px-6 py-3">Tiếp tục mua sắm</a>
        </div>
    </div>
@endsection
