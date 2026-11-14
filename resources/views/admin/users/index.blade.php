@extends('layouts.admin')
@section('title', 'Quản Lý Người Dùng')
@section('eyebrow', 'QUẢN TRỊ HỆ THỐNG')
@php
    $tz = (string) config('store.business_timezone');
    $activeChips = [];
    if (filled($filters['search'] ?? null)) {
        $activeChips[] = [
            'label' => 'Từ khóa: "'.$filters['search'].'"',
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($filters['role'] ?? null)) {
        $activeChips[] = [
            'label' => 'Vai trò: '.App\Enums\RoleSlug::from($filters['role'])->label(),
            'url' => request()->fullUrlWithQuery(['role' => null, 'page' => null]),
        ];
    }
    if (isset($filters['active']) && $filters['active'] !== '') {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($filters['active'] === '1' ? 'Hoạt động' : 'Đã khóa'),
            'url' => request()->fullUrlWithQuery(['active' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
    <div x-data="adminAjaxList()" class="space-y-6">
        <x-admin.search-form
            :action="route('admin.users.index')"
            id="user-search"
            label="Tìm người dùng"
            placeholder="Tìm kiếm theo tên, email, số điện thoại..."
            :value="$filters['search'] ?? ''"
            :ajax="true"
            :active-chips="$activeChips"
        >
            <label class="sr-only" for="user-role-filter">Vai trò</label>
            <select
                id="user-role-filter"
                name="role"
                @change="submitFilter($el.form)"
                class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent"
            >
                <option value="">Tất cả vai trò</option>
                @foreach (App\Enums\RoleSlug::cases() as $role)
                    <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="user-active-filter">Trạng thái tài khoản</label>
            <select
                id="user-active-filter"
                name="active"
                @change="submitFilter($el.form)"
                class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent"
            >
                <option value="">Tất cả trạng thái</option>
                <option value="1" @selected(($filters['active'] ?? '') === '1')>Hoạt động</option>
                <option value="0" @selected(($filters['active'] ?? '') === '0')>Đã khóa</option>
            </select>
        </x-admin.search-form>

        <div id="admin-list-region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none transition-opacity': listLoading }" class="space-y-4">
            <div class="flex items-center justify-between px-1 text-xs text-muted">
                <span>Tổng cộng <strong class="font-bold text-main">{{ number_format($users->total()) }}</strong> người dùng</span>
                @if ($users->hasPages())
                    <span>Trang {{ $users->currentPage() }} / {{ $users->lastPage() }}</span>
                @endif
            </div>

            <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
                {{-- Mobile summary cards --}}
                <div class="divide-y divide-border md:hidden">
                    @forelse ($users as $user)
                        <article class="p-4 space-y-2.5 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <strong class="font-bold text-main text-sm block truncate">{{ $user->full_name }}</strong>
                                    <span class="block break-all text-[11px] text-muted font-mono mt-0.5">{{ $user->email }}</span>
                                </div>
                                @if ($user->is_active)
                                    <span class="shrink-0 bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Hoạt động</span>
                                @else
                                    <span class="shrink-0 bg-error-bg text-error border border-error/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã khóa</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach ($user->roles as $role)
                                    <span class="bg-surface text-main border border-border px-2 py-0.5 rounded text-[11px] font-bold">{{ $role->slug->label() }}</span>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-1">
                                <span class="text-[11px] text-muted">SĐT: <strong class="text-main">{{ $user->phone ?? '—' }}</strong></span>
                                <a class="px-3 py-1.5 bg-surface text-accent hover:bg-border/40 rounded-lg font-bold text-xs transition-colors" href="{{ route('admin.users.show', $user) }}">Xem</a>
                            </div>
                            <p class="text-[11px] text-muted">Đăng nhập gần nhất: {{ $user->last_login_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</p>
                        </article>
                    @empty
                        <div class="p-10 text-center text-xs text-muted">Không tìm thấy người dùng.</div>
                    @endforelse
                </div>

                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
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
                                <td class="p-4 text-main font-semibold text-xs whitespace-nowrap">{{ $user->phone ?? '—' }}</td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($user->roles as $role)
                                            <span class="bg-surface text-main border border-border px-2 py-0.5 rounded text-[11px] font-bold">{{ $role->slug->label() }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
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
    </div>
@endsection
