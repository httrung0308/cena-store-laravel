@extends('layouts.admin')
@section('title', 'Quản Lý Banner Trang Chủ')
@section('eyebrow', 'QUẢN TRỊ TRANG CHỦ')
@section('actions')
    <a href="{{ route('admin.banners.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Thêm banner mới</a>
@endsection
@php
    $activeChips = [];
    if (filled($search)) {
        $activeChips[] = [
            'label' => 'Từ khóa: '.$search,
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if ($active !== '') {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($active === '1' ? 'Đang hiển thị' : 'Đang ẩn'),
            'url' => request()->fullUrlWithQuery(['active' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
<section x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6" aria-label="Danh sách banner trang chủ">
    <x-admin.search-form :action="route('admin.banners.index')" id="banner-search" label="Tìm banner" placeholder="Tìm theo tiêu đề, phụ đề..." :value="$search" :ajax="true" :active-chips="$activeChips">
        <label for="banner-active-filter" class="sr-only">Lọc theo trạng thái</label>
        <select id="banner-active-filter" name="active" @change="submitFilter($el.form)"
                class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
            <option value="">Tất cả trạng thái</option>
            <option value="1" @selected($active === '1')>Đang hiển thị</option>
            <option value="0" @selected($active === '0')>Đang ẩn</option>
        </select>
    </x-admin.search-form>

    <x-admin.ajax-feedback />

    <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none': listLoading }" class="space-y-4 transition-opacity">
        <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="px-4 py-3 border-b border-border/70 bg-surface/40 flex items-center justify-between text-xs text-muted">
                <span>Tìm thấy <strong class="text-main font-bold">{{ number_format($paginator->total()) }}</strong> mục</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-muted border-collapse">
                    <thead class="bg-surface text-main font-bold border-b border-border">
                        <tr>
                            <th class="p-4 w-32 whitespace-nowrap">Ảnh banner</th>
                            <th class="p-4">Tiêu đề &amp; Phụ đề</th>
                            <th class="p-4">CTA &amp; Liên kết</th>
                            <th class="p-4 text-center w-20 whitespace-nowrap">Thứ tự</th>
                            <th class="p-4 text-center w-36 whitespace-nowrap">Trạng thái</th>
                            <th class="p-4 text-right w-32 whitespace-nowrap">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-medium">
                    @forelse ($paginator->items() as $item)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    <a href="{{ $item->imageUrl() }}" target="_blank" rel="noopener noreferrer" class="block aspect-[16/7] w-28 rounded-lg overflow-hidden border border-border bg-surface group" title="Xem ảnh desktop">
                                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                    </a>
                                    @if ($item->mobile_image_path)
                                        <span class="inline-block text-[10px] text-accent font-semibold">+ Ảnh Mobile</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 max-w-[16rem]">
                                <strong class="font-bold text-main text-sm block break-words">{{ $item->title }}</strong>
                                @if ($item->subtitle)
                                    <p class="text-xs text-muted mt-0.5 line-clamp-1">{{ $item->subtitle }}</p>
                                @endif
                            </td>
                            <td class="p-4">
                                @if ($item->cta_text)
                                    <span class="inline-block px-2 py-0.5 rounded bg-surface border border-border text-[11px] font-bold text-main mb-1">
                                        {{ $item->cta_text }}
                                    </span>
                                @endif
                                @if ($item->link_url)
                                    <a href="{{ $item->link_url }}" target="_blank" rel="noopener noreferrer" class="block text-[11px] text-accent hover:underline font-mono truncate max-w-xs">
                                        {{ $item->link_url }}
                                    </a>
                                @else
                                    <span class="text-[11px] text-muted/60 italic">Không có liên kết</span>
                                @endif
                            </td>
                            <td class="p-4 text-center font-bold text-main whitespace-nowrap">
                                {{ $item->sort_order }}
                            </td>
                            <td class="p-4 text-center whitespace-nowrap">
                                <div class="inline-flex flex-col items-center gap-1.5">
                                    @if ($item->is_active)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success-text border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                            Đang hiển thị
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                            Đang ẩn
                                        </span>
                                    @endif

                                    <form method="POST" action="{{ route('admin.banners.toggle-active', $item) }}" @submit.prevent="submitToggle($event, 'banner-{{ $item->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}">
                                        @if ($item->is_active)
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                </svg>
                                                <span>Ẩn</span>
                                            </button>
                                        @else
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>Hiện</span>
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.banners.edit', $item) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" title="Chỉnh sửa">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                        <span>Sửa</span>
                                    </a>
                                    <x-ui.dialog id="delete-banner-{{ $item->id }}" title="Xác nhận xóa banner" trigger-class="px-2.5 py-1.5 text-error hover:bg-error-bg rounded-lg font-bold text-xs transition-colors">
                                        <x-slot:trigger>Xóa</x-slot:trigger>
                                        <p class="text-xs text-muted leading-relaxed">
                                            Bạn có chắc chắn muốn xóa banner <strong class="text-main">{{ $item->title }}</strong>? Thao tác này không thể hoàn tác.
                                        </p>
                                        <div class="mt-5 flex items-center justify-end gap-2">
                                            <button type="button" x-on:click="closeDialog()" class="px-4 py-2 rounded-xl border border-border bg-surface text-xs font-bold text-main hover:bg-border/40 transition-colors">Hủy</button>
                                            <form method="POST" action="{{ route('admin.banners.destroy', $item) }}" class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-4 py-2 rounded-xl bg-error text-white text-xs font-bold hover:bg-error/90 transition-colors">Xóa banner</button>
                                            </form>
                                        </div>
                                    </x-ui.dialog>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center text-muted">
                                <svg class="w-12 h-12 mx-auto text-muted/40 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="font-bold text-main text-sm">Chưa có banner nào</p>
                                <p class="text-xs text-muted mt-1">Bấm nút "+ Thêm banner mới" để tạo banner hiển thị trên trang chủ.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($paginator->hasPages())
            <x-ui.pagination :paginator="$paginator" />
        @endif
    </div>
</section>
@endsection
