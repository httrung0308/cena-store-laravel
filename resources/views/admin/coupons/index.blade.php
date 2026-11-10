@extends('layouts.admin')
@section('title', 'Coupon')
@section('eyebrow', 'Quản lý mã giảm giá')
@section('actions')<a class="btn-pill-dark" href="{{ route('admin.coupons.create') }}">+ Tạo coupon</a>@endsection
@section('content')
    <form method="GET" class="admin-card mb-5 flex gap-2"><input class="input min-w-0 flex-1 border-border bg-surface text-main" name="search" value="{{ $search }}" placeholder="Tìm mã hoặc tên coupon"><button class="btn-pill">Tìm</button></form>
    <div class="admin-table-wrap"><table class="admin-table min-w-[760px]"><thead><tr><th>Mã</th><th>Tên</th><th>Loại</th><th>Giá trị</th><th>Lượt đã giữ/dùng</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        @forelse ($coupons as $coupon)
            <tr><td class="font-semibold text-main">{{ $coupon->code }}</td><td class="text-main">{{ $coupon->name }}</td><td>{{ $coupon->type->label() }}</td><td class="font-semibold text-accent">{{ $coupon->type === App\Enums\CouponType::Percentage ? $coupon->value.'%' : ($coupon->type === App\Enums\CouponType::FreeShipping ? 'Miễn phí ship' : number_format($coupon->value, 0, ',', '.').' ₫') }}</td><td>{{ $coupon->active_redemptions_count }} / {{ $coupon->usage_limit ?? '∞' }}</td><td><span class="badge {{ $coupon->is_active ? 'bg-success-bg text-success-text border border-success/30' : 'bg-surface text-muted border border-border' }}">{{ $coupon->is_active ? 'Đang bật' : 'Đã tắt' }}</span></td><td><a class="text-xs font-semibold text-accent hover:text-accent-hover underline" href="{{ route('admin.coupons.edit', $coupon) }}">Sửa</a></td></tr>
        @empty<tr><td colspan="7" class="py-8 text-center text-muted">Chưa có coupon.</td></tr>@endforelse
    </tbody></table></div>
    <div class="mt-5">{{ $coupons->links() }}</div>
@endsection
