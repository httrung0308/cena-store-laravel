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
                        @if ($product->trashed())
                            <span class="inline-flex items-center whitespace-nowrap bg-error-bg text-error border border-error/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã xóa mềm</span>
                        @elseif ($product->is_active)
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
                    <dt class="text-muted font-semibold">Đối tượng</dt>
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
                        @php $colorThumb = $color->images->firstWhere('is_thumbnail', true) ?? $color->images->first(); @endphp
                        @if ($colorThumb)
                            <img src="{{ $colorThumb->thumb_400_url ?? $colorThumb->thumbnail_url ?? $images->imageUrl($colorThumb) }}"
                                 alt="{{ $color->color_name }}"
                                 class="w-7 h-7 rounded-lg object-cover border border-border shrink-0"
                                 loading="lazy" decoding="async">
                        @endif
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
                                <th class="p-3 whitespace-nowrap text-right">Khả dụng</th>
                                <th class="p-3 whitespace-nowrap text-right">Cách ly</th>
                                <th class="p-3 whitespace-nowrap text-center">Trạng thái</th>
                                <th class="p-3 whitespace-nowrap text-right">Thẻ kho</th>
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
                                    <td class="p-3 text-right font-mono tabular-nums font-bold whitespace-nowrap text-main">
                                        {{ $variant->stock_qty - $variant->reserved_qty }}
                                    </td>
                                    <td class="p-3 text-right font-mono tabular-nums whitespace-nowrap {{ $variant->quarantine_qty > 0 ? 'text-warning-text font-bold' : '' }}">
                                        {{ $variant->quarantine_qty }}
                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        @if ($variant->is_active && $color->is_active)
                                            <span class="inline-flex items-center whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Bán</span>
                                        @else
                                            <span class="inline-flex items-center whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">Ngừng bán</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <a @class([
                                            'inline-flex rounded-lg border px-2.5 py-1.5 text-[11px] font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent',
                                            'border-accent bg-accent text-accent-text' => $selectedVariant?->id === $variant->id,
                                            'border-border bg-surface text-accent hover:bg-border/40' => $selectedVariant?->id !== $variant->id,
                                        ]) href="{{ route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]) }}#inventory-history-section"
                                           @click.prevent="window.dispatchEvent(new CustomEvent('inventory-history:visit', { detail: { url: $el.href } }))"
                                           aria-label="Xem thẻ kho SKU {{ $variant->sku }}">
                                            Xem thẻ kho
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canViewCost ? 10 : 9 }}" class="p-6 text-center text-muted">Chưa có biến thể size nào.</td>
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

    {{-- Khối 3: Thẻ kho SKU --}}
    @php
        $variantOptions = $historyColors->flatMap(function ($color) use ($product) {
            return $color->variants->map(function ($variant) use ($color, $product) {
                return [
                    'id' => $variant->id,
                    'label' => ($color->color_name ?? 'Màu').' · Size '.$variant->size_value,
                    'sku' => $variant->sku,
                    'inactive' => $variant->trashed() || ! $variant->is_active || $color->trashed() || ! $color->is_active,
                    'url' => route('admin.products.show', ['product' => $product, 'variant_id' => $variant->id]).'#inventory-history-section',
                ];
            });
        })->values();
    @endphp

    <section
        id="inventory-history-section"
        class="space-y-4"
        aria-labelledby="inventory-history-title"
        x-data="adminAjaxList({ regionSelector: '#inventory-history-region', toolbarSelector: '#inventory-history-toolbar' })"
        @inventory-history:visit.window="visitUrl($event.detail.url); $el.scrollIntoView({ behavior: 'smooth', block: 'start' })"
    >
        <div id="inventory-history-region" @click="handleRegionClick($event)" :class="listLoading ? 'opacity-60 transition-opacity' : ''">
            @if ($selectedVariant)
                <div class="space-y-4">
                    {{-- Header + Searchable Variant Selector + Reconciliation Pill --}}
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-1.5 min-w-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 id="inventory-history-title" class="text-base font-extrabold text-main">
                                    Lịch sử kho — {{ $selectedVariant->color?->color_name ?? 'Màu' }} · Size {{ $selectedVariant->size_value }}
                                </h2>
                                @unless ($reconciliation['history_incomplete'] || $reconciliation['observed_balance_diff'])
                                    <span role="status" class="inline-flex items-center gap-1.5 rounded-full border border-success/30 bg-success-bg px-3 py-0.5 text-[11px] font-bold text-success">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                        Đối chiếu khớp toàn bộ lịch sử
                                    </span>
                                @endunless
                            </div>
                            <p class="text-xs font-mono text-muted">
                                SKU {{ $selectedVariant->sku }}
                                @if ($selectedVariant->trashed())
                                    · <span class="font-sans font-semibold text-error">Đã xóa mềm</span>
                                @elseif (! $selectedVariant->is_active)
                                    · <span class="font-sans font-semibold text-muted">Ngừng bán</span>
                                @endif
                            </p>
                        </div>

                        {{-- Searchable Variant Selector --}}
                        <div
                            class="relative w-full lg:w-80 shrink-0"
                            x-data="{
                                open: false,
                                query: '',
                                options: @js($variantOptions),
                                get filtered() {
                                    const q = this.query.trim().toLowerCase();
                                    if (!q) return this.options;
                                    return this.options.filter(o =>
                                        o.label.toLowerCase().includes(q) || o.sku.toLowerCase().includes(q)
                                    );
                                },
                            }"
                            @click.outside="open = false"
                            @keydown.escape.window="open = false"
                        >
                            <button
                                type="button"
                                @click="open = !open; if (open) { $nextTick(() => $refs.variantSearch?.focus()); }"
                                :aria-expanded="open ? 'true' : 'false'"
                                class="flex min-h-10 w-full items-center justify-between gap-2 rounded-xl border border-border bg-surface-card px-3 py-2 text-left text-xs text-main shadow-sm transition-colors hover:border-accent/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
                            >
                                <span class="min-w-0 truncate">
                                    <span class="font-bold">{{ $selectedVariant->color?->color_name ?? 'Màu' }} · Size {{ $selectedVariant->size_value }}</span>
                                    <span class="ml-1.5 font-mono text-[11px] text-muted">{{ $selectedVariant->sku }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-muted transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div
                                x-cloak
                                x-show="open"
                                x-transition.opacity.duration.150ms
                                class="absolute right-0 z-30 mt-1.5 w-full overflow-hidden rounded-xl border border-border bg-surface-card shadow-lg"
                            >
                                <div class="border-b border-border p-2">
                                    <input
                                        x-ref="variantSearch"
                                        type="search"
                                        x-model="query"
                                        placeholder="Tìm màu, size hoặc SKU..."
                                        aria-label="Tìm kiếm biến thể"
                                        class="w-full rounded-lg border border-border bg-bg px-2.5 py-1.5 text-xs text-main placeholder:text-muted focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
                                    >
                                </div>
                                <ul class="max-h-60 overflow-y-auto divide-y divide-border/50" role="listbox">
                                    <template x-for="opt in filtered" :key="opt.id">
                                        <li>
                                            <button
                                                type="button"
                                                @click="open = false; query = ''; visitUrl(opt.url)"
                                                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors hover:bg-surface"
                                                :class="opt.id === {{ $selectedVariant->id }} ? 'bg-accent/10 font-bold text-accent' : 'text-main'"
                                            >
                                                <span class="min-w-0 truncate">
                                                    <span class="block truncate" x-text="opt.label"></span>
                                                    <span class="block font-mono text-[10px] text-muted" x-text="opt.sku"></span>
                                                </span>
                                                <span x-show="opt.inactive" class="shrink-0 rounded-full border border-border bg-muted/15 px-2 py-0.5 text-[10px] font-semibold text-muted">Ngừng bán</span>
                                            </button>
                                        </li>
                                    </template>
                                    <li x-show="filtered.length === 0" class="px-3 py-4 text-center text-xs text-muted">
                                        Không tìm thấy biến thể phù hợp.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Reconciliation Warnings (never inside a tooltip) --}}
                    @if ($reconciliation['history_incomplete'] || $reconciliation['observed_balance_diff'])
                        <div class="grid gap-3 sm:grid-cols-2">
                            @if ($reconciliation['history_incomplete'])
                                <div role="status" class="rounded-2xl border border-warning/40 bg-warning-bg p-4 text-xs leading-5 text-warning-text">
                                    <strong class="block font-mono">HISTORY_INCOMPLETE</strong>
                                    Không đủ lịch sử để kết luận: thiếu số dư đầu kỳ, có khoảng nối không liên tục, hoặc chưa có biến động để đối chiếu (kể cả khi các bucket hiện bằng 0). Cảnh báo này tự nó không khẳng định số dư hiện tại sai.
                                </div>
                            @endif
                            @if ($reconciliation['observed_balance_diff'])
                                <div role="status" class="rounded-2xl border border-error/30 bg-error-bg p-4 text-xs leading-5 text-error-text">
                                    <strong class="block font-mono">OBSERVED_BALANCE_DIFF</strong>
                                    Số dư sau biến động cuối khác số dư bucket hiện tại của SKU. Không có điều chỉnh tự động nào được thực hiện.
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Balance Summary for Selected Variant --}}
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-border bg-surface-card p-3.5 shadow-sm">
                            <span class="inline-flex items-center text-[11px] font-semibold text-muted">
                                Có thể bán
                                <x-admin.tooltip text="Tồn kho - Đang giữ; không trừ trùng cách ly" />
                            </span>
                            <strong class="mt-1 block font-mono text-lg font-extrabold tabular-nums {{ ($selectedVariant->stock_qty - $selectedVariant->reserved_qty) > 0 ? 'text-main' : 'text-error' }}">
                                {{ $selectedVariant->stock_qty - $selectedVariant->reserved_qty }} đôi
                            </strong>
                        </div>
                        <div class="rounded-xl border border-border bg-surface-card p-3.5 shadow-sm">
                            <span class="block text-[11px] font-semibold text-muted">Đang giữ</span>
                            <strong class="mt-1 block font-mono text-lg font-extrabold tabular-nums text-main">
                                {{ $selectedVariant->reserved_qty }} đôi
                            </strong>
                        </div>
                        <div class="rounded-xl border border-border bg-surface-card p-3.5 shadow-sm">
                            <span class="block text-[11px] font-semibold text-muted">Cách ly</span>
                            <strong class="mt-1 block font-mono text-lg font-extrabold tabular-nums {{ $selectedVariant->quarantine_qty > 0 ? 'text-warning-text' : 'text-muted' }}">
                                {{ $selectedVariant->quarantine_qty }} đôi
                            </strong>
                        </div>
                    </div>

                    {{-- Filters --}}
                    @php
                        $advancedFiltersOpen = request()->filled('reference_type') || request()->filled('reference_id');
                        $resetHistoryUrl = route('admin.products.show', ['product' => $product, 'variant_id' => $selectedVariant->id]).'#inventory-history-section';
                    @endphp
                    <form
                        id="inventory-history-toolbar"
                        method="GET"
                        action="{{ route('admin.products.show', $product) }}#inventory-history-section"
                        @submit.prevent="submitFilter($el)"
                        x-data="{ open: {{ $advancedFiltersOpen ? 'true' : 'false' }} }"
                        class="space-y-3 rounded-2xl border border-border bg-surface-card p-4 shadow-sm"
                        aria-label="Lọc thẻ kho"
                    >
                        <input type="hidden" name="variant_id" value="{{ $selectedVariant->id }}">

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="space-y-1">
                                <label for="movement-from" class="text-[11px] font-bold text-main">Từ ngày</label>
                                <input id="movement-from" type="date" name="from" value="{{ request('from') }}" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                            </div>
                            <div class="space-y-1">
                                <label for="movement-to" class="text-[11px] font-bold text-main">Đến ngày</label>
                                <input id="movement-to" type="date" name="to" value="{{ request('to') }}" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                            </div>
                            <div class="space-y-1">
                                <label for="movement-action" class="text-[11px] font-bold text-main">Hoạt động</label>
                                <select id="movement-action" name="action" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                                    <option value="">Tất cả hoạt động</option>
                                    @foreach ($actions as $action)
                                        <option value="{{ $action->value }}" @selected(request('action') === $action->value)>{{ $action->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="min-h-10 rounded-xl bg-accent px-4 text-xs font-bold text-accent-text transition-colors hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">
                                    Lọc
                                </button>
                                <a
                                    href="{{ $resetHistoryUrl }}"
                                    @click.prevent="resetFilters('{{ $resetHistoryUrl }}', $el.closest('form'))"
                                    class="inline-flex min-h-10 items-center rounded-xl border border-border px-3 text-xs font-bold text-muted hover:bg-surface"
                                >
                                    Xóa lọc
                                </a>
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open ? 'true' : 'false'"
                                    class="ml-auto inline-flex min-h-10 items-center gap-1 rounded-xl border border-border px-3 text-xs font-bold text-main hover:bg-surface"
                                >
                                    <span>Lọc thêm</span>
                                    <svg class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div x-cloak x-show="open" x-transition.opacity.duration.150ms class="grid gap-3 border-t border-border/70 pt-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label for="movement-reference-type" class="text-[11px] font-bold text-main">Loại chứng từ</label>
                                <select id="movement-reference-type" name="reference_type" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                                    <option value="">Tất cả chứng từ</option>
                                    @foreach ($referenceTypes as $referenceType)
                                        <option value="{{ $referenceType->value }}" @selected(request('reference_type') === $referenceType->value)>{{ $referenceType->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label for="movement-reference-id" class="text-[11px] font-bold text-main">Mã chứng từ</label>
                                <input id="movement-reference-id" type="number" min="1" name="reference_id" value="{{ request('reference_id') }}" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                            </div>
                        </div>
                    </form>

                    {{-- Movements: Desktop 4-column table + Mobile cards --}}
                    <div class="overflow-hidden rounded-2xl border border-border bg-surface-card shadow-sm">
                        {{-- Desktop 4-column table --}}
                        <div class="hidden md:block">
                            <table class="w-full border-collapse text-left text-xs text-muted">
                                <caption class="sr-only">Lịch sử biến động tồn kho của SKU {{ $selectedVariant->sku }}</caption>
                                <thead class="border-b border-border bg-surface text-[11px] font-bold text-main">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Thời gian</th>
                                        <th scope="col" class="px-4 py-3">Hoạt động</th>
                                        <th scope="col" class="px-4 py-3">Thay đổi</th>
                                        <th scope="col" class="px-4 py-3">Người thực hiện</th>
                                    </tr>
                                </thead>
                                @forelse ($movements as $movement)
                                    @php
                                        $docUrl = $documentUrls[$movement->id] ?? null;
                                        $docLabel = ($movement->reference_type?->label() ?? $movement->reference_type?->value ?? 'Chứng từ').' #'.($movement->reference_id ?? '—');
                                        $deltas = array_values(array_filter([
                                            ['label' => 'Tồn kho', 'delta' => $movement->stock_delta, 'after' => $movement->stock_after],
                                            ['label' => 'Đang giữ', 'delta' => $movement->reserved_delta, 'after' => $movement->reserved_after],
                                            ['label' => 'Cách ly', 'delta' => $movement->quarantine_delta, 'after' => $movement->quarantine_after],
                                        ], fn (array $item): bool => $item['delta'] !== 0));
                                    @endphp
                                    <tbody x-data="{ open: false }" class="divide-y divide-border/60 border-b border-border last:border-b-0">
                                        <tr class="align-top transition-colors hover:bg-surface/50">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <time class="block font-medium text-main" datetime="{{ $movement->created_at?->toISOString() }}">{{ $movement->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i:s') }}</time>
                                                <span class="mt-0.5 block font-mono text-[11px] text-muted">#{{ $movement->id }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="block font-bold text-main">{{ $movement->action?->label() ?? $movement->action }}</span>
                                                @if ($docUrl !== null)
                                                    <a href="{{ $docUrl }}" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-accent hover:underline">
                                                        {{ $docLabel }}
                                                    </a>
                                                @else
                                                    <span class="mt-0.5 block text-[11px] text-muted">{{ $docLabel }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if ($deltas === [])
                                                    <span class="text-muted">Không đổi số lượng</span>
                                                @else
                                                    <ul class="space-y-0.5 font-mono text-[11px] text-main">
                                                        @foreach ($deltas as $item)
                                                            <li>{{ $item['label'] }} {{ $item['delta'] > 0 ? '+'.$item['delta'] : $item['delta'] }} đôi → {{ $item['after'] }} đôi</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="text-main">{{ $movement->actor_name_snapshot ?? $movement->actor?->full_name ?? 'Hệ thống' }}</span>
                                                    <button
                                                        type="button"
                                                        @click="open = !open"
                                                        :aria-expanded="open ? 'true' : 'false'"
                                                        class="shrink-0 rounded-lg border border-border bg-surface px-2 py-1 text-[11px] font-semibold text-muted transition-colors hover:border-accent/60 hover:text-main"
                                                    >
                                                        <span x-text="open ? 'Thu gọn' : 'Chi tiết'">Chi tiết</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr x-cloak x-show="open" class="bg-surface/40">
                                            <td colspan="4" class="px-4 py-3 text-[11px]">
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <div class="rounded-lg border border-border/80 bg-surface-card px-3 py-2">
                                                        <span class="block font-bold text-main">Tồn kho</span>
                                                        <span class="font-mono text-muted">Trước: {{ $movement->stock_before }} · Thay đổi: {{ $movement->stock_delta > 0 ? '+'.$movement->stock_delta : $movement->stock_delta }} · Sau: <strong class="text-main">{{ $movement->stock_after }}</strong></span>
                                                    </div>
                                                    <div class="rounded-lg border border-border/80 bg-surface-card px-3 py-2">
                                                        <span class="block font-bold text-main">Đang giữ</span>
                                                        <span class="font-mono text-muted">Trước: {{ $movement->reserved_before }} · Thay đổi: {{ $movement->reserved_delta > 0 ? '+'.$movement->reserved_delta : $movement->reserved_delta }} · Sau: <strong class="text-main">{{ $movement->reserved_after }}</strong></span>
                                                    </div>
                                                    <div class="rounded-lg border border-border/80 bg-surface-card px-3 py-2">
                                                        <span class="block font-bold text-main">Cách ly</span>
                                                        <span class="font-mono text-muted">Trước: {{ $movement->quarantine_before }} · Thay đổi: {{ $movement->quarantine_delta > 0 ? '+'.$movement->quarantine_delta : $movement->quarantine_delta }} · Sau: <strong class="text-main">{{ $movement->quarantine_after }}</strong></span>
                                                    </div>
                                                </div>
                                                <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 text-muted">
                                                    <span>
                                                        Chứng từ:
                                                        @if ($docUrl !== null)
                                                            <a href="{{ $docUrl }}" class="font-semibold text-accent hover:underline">{{ $docLabel }}</a>
                                                        @else
                                                            <span class="font-semibold text-main">{{ $docLabel }}</span>
                                                        @endif
                                                    </span>
                                                    @if ($canViewCost)
                                                        <span class="font-mono text-main">
                                                            @if ($movement->unit_cost_basis !== null)
                                                                Đơn vị: {{ $movement->unit_cost_basis }} ₫ ·
                                                            @endif
                                                            Bình quân: {{ $movement->average_cost_before }} → {{ $movement->average_cost_after }} ₫
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @empty
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="px-4 py-10 text-center text-xs text-muted">Không có biến động phù hợp với bộ lọc.</td>
                                        </tr>
                                    </tbody>
                                @endforelse
                            </table>
                        </div>

                        {{-- Mobile compact cards --}}
                        <div class="divide-y divide-border md:hidden">
                            @forelse ($movements as $movement)
                                @php
                                    $docUrl = $documentUrls[$movement->id] ?? null;
                                    $docLabel = ($movement->reference_type?->label() ?? $movement->reference_type?->value ?? 'Chứng từ').' #'.($movement->reference_id ?? '—');
                                    $deltas = array_values(array_filter([
                                        ['label' => 'Tồn kho', 'delta' => $movement->stock_delta, 'after' => $movement->stock_after],
                                        ['label' => 'Đang giữ', 'delta' => $movement->reserved_delta, 'after' => $movement->reserved_after],
                                        ['label' => 'Cách ly', 'delta' => $movement->quarantine_delta, 'after' => $movement->quarantine_after],
                                    ], fn (array $item): bool => $item['delta'] !== 0));
                                @endphp
                                <div x-data="{ open: false }" class="p-4 space-y-2.5 text-xs">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span class="block font-bold text-main">{{ $movement->action?->label() ?? $movement->action }}</span>
                                            @if ($docUrl !== null)
                                                <a href="{{ $docUrl }}" class="mt-0.5 inline-flex text-[11px] font-semibold text-accent hover:underline">{{ $docLabel }}</a>
                                            @else
                                                <span class="mt-0.5 block text-[11px] text-muted">{{ $docLabel }}</span>
                                            @endif
                                        </div>
                                        <div class="text-right shrink-0">
                                            <time class="block text-[11px] text-main" datetime="{{ $movement->created_at?->toISOString() }}">{{ $movement->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }}</time>
                                            <span class="font-mono text-[10px] text-muted">#{{ $movement->id }}</span>
                                        </div>
                                    </div>

                                    @if ($deltas === [])
                                        <p class="text-[11px] text-muted">Không đổi số lượng</p>
                                    @else
                                        <ul class="space-y-0.5 font-mono text-[11px] text-main">
                                            @foreach ($deltas as $item)
                                                <li>{{ $item['label'] }} {{ $item['delta'] > 0 ? '+'.$item['delta'] : $item['delta'] }} đôi → {{ $item['after'] }} đôi</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <div class="flex items-center justify-between pt-1 text-[11px] text-muted">
                                        <span>{{ $movement->actor_name_snapshot ?? $movement->actor?->full_name ?? 'Hệ thống' }}</span>
                                        <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" class="rounded-lg border border-border bg-surface px-2.5 py-1 font-semibold text-main">
                                            <span x-text="open ? 'Thu gọn' : 'Chi tiết'">Chi tiết</span>
                                        </button>
                                    </div>

                                    <div x-cloak x-show="open" class="space-y-1.5 rounded-xl border border-border/80 bg-surface/50 p-3 text-[11px] font-mono text-muted">
                                        <div>Tồn kho: {{ $movement->stock_before }} → {{ $movement->stock_after }} ({{ $movement->stock_delta > 0 ? '+'.$movement->stock_delta : $movement->stock_delta }})</div>
                                        <div>Đang giữ: {{ $movement->reserved_before }} → {{ $movement->reserved_after }} ({{ $movement->reserved_delta > 0 ? '+'.$movement->reserved_delta : $movement->reserved_delta }})</div>
                                        <div>Cách ly: {{ $movement->quarantine_before }} → {{ $movement->quarantine_after }} ({{ $movement->quarantine_delta > 0 ? '+'.$movement->quarantine_delta : $movement->quarantine_delta }})</div>
                                        @if ($canViewCost)
                                            <div class="pt-1 border-t border-border/60 text-main">
                                                @if ($movement->unit_cost_basis !== null)
                                                    <span class="block">Đơn vị: {{ $movement->unit_cost_basis }} ₫</span>
                                                @endif
                                                <span class="block">Bình quân: {{ $movement->average_cost_before }} → {{ $movement->average_cost_after }} ₫</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-10 text-center text-xs text-muted">Không có biến động phù hợp với bộ lọc.</p>
                            @endforelse
                        </div>

                        <x-ui.pagination :paginator="$movements" />
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-border bg-surface-card p-6 text-center text-xs text-muted space-y-3">
                    <div>
                        <span id="inventory-history-title" class="font-bold text-main block mb-1">Lịch sử kho</span>
                        Chọn nút <strong>Xem thẻ kho</strong> ở bảng size phía trên hoặc chọn biến thể bên dưới để xem lịch sử biến động và đối chiếu số dư.
                    </div>
                    @if ($variantOptions->isNotEmpty())
                        <div class="mx-auto max-w-xs">
                            <div
                                class="relative"
                                x-data="{
                                    open: false,
                                    query: '',
                                    options: @js($variantOptions),
                                    get filtered() {
                                        const q = this.query.trim().toLowerCase();
                                        if (!q) return this.options;
                                        return this.options.filter(o =>
                                            o.label.toLowerCase().includes(q) || o.sku.toLowerCase().includes(q)
                                        );
                                    },
                                }"
                                @click.outside="open = false"
                                @keydown.escape.window="open = false"
                            >
                                <button
                                    type="button"
                                    @click="open = !open; if (open) { $nextTick(() => $refs.emptyVariantSearch?.focus()); }"
                                    :aria-expanded="open ? 'true' : 'false'"
                                    class="flex min-h-10 w-full items-center justify-between gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-left text-xs text-main transition-colors hover:border-accent/60"
                                >
                                    <span class="font-semibold text-muted">Chọn phối màu &amp; size...</span>
                                    <svg class="h-4 w-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div x-cloak x-show="open" class="absolute left-0 right-0 z-30 mt-1.5 overflow-hidden rounded-xl border border-border bg-surface-card text-left shadow-lg">
                                    <div class="border-b border-border p-2">
                                        <input x-ref="emptyVariantSearch" type="search" x-model="query" placeholder="Tìm màu, size hoặc SKU..." class="w-full rounded-lg border border-border bg-bg px-2.5 py-1.5 text-xs text-main">
                                    </div>
                                    <ul class="max-h-56 overflow-y-auto divide-y divide-border/50">
                                        <template x-for="opt in filtered" :key="opt.id">
                                            <li>
                                                <button type="button" @click="open = false; query = ''; visitUrl(opt.url)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs hover:bg-surface text-main">
                                                    <span>
                                                        <span class="block font-semibold" x-text="opt.label"></span>
                                                        <span class="block font-mono text-[10px] text-muted" x-text="opt.sku"></span>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
