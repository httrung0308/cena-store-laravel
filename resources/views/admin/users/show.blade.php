@extends('layouts.admin')
@section('title', 'Chi Tiết Người Dùng')
@section('eyebrow', 'QUẢN TRỊ HỆ THỐNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.users.index') }}">← Danh sách người dùng</a>
@endsection
@php
    $tz = (string) config('store.business_timezone');
    $isSelf = auth()->id() === $user->id;
    $currentRoles = $user->roles->map(fn ($role) => $role->slug->value)->all();
@endphp
@section('content')
<div class="grid min-w-0 gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-6 lg:col-span-2">
        <section class="admin-card space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="break-words text-lg font-semibold text-main">{{ $user->full_name }}</p>
                @if ($user->is_active)<span class="badge bg-success-bg text-success-text border border-success/30">Hoạt động</span>@else<span class="badge bg-error-bg text-error-text border border-error/30">Đã khóa</span>@endif
            </div>
            <div class="grid gap-2 text-sm text-muted sm:grid-cols-2">
                <p>Email: <span class="break-all font-medium text-main">{{ $user->email }}</span></p>
                <p>Điện thoại: <span class="font-medium text-main">{{ $user->phone ?? '—' }}</span></p>
                <p>Ngày tạo: <span class="font-medium text-main">{{ $user->created_at?->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>
                <p>Đăng nhập gần nhất: <span class="font-medium text-main">{{ $user->last_login_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</span></p>
                <p>Số đơn hàng: <span class="font-medium text-main">{{ $orderCount }}</span></p>
                <p>Số địa chỉ: <span class="font-medium text-main">{{ $addressCount }}</span></p>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-card space-y-4">
            @csrf
            @method('PATCH')
            <h2 class="text-xl font-semibold text-main">Sửa thông tin</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.field name="full_name" label="Họ và tên" :value="$user->full_name" required />
                <x-admin.field name="phone" label="Số điện thoại" type="tel" :value="$user->phone" />
            </div>
            <button type="submit" class="btn-pill-dark">Lưu thông tin</button>
        </form>
    </div>

    <aside class="min-w-0 space-y-6">
        <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="admin-card space-y-3">
            @csrf
            @method('PUT')
            <h2 class="text-xl font-semibold text-main">Vai trò</h2>
            @foreach (App\Enums\RoleSlug::cases() as $role)
                <label class="flex items-center gap-2 text-sm font-semibold text-main cursor-pointer"><input type="checkbox" name="roles[]" value="{{ $role->value }}" class="native-checkbox accent-accent" @checked(in_array($role->value, old('roles', $currentRoles), true))> {{ $role->label() }}</label>
            @endforeach
            @if ($isSelf)<p class="text-xs text-muted">Bạn không thể tự bỏ quyền Admin của mình.</p>@endif
            <button type="submit" class="btn-pill-dark w-full">Lưu vai trò</button>
        </form>

        <section class="admin-card space-y-3">
            <h2 class="text-xl font-semibold text-main">Khóa tài khoản</h2>
            <p class="text-sm text-muted">Khóa sẽ đăng xuất người dùng khỏi mọi thiết bị ở lần tải trang tiếp theo.</p>
            <form method="POST" action="{{ route('admin.users.active', $user) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="active" value="{{ $user->is_active ? 0 : 1 }}">
                @if ($user->is_active)
                    <button type="submit" class="btn-pill w-full text-error hover:bg-error-bg" @disabled($isSelf)>Khóa tài khoản</button>
                @else
                    <button type="submit" class="btn-pill-dark w-full">Mở khóa tài khoản</button>
                @endif
            </form>
        </section>
    </aside>
</div>
@endsection
