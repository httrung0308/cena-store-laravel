@extends('layouts.admin')
@section('title', 'Coupon')
@section('eyebrow', 'Quản lý mã giảm giá')
@section('actions')<a class="btn-pill-dark" href="{{ route('admin.coupons.create') }}">+ Tạo coupon</a>@endsection
@section('content')
    <div x-data="adminStatusToggle()" class="space-y-6">
        <form method="GET" class="admin-card flex gap-2">
            <label for="coupon-search" class="sr-only">Tìm mã hoặc tên coupon</label>
            <input id="coupon-search" class="input min-w-0 flex-1 border-border bg-surface text-main" name="search" value="{{ $search }}" placeholder="Tìm mã hoặc tên coupon">
            <button class="btn-pill">Tìm</button>
        </form>

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Tên</th>
                            <th>Loại</th>
                            <th>Giá trị</th>
                            <th>Lượt đã giữ/dùng</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coupons as $coupon)
                            <tr>
                                <td class="font-semibold text-main">{{ $coupon->code }}</td>
                                <td class="text-main">{{ $coupon->name }}</td>
                                <td>{{ $coupon->type->label() }}</td>
                                <td class="font-semibold text-accent">{{ $coupon->type === App\Enums\CouponType::Percentage ? $coupon->value.'%' : ($coupon->type === App\Enums\CouponType::FreeShipping ? 'Miễn phí ship' : number_format($coupon->value, 0, ',', '.').' ₫') }}</td>
                                <td>{{ $coupon->active_redemptions_count }} / {{ $coupon->usage_limit ?? '∞' }}</td>
                                <td>
                                    <span class="badge {{ $coupon->is_active ? 'bg-success-bg text-success-text border border-success/30' : 'bg-surface text-muted border border-border' }}">
                                        {{ $coupon->is_active ? 'Đang bật' : 'Đã tắt' }}
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold text-xs transition-colors" href="{{ route('admin.coupons.edit', $coupon) }}">Sửa</a>
                                        @if ($coupon->is_active)
                                            <form method="POST" action="{{ route('admin.coupons.deactivate', $coupon) }}" @submit.prevent="submitToggle($event, 'coupon-{{ $coupon->id }}')" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" :disabled="isBusy()" class="px-2.5 py-1 text-error hover:bg-error-bg rounded-lg font-bold text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Tắt</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.coupons.activate', $coupon) }}" @submit.prevent="submitToggle($event, 'coupon-{{ $coupon->id }}')" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" :disabled="isBusy()" class="px-2.5 py-1 text-success hover:bg-success-bg rounded-lg font-bold text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Bật</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 text-center text-muted">Chưa có coupon.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$coupons" />
        </div>
    </div>
@endsection
