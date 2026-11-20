@extends('layouts.store')

@section('title', 'Đơn hàng của tôi')

@php($tz = (string) config('store.business_timezone'))

@section('content')
    <section class="py-6 sm:py-10">
        <div class="mx-auto flex max-w-4xl flex-col gap-8">
            <div class="space-y-3">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Tài khoản của tôi</span>
                </a>
                <h1 class="text-2xl sm:text-4xl font-extrabold text-main tracking-tight">Đơn hàng của tôi</h1>
            </div>
            @forelse ($orders as $order)
                <article class="rounded-2xl border border-border bg-surface-card p-6 shadow-card transition-colors duration-150 hover:border-accent/40">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="break-all text-base font-bold text-main sm:text-lg">{{ $order->order_code }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }} · {{ $order->items->sum('quantity') }} đôi</p>
                        </div>
                        <x-orders.status-badge :status="$order->order_status" />
                    </div>
                    <p class="mt-3 text-sm text-muted leading-relaxed">{{ $order->items->pluck('product_name_snapshot')->unique()->take(3)->join(', ') }}</p>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-4">
                        <p class="text-base font-bold text-accent sm:text-lg">{{ number_format($order->total_amount, 0, ',', '.') }} ₫ <span class="text-xs font-normal text-muted">· {{ $order->payment_status->label() }}</span></p>
                        <a href="{{ route('orders.show', $order->order_code) }}" class="btn-secondary px-5 py-2 text-xs font-semibold hover:border-accent hover:text-accent">Xem chi tiết</a>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-border bg-surface/40 p-12 text-center">
                    <p class="text-sm text-muted">Chưa có đơn hàng nào.</p>
                    <a href="{{ route('shoes.index') }}" class="btn mt-4 inline-block px-6 py-2.5 text-sm">Bắt đầu mua sắm</a>
                </div>
            @endforelse
            <x-ui.pagination :paginator="$orders" />
        </div>
    </section>
@endsection
