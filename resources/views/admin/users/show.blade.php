@extends('layouts.admin')
@section('title', 'Chi tiết người dùng')
@section('eyebrow', 'Quản lý người dùng')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.users.index') }}">Danh sách người dùng</a>
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
                <p class="break-words text-lg font-semibold">{{ $user->full_name }}</p>
                @if ($user->is_active)<span class="badge bg-green-100 text-green-600">Hoạt động</span>@else<span class="badge bg-red-100 text-red-600">Đã khóa</span>@endif
            </div>
            <div class="grid gap-2 text-sm text-zinc-500 sm:grid-cols-2">
                <p>Email: <span class="break-all font-medium text-zinc-900">{{ $user->email }}</span></p>
                <p>Điện thoại: <span class="font-medium text-zinc-900">{{ $user->phone ?? '—' }}</span></p>
                <p>Ngày tạo: <span class="font-medium text-zinc-900">{{ $user->created_at?->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>
                <p>Đăng nhập gần nhất: <span class="font-medium text-zinc-900">{{ $user->last_login_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</span></p>
                <p>Số đơn hàng: <span class="font-medium text-zinc-900">{{ $orderCount }}</span></p>
                <p>Số địa chỉ: <span class="font-medium text-zinc-900">{{ $addressCount }}</span></p>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-card space-y-4">
            @csrf
            @method('PATCH')
            <h2 class="text-xl font-semibold">Sửa thông tin</h2>
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
            <h2 class="text-xl font-semibold">Vai trò</h2>
            @foreach (App\Enums\RoleSlug::cases() as $role)
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="roles[]" value="{{ $role->value }}" class="native-checkbox accent-black" @checked(in_array($role->value, old('roles', $currentRoles), true))> {{ $role->label() }}</label>
            @endforeach
            @if ($isSelf)<p class="text-xs text-zinc-500">Bạn không thể tự bỏ quyền Admin của mình.</p>@endif
            <button type="submit" class="btn-pill-dark w-full">Lưu vai trò</button>
        </form>

        <section class="admin-card space-y-3">
            <h2 class="text-xl font-semibold">Khóa tài khoản</h2>
            <p class="text-sm text-zinc-500">Khóa sẽ đăng xuất người dùng khỏi mọi thiết bị ở lần tải trang tiếp theo.</p>
            <form method="POST" action="{{ route('admin.users.active', $user) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="active" value="{{ $user->is_active ? 0 : 1 }}">
                @if ($user->is_active)
                    <button type="submit" class="btn-pill w-full text-red-600" @disabled($isSelf)>Khóa tài khoản</button>
                @else
                    <button type="submit" class="btn-pill-dark w-full">Mở khóa tài khoản</button>
                @endif
            </form>
        </section>
    </aside>
</div>
@endsection
