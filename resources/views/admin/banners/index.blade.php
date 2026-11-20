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
        <x-admin.filter-select id="banner-active-filter" name="active" label="Trạng thái:" :active="$active !== ''">
            <option value="">Tất cả</option>
            <option value="1" @selected($active === '1')>Đang hiển thị</option>
            <option value="0" @selected($active === '0')>Đang ẩn</option>
        </x-admin.filter-select>
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
                            <th class="p-4 text-left w-36 whitespace-nowrap">Trạng thái</th>
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
                            <td class="p-4 text-center whitespace-nowrap">
                                <x-admin.inline-order-input
                                    :action="route('admin.banners.update-order', $item)"
                                    :value="$item->sort_order"
                                    :toggle-key="'banner-order-'.$item->id"
                                    :label="'Thứ tự banner '.$item->title"
                                />
                            </td>
                            <td class="p-4 text-left whitespace-nowrap">
                                <x-admin.status-switch
                                    :active="$item->is_active"
                                    :action="route('admin.banners.toggle-active', $item)"
                                    :toggle-key="'banner-'.$item->id"
                                    active-label="Đang hiển thị"
                                    inactive-label="Đang ẩn"
                                    :action-label="($item->is_active ? 'Ẩn banner ' : 'Hiện banner ').$item->title"
                                >
                                    <input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}">
                                </x-admin.status-switch>
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
