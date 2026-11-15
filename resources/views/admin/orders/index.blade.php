@extends('layouts.admin')
@section('title', 'Quản Lý Đơn Hàng')
@section('eyebrow', 'QUẢN TRỊ BÁN HÀNG')
@php
    $tz = (string) config('store.business_timezone');
    $activeChips = [];
    if (filled($filters['search'] ?? null)) {
        $activeChips[] = [
            'label' => 'Từ khóa: "'.$filters['search'].'"',
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($filters['status'] ?? null)) {
        $activeChips[] = [
            'label' => 'Trạng thái: '.App\Enums\OrderStatus::from($filters['status'])->label(),
            'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null]),
        ];
    }
    if (filled($filters['payment_status'] ?? null)) {
        $activeChips[] = [
            'label' => 'Thanh toán: '.App\Enums\OrderPaymentStatus::from($filters['payment_status'])->label(),
            'url' => request()->fullUrlWithQuery(['payment_status' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
    <div x-data="adminAjaxList()" class="space-y-6">
        <x-admin.search-form
            :action="route('admin.orders.index')"
            id="order-search"
            label="Tìm đơn hàng"
            placeholder="Tìm kiếm theo mã đơn, người nhận, số điện thoại..."
            :value="$filters['search'] ?? ''"
            :ajax="true"
            :active-chips="$activeChips"
        >
            <x-slot:filters>
                <x-admin.filter-select id="order-status-filter" name="status" label="Trạng thái:" :active="filled($filters['status'] ?? null)">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\OrderStatus::cases() as $state)
                        <option value="{{ $state->value }}" @selected(($filters['status'] ?? '') === $state->value)>{{ $state->label() }}</option>
                    @endforeach
                </x-admin.filter-select>

                <x-admin.filter-select id="order-payment-status-filter" name="payment_status" label="Thanh toán:" :active="filled($filters['payment_status'] ?? null)">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\OrderPaymentStatus::cases() as $paymentState)
                        <option value="{{ $paymentState->value }}" @selected(($filters['payment_status'] ?? '') === $paymentState->value)>{{ $paymentState->label() }}</option>
                    @endforeach
                </x-admin.filter-select>
            </x-slot:filters>
        </x-admin.search-form>

        <div id="admin-list-region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none transition-opacity': listLoading }" class="space-y-4">
            <div class="flex items-center justify-between px-1 text-xs text-muted">
                <span>Tổng cộng <strong class="font-bold text-main">{{ number_format($orders->total()) }}</strong> đơn hàng</span>
                @if ($orders->hasPages())
                    <span>Trang {{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
                @endif
            </div>

            <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
                {{-- Mobile summary cards --}}
                <div class="divide-y divide-border md:hidden">
                    @forelse ($orders as $order)
                        @php
                            $firstItem = $order->items->first();
                            $totalPairs = $order->items->sum('quantity');
                            $extraItems = max(0, $order->items->count() - 1);
                        @endphp
                        <article class="p-4 space-y-2.5 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-bold text-accent hover:underline break-all">{{ $order->order_code }}</a>
                                <x-orders.status-badge :status="$order->order_status" />
                            </div>
                            <div>
                                <p class="font-bold text-main">{{ $order->shipping_name }}</p>
                                <p class="text-[11px] text-muted">{{ $order->shipping_phone }}</p>
                            </div>
                            @if ($firstItem)
                                <p class="text-[11px] text-muted truncate">
                                    {{ $firstItem->product_name_snapshot }}@if ($extraItems > 0) (+{{ $extraItems }} SP khác)@endif · <span class="font-semibold text-main">{{ $totalPairs }} đôi</span>
                                </p>
                            @endif
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-accent text-sm">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</span>
                                    <span @class(['badge', 'bg-success-bg text-success border border-success/30' => $order->payment_status === App\Enums\OrderPaymentStatus::Paid, 'bg-muted/15 text-muted border border-border/50' => $order->payment_status !== App\Enums\OrderPaymentStatus::Paid])>{{ $order->payment_status->label() }}</span>
                                </div>
                                <a class="px-3 py-1.5 bg-surface text-accent hover:bg-border/40 rounded-lg font-bold text-xs transition-colors" href="{{ route('admin.orders.show', $order) }}">Xem</a>
                            </div>
                            <p class="text-[11px] text-muted">Đặt lúc {{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                        </article>
                    @empty
                        <div class="p-10 text-center text-xs text-muted">Chưa có đơn hàng.</div>
                    @endforelse
                </div>

                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs text-muted border-collapse">
                        <thead class="bg-surface text-main font-bold border-b border-border">
                            <tr>
                                <th class="p-4">Mã đơn</th>
                                <th class="p-4">Khách hàng</th>
                                <th class="p-4">Sản phẩm</th>
                                <th class="p-4">Tổng tiền</th>
                                <th class="p-4 text-center">Trạng thái</th>
                                <th class="p-4 text-center">Thanh toán</th>
                                <th class="p-4">Ngày đặt</th>
                                <th class="p-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border font-medium">
                        @forelse ($orders as $order)
                            @php
                                $firstItem = $order->items->first();
                                $totalPairs = $order->items->sum('quantity');
                                $extraItems = max(0, $order->items->count() - 1);
                            @endphp
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="p-4 whitespace-nowrap">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-bold text-accent hover:underline text-xs block">{{ $order->order_code }}</a>
                                </td>
                                <td class="p-4">
                                    <strong class="font-bold text-main text-xs block">{{ $order->shipping_name }}</strong>
                                    <span class="block text-[11px] text-muted">{{ $order->shipping_phone }}</span>
                                </td>
                                <td class="p-4 max-w-[220px]">
                                    @if ($firstItem)
                                        <span class="block truncate font-semibold text-main text-xs" title="{{ $firstItem->product_name_snapshot }}">
                                            {{ $firstItem->product_name_snapshot }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center gap-1.5 mt-0.5 text-[11px] text-muted">
                                        <span class="bg-surface text-main border border-border px-2 py-0.5 rounded-md font-mono font-bold">{{ $totalPairs }} đôi</span>
                                        @if ($extraItems > 0)
                                            <span>+{{ $extraItems }} SP khác</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="p-4 whitespace-nowrap font-extrabold text-accent text-xs">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <x-orders.status-badge :status="$order->order_status" />
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <span @class(['badge', 'bg-success-bg text-success border border-success/30' => $order->payment_status === App\Enums\OrderPaymentStatus::Paid, 'bg-muted/15 text-muted border border-border/50' => $order->payment_status !== App\Enums\OrderPaymentStatus::Paid])>{{ $order->payment_status->label() }}</span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-muted text-xs">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                                <td class="p-4 text-right whitespace-nowrap">
                                    <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold text-xs inline-block transition-colors" href="{{ route('admin.orders.show', $order) }}">Xem</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="p-12 text-center text-xs text-muted">Chưa có đơn hàng.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$orders" />
            </div>
        </div>
    </div>
@endsection
