@extends('layouts.admin')
@section('title', 'Quản Lý Người Dùng')
@section('eyebrow', 'QUẢN TRỊ HỆ THỐNG')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.users.index')" id="user-search" label="Tìm người dùng" placeholder="Tìm kiếm theo tên, email, số điện thoại..." :value="$filters['search'] ?? ''">
            <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="role">
                <option value="">Tất cả vai trò</option>
                @foreach (App\Enums\RoleSlug::cases() as $role)
                    <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="active">
                <option value="">Tất cả trạng thái</option>
                <option value="1" @selected(($filters['active'] ?? '') === '1')>Hoạt động</option>
                <option value="0" @selected(($filters['active'] ?? '') === '0')>Đã khóa</option>
            </select>
        </x-admin.search-form>

        <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-muted border-collapse">
                    <thead class="bg-surface text-main font-bold border-b border-border">
                        <tr>
                            <th class="p-4">Người dùng</th>
                            <th class="p-4">Điện thoại</th>
                            <th class="p-4">Vai trò</th>
                            <th class="p-4 text-center">Trạng thái</th>
                            <th class="p-4">Đăng nhập gần nhất</th>
                            <th class="p-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-medium">
                    @forelse ($users as $user)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4">
                                <strong class="font-bold text-main text-sm block">{{ $user->full_name }}</strong>
                                <span class="block break-all text-[11px] text-muted font-mono mt-0.5">{{ $user->email }}</span>
                            </td>
                            <td class="p-4 text-main font-semibold text-xs">{{ $user->phone ?? '—' }}</td>
                            <td class="p-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->roles as $role)
                                        <span class="bg-surface text-main border border-border px-2 py-0.5 rounded text-[11px] font-bold">{{ $role->slug->label() }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                @if ($user->is_active)
                                    <span class="bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Hoạt động</span>
                                @else
                                    <span class="bg-error-bg text-error border border-error/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã khóa</span>
                                @endif
                            </td>
                            <td class="p-4 whitespace-nowrap text-muted text-xs">{{ $user->last_login_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold text-xs inline-block transition-colors" href="{{ route('admin.users.show', $user) }}">Xem</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-12 text-center text-xs text-muted">Không tìm thấy người dùng.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$users" />
        </div>
    </div>
@endsection
