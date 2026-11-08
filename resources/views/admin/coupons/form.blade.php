@extends('layouts.admin')
@section('title', $coupon->exists ? 'Sửa coupon' : 'Tạo coupon')
@section('eyebrow', 'Quản lý mã giảm giá')
@section('actions')<a class="btn-pill" href="{{ route('admin.coupons.index') }}">Quay lại danh sách</a>@endsection
@section('content')
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="admin-card max-w-3xl space-y-4">
        @csrf @if ($coupon->exists)@method('PUT')@endif
        <x-admin.field name="code" label="Mã coupon" :value="$coupon->code" required maxlength="50" hint="Mã được chuẩn hóa thành chữ hoa." />
        <x-admin.field name="name" label="Tên chương trình" :value="$coupon->name" required maxlength="150" />
        <div><label class="admin-label" for="coupon-type">Loại giảm giá</label><select id="coupon-type" name="type" class="input w-full border-zinc-300" required>@foreach ($types as $type)<option value="{{ $type->value }}" @selected(old('type', $coupon->type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
        <x-admin.field name="value" label="Giá trị (phần trăm hoặc VND)" type="number" :value="$coupon->value" required min="0" />
        <x-admin.field name="min_order_amount" label="Đơn tối thiểu (VND)" type="number" :value="$coupon->min_order_amount ?? 0" required min="0" />
        <x-admin.field name="max_discount_amount" label="Mức giảm tối đa (VND, tùy chọn)" type="number" :value="$coupon->max_discount_amount" min="1" />
        <div class="grid gap-4 sm:grid-cols-2"><x-admin.field name="usage_limit" label="Tổng lượt dùng (trống = không giới hạn)" type="number" :value="$coupon->usage_limit" min="1" /><x-admin.field name="per_user_limit" label="Lượt tối đa mỗi khách" type="number" :value="$coupon->per_user_limit ?? 1" required min="1" /></div>
        <div class="grid gap-4 sm:grid-cols-2"><x-admin.field name="starts_at" label="Bắt đầu" type="datetime-local" :value="$coupon->starts_at?->setTimezone(config('store.business_timezone'))->format('Y-m-d\TH:i')" /><x-admin.field name="expires_at" label="Kết thúc" type="datetime-local" :value="$coupon->expires_at?->setTimezone(config('store.business_timezone'))->format('Y-m-d\TH:i')" /></div>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->exists ? $coupon->is_active : true))> Đang hoạt động</label>
        @if ($errors->any())<div role="alert" class="text-sm text-red-600">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <button class="btn-pill-dark px-6 py-3">{{ $coupon->exists ? 'Lưu thay đổi' : 'Tạo coupon' }}</button>
    </form>
@endsection
