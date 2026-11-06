@extends('layouts.admin')
@section('title', 'Người dùng')
@section('eyebrow', 'Quản lý người dùng')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.users.index')" id="user-search" label="Tìm người dùng" placeholder="Tên, email hoặc số điện thoại..." :value="$filters['search'] ?? ''">
            <label class="flex items-center gap-2 text-sm font-semibold text-muted">Vai trò
                <select class="input border-border bg-surface text-main py-2 font-normal" name="role"><option value="">Tất cả</option>@foreach (App\Enums\RoleSlug::cases() as $role)<option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>@endforeach</select>
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-muted">Trạng thái
                <select class="input border-border bg-surface text-main py-2 font-normal" name="active"><option value="">Tất cả</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Hoạt động</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Đã khóa</option></select>
            </label>
        </x-admin.search-form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Người dùng</th><th>Điện thoại</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th>Thao tác</th></tr></thead><tbody>
        @forelse ($users as $user)
            <tr class="hover:bg-surface-alt/40 transition-colors">
                <td class="min-w-[12rem]"><span class="font-semibold text-main">{{ $user->full_name }}</span><span class="block break-all text-xs text-muted">{{ $user->email }}</span></td>
                <td class="text-main">{{ $user->phone ?? '—' }}</td>
                <td><div class="flex flex-wrap gap-1">@foreach ($user->roles as $role)<span class="badge bg-surface-alt text-main border border-border">{{ $role->slug->label() }}</span>@endforeach</div></td>
                <td>@if ($user->is_active)<span class="badge bg-success-bg text-success-text border border-success/30">Hoạt động</span>@else<span class="badge bg-error-bg text-error-text border border-error/30">Đã khóa</span>@endif</td>
                <td class="whitespace-nowrap text-muted">{{ $user->last_login_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                <td><a class="btn-pill" href="{{ route('admin.users.show', $user) }}">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-20 text-center text-muted">Không tìm thấy người dùng.</td></tr>
        @endforelse
        </tbody></table></div>
        <x-ui.pagination :paginator="$users" />
    </div>
@endsection
