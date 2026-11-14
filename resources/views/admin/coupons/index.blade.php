@extends('layouts.admin')
@section('title', 'Coupon')
@section('eyebrow', 'Quản lý mã giảm giá')
@section('actions')<a class="btn-pill-dark" href="{{ route('admin.coupons.create') }}">+ Tạo coupon</a>@endsection
@php
    $typeFilter = $type ?? '';
    $activeFilter = $active ?? '';
    $typeLabels = collect(App\Enums\CouponType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]);
    $activeChips = [];
    if (filled($search)) {
        $activeChips[] = [
            'label' => 'Từ khóa: '.$search,
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($typeFilter)) {
        $activeChips[] = [
            'label' => 'Loại: '.($typeLabels[$typeFilter] ?? $typeFilter),
            'url' => request()->fullUrlWithQuery(['type' => null, 'page' => null]),
        ];
    }
    if ($activeFilter !== '') {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($activeFilter === '1' ? 'Đang bật' : 'Đã tắt'),
            'url' => request()->fullUrlWithQuery(['active' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
    <div x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6">
        <x-admin.search-form :action="route('admin.coupons.index')" id="coupon-search" label="Tìm mã hoặc tên coupon" placeholder="Tìm mã hoặc tên coupon..." :value="$search" :ajax="true" :active-chips="$activeChips">
            <label for="coupon-type-filter" class="sr-only">Lọc theo loại mã giảm giá</label>
            <select id="coupon-type-filter" name="type" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả loại</option>
                @foreach (App\Enums\CouponType::cases() as $couponType)
                    <option value="{{ $couponType->value }}" @selected($typeFilter === $couponType->value)>{{ $couponType->label() }}</option>
                @endforeach
            </select>

            <label for="coupon-active-filter" class="sr-only">Lọc theo trạng thái</label>
            <select id="coupon-active-filter" name="active" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả trạng thái</option>
                <option value="1" @selected($activeFilter === '1')>Đang bật</option>
                <option value="0" @selected($activeFilter === '0')>Đã tắt</option>
            </select>
        </x-admin.search-form>

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none': listLoading }" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm transition-opacity">
            <div class="px-4 py-3 border-b border-border/70 bg-surface/40 flex items-center justify-between text-xs text-muted">
                <span>Tìm thấy <strong class="text-main font-bold">{{ number_format($coupons->total()) }}</strong> mục</span>
            </div>

            {{-- Mobile card layout (< md) --}}
            <div class="md:hidden divide-y divide-border">
                @forelse ($coupons as $coupon)
                    @php
                        $formattedValue = $coupon->type === App\Enums\CouponType::Percentage
                            ? $coupon->value.'%'
                            : ($coupon->type === App\Enums\CouponType::FreeShipping ? 'Miễn phí ship' : number_format($coupon->value, 0, ',', '.').' ₫');
                    @endphp
                    <article class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="font-mono font-bold text-accent text-xs block">{{ $coupon->code }}</span>
                                <strong class="font-bold text-main text-sm block break-words mt-0.5">{{ $coupon->name }}</strong>
                            </div>
                            @if ($coupon->is_active)
                                <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success-text border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px] shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                    Đang bật
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px] shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                    Đã tắt
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="px-2.5 py-0.5 rounded-lg bg-surface border border-border font-semibold text-main">{{ $coupon->type->label() }}</span>
                            <span class="px-2.5 py-0.5 rounded-lg bg-accent/10 border border-accent/20 font-mono font-bold text-accent">{{ $formattedValue }}</span>
                            <span class="px-2.5 py-0.5 rounded-lg bg-surface border border-border font-mono text-muted">
                                Lượt: {{ $coupon->active_redemptions_count }} / {{ $coupon->usage_limit ?? '∞' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60">
                            <div>
                                @if ($coupon->is_active)
                                    <form method="POST" action="{{ route('admin.coupons.deactivate', $coupon) }}" @submit.prevent="submitToggle($event, 'coupon-{{ $coupon->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" :disabled="isBusy()"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Tắt</span>
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.coupons.activate', $coupon) }}" @submit.prevent="submitToggle($event, 'coupon-{{ $coupon->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" :disabled="isBusy()"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Bật</span>
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ route('admin.coupons.edit', $coupon) }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                                <span>Sửa</span>
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-xs text-muted">Chưa có coupon.</div>
                @endforelse
            </div>

            {{-- Desktop table layout (>= md) --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="admin-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Tên</th>
                            <th>Loại</th>
                            <th>Giá trị</th>
                            <th class="text-center">Lượt đã giữ/dùng</th>
                            <th class="text-center">Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coupons as $coupon)
                            <tr>
                                <td class="font-mono font-semibold text-main whitespace-nowrap">{{ $coupon->code }}</td>
                                <td class="text-main max-w-[16rem] break-words">{{ $coupon->name }}</td>
                                <td class="whitespace-nowrap">{{ $coupon->type->label() }}</td>
                                <td class="font-semibold text-accent whitespace-nowrap">{{ $coupon->type === App\Enums\CouponType::Percentage ? $coupon->value.'%' : ($coupon->type === App\Enums\CouponType::FreeShipping ? 'Miễn phí ship' : number_format($coupon->value, 0, ',', '.').' ₫') }}</td>
                                <td class="text-center font-mono whitespace-nowrap">{{ $coupon->active_redemptions_count }} / {{ $coupon->usage_limit ?? '∞' }}</td>
                                <td class="text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center gap-1.5">
                                        @if ($coupon->is_active)
                                            <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success-text border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                                Đang bật
                                            </span>
                                            <form method="POST" action="{{ route('admin.coupons.deactivate', $coupon) }}" @submit.prevent="submitToggle($event, 'coupon-{{ $coupon->id }}')" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" :disabled="isBusy()"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Tắt</span>
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center gap-1 whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                                Đã tắt
                                            </span>
                                            <form method="POST" action="{{ route('admin.coupons.activate', $coupon) }}" @submit.prevent="submitToggle($event, 'coupon-{{ $coupon->id }}')" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" :disabled="isBusy()"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Bật</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ route('admin.coupons.edit', $coupon) }}">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                        <span>Sửa</span>
                                    </a>
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
