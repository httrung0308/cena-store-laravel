@extends('layouts.admin')
@section('title', $product->name)
@section('eyebrow', 'CHI TIẾT SẢN PHẨM')
@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        @if ($isPubliclyVisible)
            <a href="{{ route('shoes.show', $product->slug) }}" target="_blank" rel="noopener noreferrer"
               class="px-4 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors inline-flex items-center gap-1.5">
                <span>Xem ngoài cửa hàng</span>
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
        @endif
        <a href="{{ route('admin.products.duplicate', $product) }}"
           class="px-4 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors">
            Nhân bản
        </a>
        <a href="{{ route('admin.products.edit', $product) }}"
           class="px-5 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm">
            Chỉnh sửa sản phẩm
        </a>
        <a href="{{ route('admin.products.index') }}"
           class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">
            ← Quay lại danh sách
        </a>
    </div>
@endsection

@php $images = app(\App\Services\CatalogQueryService::class); @endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    {{-- Cảnh báo trạng thái hiển thị ngoài cửa hàng --}}
    @if (! $isPubliclyVisible)
        <div class="rounded-2xl border border-warning/30 bg-warning-bg p-4 text-xs text-warning-text space-y-1.5" role="status">
            <p class="font-bold">Sản phẩm chưa hiển thị ngoài cửa hàng</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($blockingReasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
            <div class="pt-1">
                <a href="{{ route('admin.products.edit', $product) }}" class="font-bold underline hover:no-underline">Mở trang chỉnh sửa để hoàn thiện</a>
            </div>
        </div>
    @endif

    {{-- Khối 1: Tổng quan thông tin & Thống kê tồn kho --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Thông tin cơ bản --}}
        <section class="admin-card lg:col-span-2 space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-border/70">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($product->is_active)
                            <span class="inline-flex items-center whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang bán</span>
                        @else
                            <span class="inline-flex items-center whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">Ngừng bán</span>
                        @endif
                        @if ($product->is_featured)
                            <span class="inline-flex items-center whitespace-nowrap bg-accent/10 text-accent border border-accent/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Nổi bật</span>
                        @endif
                    </div>
                    <h2 class="text-lg font-extrabold text-main break-words leading-snug">{{ $product->name }}</h2>
                    <p class="text-xs font-mono text-muted break-all">{{ $product->slug }}</p>
                </div>

                <div class="text-left sm:text-right shrink-0">
                    @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                        <span class="block text-xs text-muted line-through font-mono tabular-nums">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                        <span class="block text-xl font-extrabold text-accent font-mono tabular-nums">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                    @else
                        <span class="block text-xl font-extrabold text-main font-mono tabular-nums">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                    @endif
                </div>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 text-xs">
                <div>
                    <dt class="text-muted font-semibold">Danh mục</dt>
                    <dd class="text-main font-bold mt-1">
                        @if ($product->category?->parent)
                            {{ $product->category->parent->name }} / {{ $product->category->name }}
                        @else
                            {{ $product->category?->name ?? '—' }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-muted font-semibold">Dòng thương hiệu</dt>
                    <dd class="text-main font-bold mt-1">{{ $product->brand_line ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted font-semibold">Chính sách bảo hành</dt>
                    <dd class="text-main font-bold mt-1">{{ $product->warranty_months }} tháng</dd>
                </div>
                <div>
                    <dt class="text-muted font-semibold">Thời hạn đổi size</dt>
                    <dd class="text-main font-bold mt-1">{{ $product->exchange_days }} ngày</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted font-semibold mb-1.5">Phân loại</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        @forelse ($product->classifications as $classification)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-surface border border-border text-[11px] font-semibold text-main">
                                {{ $classification->name }}
                            </span>
                        @empty
                            <span class="text-muted">Chưa gắn phân loại</span>
                        @endforelse
                    </dd>
                </div>
                @if ($product->description)
                    <div class="sm:col-span-2 pt-2 border-t border-border/60">
                        <dt class="text-muted font-semibold mb-1.5">Mô tả sản phẩm</dt>
                        <dd class="text-main leading-relaxed whitespace-pre-line break-words">{{ $product->description }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- Tổng quan kho & Biến thể --}}
        <aside class="admin-card space-y-4">
            <h2 class="text-sm font-bold text-main pb-3 border-b border-border/70">Tổng quan tồn kho</h2>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-border bg-surface p-3.5">
                    <span class="block text-[11px] font-semibold text-muted">Tổng tồn kho</span>
                    <strong class="block mt-1 text-lg font-extrabold font-mono tabular-nums {{ $totals['stock'] > 0 ? 'text-main' : 'text-error' }}">
                        {{ number_format($totals['stock'], 0, ',', '.') }} đôi
                    </strong>
                </div>
                <div class="rounded-xl border border-border bg-surface p-3.5">
                    <span class="block text-[11px] font-semibold text-muted">Đang giữ chỗ</span>
                    <strong class="block mt-1 text-lg font-extrabold font-mono tabular-nums text-main">
                        {{ number_format($totals['reserved'], 0, ',', '.') }} đôi
                    </strong>
                </div>
                <div class="rounded-xl border border-border bg-surface p-3.5">
                    <span class="block text-[11px] font-semibold text-muted">Cách ly / Lỗi</span>
                    <strong class="block mt-1 text-lg font-extrabold font-mono tabular-nums {{ $totals['quarantine'] > 0 ? 'text-warning-text' : 'text-muted' }}">
                        {{ number_format($totals['quarantine'], 0, ',', '.') }} đôi
                    </strong>
                </div>
                <div class="rounded-xl border border-border bg-surface p-3.5">
                    <span class="block text-[11px] font-semibold text-muted">Phối màu / Size</span>
                    <strong class="block mt-1 text-lg font-extrabold font-mono tabular-nums text-main">
                        {{ $totals['colors'] }} / {{ $totals['variants'] }}
                    </strong>
                </div>
            </div>

            <div class="pt-2 border-t border-border/60 text-[11px] text-muted space-y-1">
                <p>Ngày tạo: <span class="text-main font-semibold">{{ $product->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') ?? '—' }}</span></p>
                <p>Cập nhật cuối: <span class="text-main font-semibold">{{ $product->updated_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') ?? '—' }}</span></p>
                @unless ($canViewCost)
                    <p class="pt-1 text-muted">Thông tin giá vốn chỉ hiển thị với tài khoản Admin.</p>
                @endunless
            </div>
        </aside>
    </div>

    {{-- Khối 2: Chi tiết từng phối màu, ảnh và bảng biến thể --}}
    <section class="space-y-4" aria-label="Phối màu và biến thể">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-main">Phối màu, Hình ảnh &amp; Biến thể ({{ $product->colors->count() }} màu)</h2>
        </div>

        @forelse ($product->colors as $color)
            <article class="admin-card space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-border/70">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-5 h-5 rounded-full border border-border shrink-0" style="background-color: {{ $color->primary_hex }}"></span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-main truncate">
                                {{ $color->color_name }}
                                <span class="ml-1.5 font-mono text-xs font-normal text-muted">({{ $color->style_color_code }})</span>
                            </h3>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[11px] text-muted font-mono">{{ $color->variants->count() }} size</span>
                        @if ($color->is_active)
                            <span class="inline-flex items-center whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang bán</span>
                        @else
                            <span class="inline-flex items-center whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">Ngừng bán</span>
                        @endif
                    </div>
                </div>

                {{-- Ảnh theo màu --}}
                <div>
                    <h4 class="text-xs font-semibold text-muted mb-2.5">Hình ảnh phối màu</h4>
                    @if ($color->images->isEmpty())
                        <div class="rounded-xl border border-dashed border-warning/50 bg-warning-bg/50 p-4 text-xs text-warning-text flex flex-wrap items-center justify-between gap-2">
                            <span>Phối màu này chưa có ảnh. Cần tải ít nhất 1 ảnh đại diện để mở bán công khai.</span>
                            <a href="{{ route('admin.products.edit', $product) }}" class="font-bold underline hover:no-underline">Bổ sung ảnh</a>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-3">
                            @foreach ($color->images as $img)
                                <div class="relative w-20 h-20 rounded-xl overflow-hidden border {{ $img->is_thumbnail ? 'border-accent ring-2 ring-accent/30' : 'border-border' }} bg-surface shrink-0">
                                    <img src="{{ $img->thumb_400_url ?? $img->thumbnail_url ?? $images->imageUrl($img) }}"
                                         alt="{{ $img->alt_text }}"
                                         class="w-full h-full object-cover" loading="lazy" decoding="async">
                                    @if ($img->is_thumbnail)
                                        <span class="absolute bottom-1 left-1 right-1 text-center bg-accent text-accent-text text-[9px] font-bold py-0.5 rounded">Ảnh chính</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Bảng biến thể SKU --}}
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left text-xs text-muted border-collapse">
                        <thead class="bg-surface text-main font-bold border-b border-border">
                            <tr>
                                <th class="p-3 whitespace-nowrap">Size</th>
                                <th class="p-3 whitespace-nowrap">Mã SKU</th>
                                <th class="p-3 whitespace-nowrap text-right">Giá bán</th>
                                @if ($canViewCost)
                                    <th class="p-3 whitespace-nowrap text-right">Giá vốn BQ</th>
                                @endif
                                <th class="p-3 whitespace-nowrap text-right">Tồn kho</th>
                                <th class="p-3 whitespace-nowrap text-right">Giữ chỗ</th>
                                <th class="p-3 whitespace-nowrap text-right">Cách ly</th>
                                <th class="p-3 whitespace-nowrap text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-surface-card">
                            @forelse ($color->variants as $variant)
                                @php
                                    $effectivePrice = $variant->price_override ?? ($product->promotion_price ?? $product->base_price);
                                    $isCustomPrice = $variant->price_override !== null;
                                @endphp
                                <tr class="hover:bg-surface/50 transition-colors">
                                    <td class="p-3 font-bold text-main font-mono whitespace-nowrap">{{ $variant->size_value }}</td>
                                    <td class="p-3 font-mono text-main whitespace-nowrap">{{ $variant->sku }}</td>
                                    <td class="p-3 text-right font-mono tabular-nums whitespace-nowrap">
                                        @if ($isCustomPrice)
                                            <span class="font-bold text-accent">{{ number_format($variant->price_override, 0, ',', '.') }} ₫</span>
                                            <span class="block text-[10px] text-muted">Giá riêng</span>
                                        @else
                                            <span class="text-main">{{ number_format($effectivePrice, 0, ',', '.') }} ₫</span>
                                            <span class="block text-[10px] text-muted">Kế thừa</span>
                                        @endif
                                    </td>
                                    @if ($canViewCost)
                                        <td class="p-3 text-right font-mono tabular-nums whitespace-nowrap text-main">
                                            {{ number_format((float) $variant->average_cost, 0, ',', '.') }} ₫
                                        </td>
                                    @endif
                                    <td class="p-3 text-right font-mono tabular-nums font-bold whitespace-nowrap {{ $variant->stock_qty > 0 ? 'text-main' : 'text-error' }}">
                                        {{ $variant->stock_qty }}
                                    </td>
                                    <td class="p-3 text-right font-mono tabular-nums whitespace-nowrap">
                                        {{ $variant->reserved_qty }}
                                    </td>
                                    <td class="p-3 text-right font-mono tabular-nums whitespace-nowrap {{ $variant->quarantine_qty > 0 ? 'text-warning-text font-bold' : '' }}">
                                        {{ $variant->quarantine_qty }}
                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        @if ($variant->is_active && $color->is_active)
                                            <span class="inline-flex items-center whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Bán</span>
                                        @else
                                            <span class="inline-flex items-center whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">Ẩn</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canViewCost ? 8 : 7 }}" class="p-6 text-center text-muted">Chưa có biến thể size nào.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="admin-card p-8 text-center text-xs text-muted">
                Sản phẩm chưa có phối màu nào.
            </div>
        @endforelse
    </section>

</div>
@endsection
