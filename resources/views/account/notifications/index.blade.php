@extends('layouts.store')

@section('title', 'Thông báo')

@php($tz = (string) config('store.business_timezone'))

@section('content')
    <section class="py-6 sm:py-10">
        <div class="mx-auto flex max-w-4xl flex-col gap-8">
            <div class="space-y-3">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Tài khoản của tôi</span>
                </a>
                <h1 class="text-2xl font-extrabold tracking-tight text-main sm:text-4xl">Thông báo</h1>
            </div>

            @if (session('status'))
                <p role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</p>
            @endif

            @forelse ($notifications as $notification)
                <article @class(['rounded-2xl border bg-surface-card p-5 shadow-card sm:p-6', 'border-accent/40' => $notification->read_at === null, 'border-border' => $notification->read_at !== null])>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Cập nhật đơn hàng</p>
                            <p class="mt-1 text-sm leading-relaxed text-main">Đơn <span class="font-bold">{{ $notification->order->order_code }}</span> chuyển sang trạng thái <span class="font-semibold">{{ $notification->order_status->label() }}</span>.</p>
                            <p class="mt-2 text-xs text-muted">{{ $notification->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                        </div>
                        @if ($notification->read_at === null)
                            <span class="rounded-full bg-accent-sub px-2.5 py-1 text-xs font-semibold text-accent-sub-text">Chưa đọc</span>
                        @else
                            <span class="rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-muted">Đã đọc</span>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-border/60 pt-4">
                        <a href="{{ route('orders.show', $notification->order->order_code) }}" class="btn-secondary px-4 py-2 text-xs font-semibold">Xem đơn hàng</a>
                        @if ($notification->read_at === null)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="px-2 py-2 text-xs font-semibold text-muted transition-colors hover:text-accent">Đánh dấu đã đọc</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-border bg-surface/40 p-12 text-center">
                    <p class="text-sm text-muted">Bạn chưa có thông báo nào.</p>
                </div>
            @endforelse

            <x-ui.pagination :paginator="$notifications" />
        </div>
    </section>
@endsection
