@extends('layouts.store')

@section('title', 'Thanh toán đơn '.$order->order_code)

@php($vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫')

@section('content')
    <div class="mx-auto mt-12 max-w-xl space-y-6 rounded-lg border bg-white p-8 shadow-sm">
        <div class="text-center">
            <p class="text-sm uppercase tracking-wide text-zinc-500">Thanh toán mô phỏng</p>
            <h1 class="mt-2 text-2xl font-bold">Đơn {{ $order->order_code }}</h1>
            <p class="mt-6 text-sm text-zinc-600">Số tiền của đơn hàng</p>
            <p class="mt-1 text-3xl font-bold">{{ $vnd($order->total_amount) }}</p>
            <p class="mt-3 text-xs text-zinc-500">Đây là màn hình giả lập. Không có giao dịch tiền thật.</p>
        </div>
        @if ($errors->any())<div role="alert" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if ($order->payment_status === App\Enums\OrderPaymentStatus::Paid)
            <div role="status" class="rounded bg-green-50 p-4 text-center font-semibold text-green-800">Đơn đã được đánh dấu đã thanh toán.</div>
        @elseif ($order->order_status === App\Enums\OrderStatus::Cancelled)
            <div role="status" class="rounded bg-amber-50 p-4 text-center text-amber-800">Đơn đã hủy, không thể thanh toán.</div>
        @else
            @php($latestAttempt = $order->payments->first())
            @if ($latestAttempt?->status === App\Enums\PaymentAttemptStatus::Failed)
                <form method="POST" action="{{ route('orders.payment.retry', $order->order_code) }}">@csrf<button class="btn w-full py-3">Thử thanh toán lại</button></form>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('orders.payment.complete', $order->order_code) }}">@csrf<input type="hidden" name="result" value="success"><button class="btn w-full py-3">Thành công</button></form>
                    <form method="POST" action="{{ route('orders.payment.complete', $order->order_code) }}">@csrf<input type="hidden" name="result" value="failure"><button class="btn w-full py-3">Thất bại</button></form>
                </div>
            @endif
        @endif
        <a class="block text-center text-sm underline" href="{{ route('orders.show', $order->order_code) }}">Quay lại chi tiết đơn</a>
    </div>
@endsection
