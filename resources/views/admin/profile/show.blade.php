@extends('layouts.admin')
@section('title', 'Tài khoản của tôi')
@section('eyebrow', 'TÀI KHOẢN CÁ NHÂN')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.dashboard') }}">
        ← Về tổng quan
    </a>
@endsection

@php
    $tz = (string) config('store.business_timezone');
    $initials = collect(preg_split('/\s+/u', trim((string) $user->full_name)) ?: [])
        ->filter()
        ->slice(-2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('') ?: 'NV';
@endphp

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <section class="admin-card space-y-6" aria-labelledby="admin-profile-heading">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-border">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-14 h-14 rounded-2xl bg-accent/10 border border-accent/25 text-accent flex items-center justify-center text-base font-extrabold shrink-0" aria-hidden="true">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0 space-y-1">
                        <h2 id="admin-profile-heading" class="text-lg sm:text-xl font-extrabold text-main break-words">
                            {{ $user->full_name }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @forelse ($roleLabels as $roleLabel)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-accent/10 text-accent border border-accent/25">
                                    {{ $roleLabel }}
                                </span>
                            @empty
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-surface text-muted border border-border">
                                    Nhân viên
                                </span>
                            @endforelse

                            @if ($user->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-success-bg text-success border border-success/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                    Đang hoạt động
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="p-4 rounded-xl bg-surface border border-border/80 space-y-1">
                    <dt class="text-xs font-bold uppercase tracking-wider text-muted">Họ và tên</dt>
                    <dd class="font-bold text-main break-words">{{ $user->full_name }}</dd>
                </div>

                <div class="p-4 rounded-xl bg-surface border border-border/80 space-y-1">
                    <dt class="text-xs font-bold uppercase tracking-wider text-muted">Email đăng nhập</dt>
                    <dd class="font-mono font-semibold text-main break-all">{{ $user->email }}</dd>
                </div>

                <div class="p-4 rounded-xl bg-surface border border-border/80 space-y-1">
                    <dt class="text-xs font-bold uppercase tracking-wider text-muted">Số điện thoại</dt>
                    <dd class="font-mono font-semibold text-main">{{ $user->phone ?: 'Chưa cập nhật' }}</dd>
                </div>

                <div class="p-4 rounded-xl bg-surface border border-border/80 space-y-1">
                    <dt class="text-xs font-bold uppercase tracking-wider text-muted">Vai trò được cấp</dt>
                    <dd class="font-bold text-main">
                        {{ $roleLabels !== [] ? implode(', ', $roleLabels) : 'Nhân viên' }}
                    </dd>
                </div>
            </dl>

            <div class="pt-2 border-t border-border/60 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                <span>
                    Đăng nhập gần nhất:
                    <strong class="text-main font-semibold">{{ $user->last_login_at?->setTimezone($tz)->format('d/m/Y H:i') ?? 'Phiên hiện tại' }}</strong>
                </span>
                <span>Thông tin tài khoản được xác định trực tiếp từ phiên đăng nhập hiện tại.</span>
            </div>
        </section>
    </div>
@endsection
