@extends('layouts.admin')
@section('title', 'Quản Lý Đơn Hàng')
@section('eyebrow', 'QUẢN TRỊ BÁN HÀNG')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.orders.index')" id="order-search" label="Tìm đơn hàng" placeholder="Tìm kiếm theo mã đơn, người nhận, số điện thoại..." :value="$filters['search'] ?? ''">
            <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach (App\Enums\OrderStatus::cases() as $state)
                    <option value="{{ $state->value }}" @selected(($filters['status'] ?? '') === $state->value)>{{ $state->label() }}</option>
                @endforeach
            </select>
        </x-admin.search-form>

        <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-muted border-collapse">
                    <thead class="bg-surface text-main font-bold border-b border-border">
                        <tr>
                            <th class="p-4">Mã đơn</th>
                            <th class="p-4">Khách hàng</th>
                            <th class="p-4 text-center">Số lượng</th>
                            <th class="p-4">Tổng tiền</th>
                            <th class="p-4 text-center">Trạng thái</th>
                            <th class="p-4 text-center">Thanh toán</th>
                            <th class="p-4">Ngày đặt</th>
                            <th class="p-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-medium">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-bold text-accent hover:underline text-xs block">{{ $order->order_code }}</a>
                            </td>
                            <td class="p-4">
                                <strong class="font-bold text-main text-xs block">{{ $order->shipping_name }}</strong>
                                <span class="block text-[11px] text-muted">{{ $order->shipping_phone }}</span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="bg-surface text-main border border-border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]">{{ $order->items->sum('quantity') }} đôi</span>
                            </td>
                            <td class="p-4 whitespace-nowrap font-extrabold text-accent text-xs">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td>
                            <td class="p-4 text-center">
                                <x-orders.status-badge :status="$order->order_status" />
                            </td>
                            <td class="p-4 text-center">
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
@endsection
