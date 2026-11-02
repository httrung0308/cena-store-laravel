@extends('layouts.store')

@section('title', 'Đơn hàng của tôi')

@php($tz = (string) config('store.business_timezone'))

@section('content')
    <section class="py-10 sm:px-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-8">
            <div class="space-y-4">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Tài khoản của tôi</a>
                <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Tài khoản</p>
                <h1 class="text-4xl font-bold sm:text-5xl">Đơn hàng của tôi</h1>
            </div>
            @forelse ($orders as $order)
                <article class="rounded-2xl border-2 border-zinc-200 p-6 transition hover:border-zinc-300">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-all text-lg font-semibold">{{ $order->order_code }}</p>
                            <p class="text-sm text-zinc-500">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }} · {{ $order->items->sum('quantity') }} đôi</p>
                        </div>
                        <x-orders.status-badge :status="$order->order_status" />
                    </div>
                    <p class="mt-3 text-sm text-zinc-600">{{ $order->items->pluck('product_name_snapshot')->unique()->take(3)->join(', ') }}</p>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-lg font-bold">{{ number_format($order->total_amount, 0, ',', '.') }} ₫ <span class="text-sm font-normal text-zinc-500">· {{ $order->payment_status->label() }}</span></p>
                        <a href="{{ route('orders.show', $order->order_code) }}" class="rounded-full border border-zinc-200 px-4 py-2 text-sm font-semibold transition hover:border-zinc-900 hover:bg-zinc-900 hover:text-white">Xem chi tiết</a>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-zinc-200 p-10 text-center">
                    <p class="text-zinc-500">Chưa có đơn hàng nào.</p>
                    <a href="{{ route('shoes.index') }}" class="btn mt-5 inline-block rounded-full px-6 py-3">Bắt đầu mua sắm</a>
                </div>
            @endforelse
            <x-ui.pagination :paginator="$orders" />
        </div>
    </section>
@endsection
