@extends('layouts.admin')
@section('title', 'Đơn hàng')
@section('eyebrow', 'Quản lý đơn hàng')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.orders.index')" id="order-search" label="Tìm đơn hàng" placeholder="Mã đơn, tên hoặc số điện thoại..." :value="$filters['search'] ?? ''">
            <label class="flex items-center gap-2 text-sm font-semibold text-muted">Trạng thái
                <select class="input border-border bg-surface text-main py-2 font-normal" name="status"><option value="">Tất cả</option>@foreach (App\Enums\OrderStatus::cases() as $state)<option value="{{ $state->value }}" @selected(($filters['status'] ?? '') === $state->value)>{{ $state->label() }}</option>@endforeach</select>
            </label>
        </x-admin.search-form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Số đôi</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th>Ngày đặt</th><th>Thao tác</th></tr></thead><tbody>
        @forelse ($orders as $order)
            <tr class="hover:bg-surface-alt/40 transition-colors">
                <td class="min-w-[12rem]"><a href="{{ route('admin.orders.show', $order) }}" class="break-all font-semibold text-accent hover:underline">{{ $order->order_code }}</a></td>
                <td class="text-main">{{ $order->shipping_name }}<span class="block text-xs text-muted">{{ $order->shipping_phone }}</span></td>
                <td class="text-main">{{ $order->items->sum('quantity') }}</td>
                <td class="whitespace-nowrap font-semibold text-main">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td>
                <td><x-orders.status-badge :status="$order->order_status" /></td>
                <td><span @class(['badge', 'bg-success-bg text-success-text border border-success/30' => $order->payment_status === App\Enums\OrderPaymentStatus::Paid, 'bg-surface-alt text-muted border border-border' => $order->payment_status !== App\Enums\OrderPaymentStatus::Paid])>{{ $order->payment_status->label() }}</span></td>
                <td class="whitespace-nowrap text-muted">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                <td><a class="btn-pill" href="{{ route('admin.orders.show', $order) }}">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-20 text-center text-muted">Chưa có đơn hàng.</td></tr>
        @endforelse
        </tbody></table></div>
        <x-ui.pagination :paginator="$orders" />
    </div>
@endsection
