@extends('layouts.admin')
@section('title', 'Quản Lý Banner Trang Chủ')
@section('eyebrow', 'QUẢN TRỊ TRANG CHỦ')
@section('actions')
    <a href="{{ route('admin.banners.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Thêm banner mới</a>
@endsection
@section('content')
<section x-data="adminStatusToggle()" class="space-y-6" aria-label="Danh sách banner trang chủ">
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
        <form method="GET" action="{{ route('admin.banners.index') }}" class="flex-1 max-w-md">
            <label for="banner-search" class="sr-only">Tìm banner</label>
            <div class="relative">
                <input id="banner-search" type="text" name="search" value="{{ $search }}" placeholder="Tìm theo tiêu đề, phụ đề..." class="input w-full pl-9 text-xs border-border bg-surface text-main placeholder:text-muted">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            @if ($active !== '')<input type="hidden" name="active" value="{{ $active }}">@endif
        </form>

        <div class="flex items-center gap-2 overflow-x-auto text-xs font-semibold">
            <a href="{{ route('admin.banners.index', array_filter(['search' => $search])) }}"
               class="px-3 py-1.5 rounded-lg border transition-colors {{ $active === '' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                Tất cả trạng thái
            </a>
            <a href="{{ route('admin.banners.index', ['active' => '1', 'search' => $search]) }}"
               class="px-3 py-1.5 rounded-lg border transition-colors {{ $active === '1' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                Đang hiển thị
            </a>
            <a href="{{ route('admin.banners.index', ['active' => '0', 'search' => $search]) }}"
               class="px-3 py-1.5 rounded-lg border transition-colors {{ $active === '0' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                Đang ẩn
            </a>
        </div>
    </div>

    <x-admin.ajax-feedback />

    <div id="admin-list-region" x-ref="region" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4 w-32">Ảnh banner</th>
                        <th class="p-4">Tiêu đề & Phụ đề</th>
                        <th class="p-4">CTA & Liên kết</th>
                        <th class="p-4 text-center w-20">Thứ tự</th>
                        <th class="p-4 text-center w-28">Trạng thái</th>
                        <th class="p-4 text-right w-28">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($paginator->items() as $item)
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4">
                            <div class="space-y-1">
                                <a href="{{ $item->imageUrl() }}" target="_blank" rel="noopener noreferrer" class="block aspect-[16/7] w-28 rounded-lg overflow-hidden border border-border bg-surface group" title="Xem ảnh desktop">
                                    <img src="{{ $item->imageUrl() }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                </a>
                                @if ($item->mobile_image_path)
                                    <span class="inline-block text-[10px] text-accent font-semibold">+ Ảnh Mobile</span>
                                @endif
                            </div>
                        </td>
                        <td class="p-4">
                            <strong class="font-bold text-main text-sm block">{{ $item->title }}</strong>
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
                        <td class="p-4 text-center font-bold text-main">
                            {{ $item->sort_order }}
                        </td>
                        <td class="p-4 text-center">
                            <form method="POST" action="{{ route('admin.banners.toggle-active', $item) }}" @submit.prevent="submitToggle($event, 'banner-{{ $item->id }}')">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}">
                                <button type="submit" :disabled="isBusy()" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold transition-transform active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed {{ $item->is_active ? 'bg-success-bg text-success-text border border-success/30' : 'bg-surface text-muted border border-border' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $item->is_active ? 'bg-success' : 'bg-muted/40' }}" aria-hidden="true"></span>
                                    {{ $item->is_active ? 'Hiển thị' : 'Đang ẩn' }}
                                </button>
                            </form>
                        </td>
                        <td class="p-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.banners.edit', $item) }}" class="p-1.5 hover:bg-surface rounded-lg text-main transition-colors" title="Chỉnh sửa">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('admin.banners.destroy', $item) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xóa banner \'{{ addslashes($item->title) }}\' không? Thao tác này không thể hoàn tác.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 hover:bg-rose-500/10 text-rose-500 rounded-lg transition-colors" title="Xóa">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
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
        <div class="mt-4"><x-ui.pagination :paginator="$paginator" /></div>
    @endif
</section>
@endsection
