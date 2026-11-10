@extends('layouts.store')

@section('title', 'Thanh toán đơn '.$order->order_code)

@php($vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫')

@section('content')
    <div class="mx-auto mt-8 max-w-xl space-y-6 rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:mt-12 sm:p-8">
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Thanh toán mô phỏng</p>
            <h1 class="mt-2 text-2xl font-extrabold text-main sm:text-3xl tracking-tight">Đơn {{ $order->order_code }}</h1>
            <p class="mt-5 text-xs text-muted">Số tiền cần thanh toán</p>
            <p class="mt-1 text-3xl font-extrabold text-accent sm:text-4xl">{{ $vnd($order->total_amount) }}</p>
            <p class="mt-3 text-xs text-muted leading-relaxed">Đây là màn hình giả lập phục vụ thử nghiệm tính năng. Không có giao dịch tiền thật.</p>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs font-medium text-error-text shadow-subtle">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @if ($order->payment_status === App\Enums\OrderPaymentStatus::Paid)
            <div role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-center text-sm font-semibold text-success-text shadow-subtle">
                Đơn hàng đã được đánh dấu là đã thanh toán thành công.
            </div>
        @elseif ($order->order_status === App\Enums\OrderStatus::Cancelled)
            <div role="status" class="rounded-xl border border-warning/30 bg-warning-bg p-4 text-center text-sm font-semibold text-warning-text shadow-subtle">
                Đơn hàng đã hủy, không thể tiếp tục thanh toán.
            </div>
        @else
            @php($latestAttempt = $order->payments->first())
            @if ($latestAttempt?->status === App\Enums\PaymentAttemptStatus::Failed)
                <form method="POST" action="{{ route('orders.payment.retry', $order->order_code) }}">
                    @csrf
                    <button class="btn w-full py-3 text-sm font-semibold shadow-card">Thử thanh toán lại</button>
                </form>
            @else
                <div class="grid gap-3 sm:grid-cols-2" x-data="{ paying: false }">
                    <form method="POST" action="{{ route('orders.payment.complete', $order->order_code) }}" x-on:submit="if (paying) { $event.preventDefault(); return false; } paying = true">
                        @csrf
                        <input type="hidden" name="result" value="success">
                        <button :disabled="paying" class="btn w-full py-3 text-sm font-semibold shadow-card disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!paying">Mô phỏng thành công</span>
                            <span x-cloak x-show="paying" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('orders.payment.complete', $order->order_code) }}" x-on:submit="if (paying) { $event.preventDefault(); return false; } paying = true">
                        @csrf
                        <input type="hidden" name="result" value="failure">
                        <button :disabled="paying" class="btn-pill w-full py-3 text-sm font-semibold border-error/40 text-error hover:bg-error-bg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!paying">Mô phỏng thất bại</span>
                            <span x-cloak x-show="paying" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                        </button>
                    </form>
                </div>
            @endif
        @endif
        <div class="pt-2 text-center">
            <a class="inline-flex items-center gap-1 text-xs font-semibold text-muted hover:text-accent transition-colors" href="{{ route('orders.show', $order->order_code) }}">
                <span>← Quay lại chi tiết đơn hàng</span>
            </a>
        </div>
    </div>
@endsection
