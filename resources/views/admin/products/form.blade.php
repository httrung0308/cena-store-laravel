@extends('layouts.admin')
@section('title', ($isDuplicate ?? false) ? 'Nhân bản sản phẩm' : ($product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm'))
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        @if ($product->exists && !($isDuplicate ?? false))
            <a class="px-4 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors"
               href="{{ route('admin.products.show', $product) }}">
                Xem chi tiết
            </a>
            <a class="px-4 py-2 bg-surface hover:bg-border/40 text-accent hover:text-accent-hover text-xs font-bold rounded-xl border border-border transition-colors"
               href="{{ route('admin.products.duplicate', $product) }}">
                Nhân bản sản phẩm này
            </a>
        @endif
        <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.products.index') }}">← Quay lại danh sách</a>
    </div>
@endsection

@php
    $classificationOptions = collect($classifications ?? [])->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'group_label' => $c->classification_group?->label() ?? 'Phân loại',
        'is_active' => (bool) $c->is_active,
    ])->values()->all();

    $hasNoImages = $product->exists && !($isDuplicate ?? false) && $product->colors()->whereDoesntHave('images')->exists();
    $actionUrl = ($product->exists && !($isDuplicate ?? false))
        ? route('admin.products.update', $product)
        : route('admin.products.store');
    $httpMethod = ($product->exists && !($isDuplicate ?? false)) ? 'PUT' : 'POST';
    $productRoutePrefix = \Illuminate\Support\Str::before(parse_url(route('shoes.show', '__SLUG__'), PHP_URL_PATH) ?: '/shoes/__SLUG__', '__SLUG__');
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-5"
     x-data="productFormWorkspace({
         product: @js($product->toArray()),
         isNew: {{ (!$product->exists || ($isDuplicate ?? false)) ? 'true' : 'false' }},
         isDuplicate: {{ ($isDuplicate ?? false) ? 'true' : 'false' }},
         action: @js($actionUrl),
         method: @js($httpMethod),
         indexUrl: @js(route('admin.products.index')),
         categories: @js($categories ?? []),
         classifications: @js($classificationOptions),
         selectedClassificationIds: @js($selectedClassificationIds ?? []),
         colors: @js($colors ?? []),
     })">

    @if ($isDuplicate ?? false)
        <div class="rounded-2xl border border-accent/30 bg-accent/10 p-4 text-xs text-main space-y-1">
            <p class="font-bold text-accent">Đang tạo bản sao từ sản phẩm: {{ $originalProduct->name ?? '' }}</p>
            <p class="text-muted">Phối màu và size đã được sao chép với mã SKU mới. Tồn kho bắt đầu từ 0. Tệp ảnh chỉ được nhân bản độc lập khi bạn bấm Lưu, không ảnh hưởng sản phẩm gốc.</p>
        </div>
    @endif

    @if ($hasNoImages)
        <div class="rounded-2xl border border-warning/30 bg-warning-bg p-4 text-xs text-warning-text space-y-1">
            <p class="font-bold">Sản phẩm chưa có đủ ảnh đại diện</p>
            <p>Một hoặc nhiều phối màu chưa có ảnh. Hãy tải ít nhất 1 ảnh cho mỗi màu để có thể mở bán công khai trên cửa hàng.</p>
        </div>
    @endif

    {{-- Cảnh báo lỗi mạng / hết phiên sau khi gửi --}}
    <template x-if="networkNotice">
        <div class="rounded-2xl border p-4 text-xs space-y-2"
             x-bind:class="networkNotice.type === 'session' ? 'border-error/40 bg-error-bg text-error-text' : 'border-warning/40 bg-warning-bg text-warning-text'"
             role="alert">
            <p class="font-bold" x-text="networkNotice.type === 'session' ? 'Phiên đăng nhập hết hạn' : 'Không xác định được kết quả lưu'"></p>
            <p x-text="networkNotice.message"></p>
            <div class="flex flex-wrap gap-3 pt-1">
                <a :href="indexUrl" target="_blank" rel="noopener" class="font-bold underline hover:no-underline">Mở danh sách sản phẩm (tab mới) để kiểm tra</a>
            </div>
        </div>
    </template>

    <form :action="action" method="POST" enctype="multipart/form-data" @submit.prevent="submitForm" novalidate
          class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] items-start">
        @csrf
        @if ($httpMethod === 'PUT') @method('PUT') @endif

        {{-- CỘT CHÍNH: Luồng nhập sản phẩm --}}
        <div class="space-y-6 min-w-0">

            {{-- KHỐI 1: Thông tin cơ bản & Danh mục --}}
            <section class="admin-card space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-main">1. Thông tin cơ bản</h2>
                    <span class="text-[11px] text-muted">Trường có <span class="text-error font-bold">*</span> là bắt buộc</span>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="product-name" class="admin-label">Tên sản phẩm <span class="text-error">*</span></label>
                        <input id="product-name" name="name" type="text" x-model="name" @input="onNameInput(); clearError('name')"
                               :data-has-error="Boolean(getError('name'))"
                               :class="getError('name') ? 'border-error ring-1 ring-error' : 'border-border focus:border-accent'"
                               class="input w-full bg-surface text-main text-xs"
                               placeholder="Ví dụ: Giày Chạy Bộ Nam Pegasus 40" required>
                        <p x-show="getError('name')" x-text="getError('name')" class="mt-1 text-xs font-medium text-error" role="alert"></p>

                        {{-- Slug tự sinh: mặc định ẩn, chỉ hiện xem trước URL và nút tùy chỉnh --}}
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted">
                            <span class="truncate">
                                Đường dẫn: <code class="font-mono text-main">{{ $productRoutePrefix }}<span x-text="slug || '...'"></span></code>
                            </span>
                            <button type="button" @click="showSlugEditor = !showSlugEditor"
                                    class="font-semibold text-accent hover:underline shrink-0">
                                <span x-text="showSlugEditor ? 'Ẩn chỉnh sửa slug' : 'Tùy chỉnh slug'"></span>
                            </button>
                        </div>

                        <div x-show="showSlugEditor" x-cloak class="mt-2.5 rounded-xl border border-border bg-surface/60 p-3 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label for="product-slug" class="text-xs font-semibold text-main">Đường dẫn slug <span class="text-error">*</span></label>
                                <div class="flex items-center gap-3">
                                    <button type="button" x-show="isSlugLocked" @click="unlockSlug()"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-accent hover:underline">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <span>Mở khóa sửa slug</span>
                                    </button>
                                    <button type="button" x-show="!isSlugLocked && isNew && !isDuplicate" @click="regenerateSlug()"
                                            class="text-[11px] font-semibold text-muted hover:text-main">
                                        Làm mới theo tên
                                    </button>
                                </div>
                            </div>
                            <input id="product-slug" name="slug" type="text" x-ref="slugInput" x-model="slug"
                                   @input="onSlugInput(); clearError('slug')"
                                   :readonly="isSlugLocked"
                                   :data-has-error="Boolean(getError('slug'))"
                                   :class="[isSlugLocked ? 'bg-surface-alt/70 cursor-not-allowed opacity-85' : 'bg-surface', getError('slug') ? 'border-error ring-1 ring-error' : 'border-border focus:border-accent']"
                                   class="input w-full text-main font-mono text-xs"
                                   placeholder="giay-chay-bo-nam-pegasus-40">
                            <p class="text-[11px] text-muted">
                                <span x-show="isNew && !manuallyModifiedSlug">Tự tạo từ tên sản phẩm. Nếu sửa tay, hệ thống sẽ giữ nguyên không ghi đè.</span>
                                <span x-show="!isNew && isSlugLocked">Đang khóa để giữ nguyên liên kết cũ khi đổi tên sản phẩm.</span>
                                <span x-show="!isNew && !isSlugLocked" class="text-warning-text font-medium">Lưu ý: Đổi slug sản phẩm đã bán có thể làm hỏng liên kết đã chia sẻ.</span>
                            </p>
                            <p x-show="getError('slug')" x-text="getError('slug')" class="text-xs font-medium text-error" role="alert"></p>
                        </div>
                    </div>

                    {{-- Danh mục có tìm kiếm tiếng Việt không dấu --}}
                    <div class="relative" @click.outside="categoryOpen = false">
                        <label for="category-search-btn" class="admin-label">Danh mục <span class="text-error">*</span></label>
                        <button id="category-search-btn" type="button"
                                @click="categoryOpen = !categoryOpen; if (categoryOpen) $nextTick(() => $refs.categorySearchInput?.focus())"
                                :data-has-error="Boolean(getError('category_id'))"
                                :class="getError('category_id') ? 'border-error ring-1 ring-error' : 'border-border'"
                                class="input mt-1 w-full bg-surface text-left text-xs flex items-center justify-between gap-2">
                            <span class="truncate" :class="selectedCategoryLabel ? 'text-main font-medium' : 'text-muted'"
                                  x-text="selectedCategoryLabel || 'Chọn danh mục sản phẩm...'"></span>
                            <svg class="w-4 h-4 text-muted shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div x-show="categoryOpen" x-cloak
                             class="absolute z-30 mt-1.5 w-full rounded-xl border border-border bg-surface-card shadow-elevated p-2 space-y-2">
                            <div class="relative">
                                <input x-ref="categorySearchInput" type="search" x-model="categoryQuery"
                                       @keydown.escape.prevent="categoryOpen = false"
                                       class="input w-full border-border bg-surface text-xs text-main pl-8"
                                       placeholder="Tìm danh mục (hỗ trợ không dấu)...">
                                <svg class="w-3.5 h-3.5 text-muted absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            </div>
                            <ul class="max-h-52 overflow-y-auto divide-y divide-border/40 text-xs" role="listbox">
                                <template x-for="cat in filteredCategories" :key="cat.id">
                                    <li>
                                        <button type="button" @click="selectCategory(cat)"
                                                :class="Number(category_id) === Number(cat.id) ? 'bg-accent/10 text-accent font-bold' : 'text-main hover:bg-surface'"
                                                class="w-full text-left px-2.5 py-2 rounded-lg flex items-center justify-between gap-2 transition-colors">
                                            <span class="truncate" x-text="cat.label"></span>
                                            <span x-show="!cat.is_active" class="text-[10px] px-1.5 py-0.5 rounded bg-muted/15 text-muted shrink-0">Đang ẩn</span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="filteredCategories.length === 0" class="p-3 text-center text-muted">
                                    Không tìm thấy danh mục phù hợp.
                                </li>
                            </ul>
                        </div>
                        <p x-show="getError('category_id')" x-text="getError('category_id')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>

                    <div>
                        <label for="product-brand-line" class="admin-label">Dòng thương hiệu</label>
                        <input id="product-brand-line" name="brand_line" type="text" x-model="brand_line"
                               class="input mt-1 w-full border-border bg-surface text-main text-xs"
                               placeholder="Ví dụ: Air Zoom, Pegasus, Dunk...">
                        <p x-show="getError('brand_line')" x-text="getError('brand_line')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="product-description" class="admin-label">Mô tả sản phẩm</label>
                        <textarea id="product-description" name="description" x-model="description" rows="4"
                                  class="input mt-1 w-full border-border bg-surface text-main text-xs leading-relaxed"
                                  placeholder="Mô tả công nghệ đệm, chất liệu thân giày, độ thoáng khí..."></textarea>
                        <p x-show="getError('description')" x-text="getError('description')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>
                </div>
            </section>

            {{-- KHỐI 2: Giá bán & Chính sách --}}
            <section class="admin-card space-y-5">
                <h2 class="text-base font-bold text-main">2. Giá bán &amp; Chính sách</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="product-base-price" class="admin-label">Giá gốc (₫) <span class="text-error">*</span></label>
                        <div class="relative mt-1">
                            <input id="product-base-price" type="text" inputmode="numeric"
                                   :value="base_price_display" @input="onBasePriceInput($event.target.value); clearError('base_price')"
                                   :data-has-error="Boolean(getError('base_price'))"
                                   :class="getError('base_price') ? 'border-error ring-1 ring-error' : 'border-border focus:border-accent'"
                                   class="input w-full bg-surface text-main font-mono text-xs pr-8"
                                   placeholder="1.500.000" required>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted pointer-events-none">₫</span>
                        </div>
                        <p x-show="getError('base_price')" x-text="getError('base_price')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="product-promo-price" class="admin-label">Giá khuyến mãi (₫, tùy chọn)</label>
                            <span x-show="discountPercent !== null" x-text="'Giảm ' + discountPercent + '%'"
                                  class="text-[11px] font-bold text-accent bg-accent/10 px-2 py-0.5 rounded-full"></span>
                        </div>
                        <div class="relative mt-1">
                            <input id="product-promo-price" type="text" inputmode="numeric"
                                   :value="promotion_price_display" @input="onPromoPriceInput($event.target.value); clearError('promotion_price')"
                                   :data-has-error="Boolean(getError('promotion_price'))"
                                   :class="getError('promotion_price') ? 'border-error ring-1 ring-error' : 'border-border focus:border-accent'"
                                   class="input w-full bg-surface text-main font-mono text-xs pr-8"
                                   placeholder="Để trống nếu không giảm giá">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted pointer-events-none">₫</span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted">Phải nhỏ hơn hoặc bằng giá gốc.</p>
                        <p x-show="getError('promotion_price')" x-text="getError('promotion_price')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>

                    <div>
                        <label for="product-warranty" class="admin-label">Bảo hành (tháng) <span class="text-error">*</span></label>
                        <input id="product-warranty" type="number" min="0" max="120" x-model="warranty_months"
                               @input="clearError('warranty_months')"
                               :class="getError('warranty_months') ? 'border-error ring-1 ring-error' : 'border-border'"
                               class="input mt-1 w-full bg-surface text-main text-xs font-mono">
                        <p x-show="getError('warranty_months')" x-text="getError('warranty_months')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>

                    <div>
                        <label for="product-exchange" class="admin-label">Đổi size (ngày) <span class="text-error">*</span></label>
                        <input id="product-exchange" type="number" min="0" max="365" x-model="exchange_days"
                               @input="clearError('exchange_days')"
                               :class="getError('exchange_days') ? 'border-error ring-1 ring-error' : 'border-border'"
                               class="input mt-1 w-full bg-surface text-main text-xs font-mono">
                        <p x-show="getError('exchange_days')" x-text="getError('exchange_days')" class="mt-1 text-xs font-medium text-error" role="alert"></p>
                    </div>
                </div>
            </section>

            {{-- KHỐI 3: Phối màu, Size & Ảnh (Dạng Tab từng màu) --}}
            <section class="admin-card space-y-5" aria-label="Phối màu, size và ảnh">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-main">3. Phối màu, Ảnh &amp; Biến thể Size</h2>
                        <p class="text-xs text-muted mt-0.5">Chọn tab màu để chỉnh sửa nhanh từng màu mà không phải cuộn dài.</p>
                    </div>
                    <button type="button" @click="addColor()"
                            class="px-3.5 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm">
                        + Thêm phối màu
                    </button>
                </div>

                <p x-show="getError('colors')" x-text="getError('colors')" class="text-xs font-semibold text-error bg-error-bg border border-error/30 rounded-xl p-3" role="alert"></p>

                {{-- Thanh Tab các phối màu --}}
                <div class="flex flex-wrap items-center gap-2 border-b border-border pb-3" role="tablist" aria-label="Danh sách phối màu">
                    <template x-for="(col, ci) in colors" :key="col.uid">
                        <button type="button" role="tab"
                                :aria-selected="(activeColorIndex === ci).toString()"
                                @click="activeColorIndex = ci"
                                :class="[
                                    activeColorIndex === ci
                                        ? 'border-accent bg-accent/10 text-main ring-1 ring-accent'
                                        : 'border-border bg-surface text-muted hover:text-main',
                                    colorHasError(ci) ? 'border-error text-error' : ''
                                ]"
                                class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition-colors">
                            <span class="w-3.5 h-3.5 rounded-full border border-border shrink-0" :style="'background-color:' + (col.primary_hex || '#000000')"></span>
                            <span class="max-w-[9rem] truncate" x-text="col.color_name || ('Phối màu ' + (ci + 1))"></span>
                            <span class="text-[10px] font-mono opacity-80" x-text="'(' + col.variants.length + 's · ' + col.images.length + 'ả)'"></span>
                            <span x-show="colorHasError(ci)" class="w-2 h-2 rounded-full bg-error shrink-0" title="Có lỗi cần kiểm tra"></span>
                            <span x-show="!colorHasError(ci) && col.images.length === 0" class="w-2 h-2 rounded-full bg-warning shrink-0" title="Chưa có ảnh"></span>
                        </button>
                    </template>
                </div>

                {{-- Panel phối màu đang chọn --}}
                <template x-if="activeColor">
                    <div class="space-y-6">
                        {{-- Thông tin màu --}}
                        <div class="rounded-xl border border-border bg-surface/40 p-4 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-bold text-main uppercase tracking-wide">
                                    Thông tin phối màu <span x-text="'#' + (activeColorIndex + 1)"></span>
                                </span>
                                <div class="flex items-center gap-4">
                                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                                        <input type="checkbox" class="native-checkbox text-accent rounded" x-model="activeColor.is_active">
                                        <span>Mở bán màu này</span>
                                    </label>
                                    <button type="button"
                                            x-show="colors.length > 1 && !activeColor.variants.some(v => v.documented)"
                                            @click="removeColor(activeColorIndex)"
                                            class="text-xs font-bold text-error hover:underline">
                                        Xóa phối màu
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label class="text-xs font-semibold text-muted">Tên màu <span class="text-error">*</span></label>
                                    <input class="input mt-1 w-full bg-surface text-xs text-main"
                                           x-model="activeColor.color_name"
                                           @input="clearError('colors.' + activeColorIndex + '.color_name')"
                                           :data-has-error="Boolean(getError('colors.' + activeColorIndex + '.color_name'))"
                                           :class="getError('colors.' + activeColorIndex + '.color_name') ? 'border-error ring-1 ring-error' : 'border-border'"
                                           placeholder="Ví dụ: Đen Trắng">
                                    <p x-show="getError('colors.' + activeColorIndex + '.color_name')"
                                       x-text="getError('colors.' + activeColorIndex + '.color_name')"
                                       class="mt-1 text-[11px] font-medium text-error" role="alert"></p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold text-muted">Mã phối màu <span class="text-error">*</span></label>
                                    <input class="input mt-1 w-full bg-surface text-xs text-main font-mono uppercase"
                                           x-model="activeColor.style_color_code"
                                           :readonly="activeColor.variants.some(v => v.documented)"
                                           @input="clearError('colors.' + activeColorIndex + '.style_color_code')"
                                           :data-has-error="Boolean(getError('colors.' + activeColorIndex + '.style_color_code'))"
                                           :class="getError('colors.' + activeColorIndex + '.style_color_code') ? 'border-error ring-1 ring-error' : 'border-border'"
                                           placeholder="Ví dụ: BLK, CW2288">
                                    <p x-show="getError('colors.' + activeColorIndex + '.style_color_code')"
                                       x-text="getError('colors.' + activeColorIndex + '.style_color_code')"
                                       class="mt-1 text-[11px] font-medium text-error" role="alert"></p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold text-muted">Nhóm màu</label>
                                    <select class="input mt-1 w-full border-border bg-surface text-xs text-main"
                                            x-model="activeColor.color_key">
                                        @foreach(config('store.palette') as $key => $option)
                                            <option value="{{ $key }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold text-muted">Mã HEX <span class="text-error">*</span></label>
                                    <div class="flex items-center gap-2 mt-1">
                                        <input type="color" class="h-9 w-9 rounded-lg border border-border cursor-pointer bg-surface p-0.5 shrink-0"
                                               x-model="activeColor.primary_hex" aria-label="Chọn màu HEX">
                                        <input class="input w-full bg-surface text-xs text-main font-mono uppercase"
                                               x-model="activeColor.primary_hex"
                                               :class="getError('colors.' + activeColorIndex + '.primary_hex') ? 'border-error ring-1 ring-error' : 'border-border'">
                                    </div>
                                    <p x-show="getError('colors.' + activeColorIndex + '.primary_hex')"
                                       x-text="getError('colors.' + activeColorIndex + '.primary_hex')"
                                       class="mt-1 text-[11px] font-medium text-error" role="alert"></p>
                                </div>
                            </div>
                        </div>

                        {{-- ẢNH PHỐI MÀU (Multi-file upload + Drag & Drop + Lưới xem trước) --}}
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold text-main uppercase">Ảnh phối màu (<span x-text="activeColor.images.length"></span>)</h3>
                                    <p class="text-[11px] text-muted">Chọn nhiều ảnh một lần. Mô tả ảnh (alt) tự tạo từ tên sản phẩm và màu, không cần nhập tay.</p>
                                </div>
                                <button type="button" @click="activeColor.showUrlAdder = !activeColor.showUrlAdder"
                                        class="text-[11px] font-semibold text-muted hover:text-accent">
                                    <span x-text="activeColor.showUrlAdder ? 'Đóng nhập URL' : 'Hoặc thêm ảnh từ URL HTTPS'"></span>
                                </button>
                            </div>

                            {{-- Lỗi ảnh cấp màu --}}
                            <p x-show="getError('colors.' + activeColorIndex + '.images')"
                               x-text="getError('colors.' + activeColorIndex + '.images')"
                               class="text-xs font-medium text-error bg-error-bg border border-error/30 rounded-xl p-2.5" role="alert"></p>

                            {{-- Input URL phụ (ẩn mặc định) --}}
                            <div x-show="activeColor.showUrlAdder" x-cloak class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-surface p-3">
                                <input type="url" x-model="activeColor.newExternalUrl"
                                       class="input flex-1 min-w-[200px] border-border bg-surface-card text-xs text-main"
                                       placeholder="https://example.com/giay-chay-bo.jpg">
                                <button type="button" @click="addExternalUrlToColor(activeColor)"
                                        class="px-3.5 py-2 rounded-xl bg-accent text-accent-text text-xs font-bold hover:bg-accent-hover transition-colors">
                                    Thêm URL
                                </button>
                            </div>

                            {{-- Vùng kéo-thả & chọn nhiều tệp --}}
                            <label @dragover.prevent="activeColor.dragOver = true"
                                   @dragleave.prevent="activeColor.dragOver = false"
                                   @drop.prevent="handleFileDrop(activeColor, $event)"
                                   :class="activeColor.dragOver ? 'border-accent bg-accent/5' : 'border-border bg-surface/50 hover:border-accent/60'"
                                   class="flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed p-5 text-center cursor-pointer transition-colors">
                                <svg class="w-6 h-6 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                                <span class="text-xs font-bold text-main">Kéo thả hoặc bấm để chọn nhiều ảnh (JPEG, PNG, WebP · tối đa 5MB/ảnh)</span>
                                <span class="text-[11px] text-muted">Có thể chọn cùng lúc nhiều góc chụp cho phối màu này</span>
                                <input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp"
                                       @change="addFilesToColor(activeColor, $event.target.files); $event.target.value = ''">
                            </label>

                            {{-- Cảnh báo thiếu ảnh --}}
                            <div x-show="activeColor.images.length === 0"
                                 class="rounded-xl border border-warning/40 bg-warning-bg/60 p-3 text-xs text-warning-text">
                                Phối màu này chưa có ảnh. Cần ít nhất 1 ảnh đại diện để sản phẩm đủ điều kiện hiển thị công khai.
                            </div>

                            {{-- Lưới thẻ ảnh đã chọn / đã lưu --}}
                            <div x-show="activeColor.images.length > 0" class="grid gap-3 grid-cols-2 sm:grid-cols-3 md:grid-cols-4">
                                <template x-for="(img, ii) in activeColor.images" :key="img.uid">
                                    <div class="rounded-xl border bg-surface p-2 space-y-2 transition-colors"
                                         :class="img.thumbnail ? 'border-accent ring-1 ring-accent/40' : 'border-border'">
                                        <div class="relative aspect-square rounded-lg overflow-hidden bg-surface-card border border-border/60 flex items-center justify-center">
                                            <template x-if="!img.broken && (img.previewUrl || img.existingUrl || img.external_url)">
                                                <img :src="img.previewUrl || img.existingUrl || img.external_url"
                                                     :alt="img.alt_text || defaultAltFor(activeColor)"
                                                     x-on:error="img.broken = true"
                                                     class="w-full h-full object-cover" loading="lazy">
                                            </template>
                                            <template x-if="img.broken || !(img.previewUrl || img.existingUrl || img.external_url)">
                                                <span class="text-[11px] text-error font-semibold px-2 text-center">Không tải được ảnh</span>
                                            </template>

                                            <span x-show="img.thumbnail"
                                                  class="absolute top-1.5 left-1.5 rounded-md bg-accent px-1.5 py-0.5 text-[10px] font-bold text-accent-text shadow-sm">
                                                Ảnh chính
                                            </span>
                                        </div>

                                        <div class="flex items-center justify-between gap-1 text-[11px]">
                                            <button type="button" x-show="!img.thumbnail" @click="setThumbnail(activeColor, img.uid)"
                                                    class="font-semibold text-accent hover:underline">
                                                Đặt làm ảnh chính
                                            </button>
                                            <span x-show="img.thumbnail" class="font-semibold text-success">Đang là ảnh chính</span>

                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="moveImage(activeColor, ii, -1)" :disabled="ii === 0"
                                                        class="px-1.5 py-0.5 rounded border border-border text-muted hover:text-main disabled:opacity-30" title="Sang trái">←</button>
                                                <button type="button" @click="moveImage(activeColor, ii, 1)" :disabled="ii === activeColor.images.length - 1"
                                                        class="px-1.5 py-0.5 rounded border border-border text-muted hover:text-main disabled:opacity-30" title="Sang phải">→</button>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-2 pt-0.5 border-t border-border/50 text-[11px]">
                                            <button type="button" @click="img.showAltEditor = !img.showAltEditor"
                                                    class="text-muted hover:text-main">
                                                <span x-text="img.showAltEditor ? 'Ẩn mô tả' : 'Sửa mô tả'"></span>
                                            </button>
                                            <button type="button" @click="removeImage(activeColor, img.uid)"
                                                    class="font-semibold text-error hover:underline">
                                                Xóa
                                            </button>
                                        </div>

                                        <div x-show="img.showAltEditor" x-cloak class="space-y-1">
                                            <input type="text" x-model="img.alt_text"
                                                   :placeholder="defaultAltFor(activeColor)"
                                                   class="input w-full border-border bg-surface-card text-[11px] text-main py-1 px-2">
                                            <p x-show="getError('colors.' + activeColorIndex + '.images.' + ii + '.alt_text')"
                                               x-text="getError('colors.' + activeColorIndex + '.images.' + ii + '.alt_text')"
                                               class="text-[10px] text-error" role="alert"></p>
                                        </div>
                                        <p x-show="getError('colors.' + activeColorIndex + '.images.' + ii)"
                                           x-text="getError('colors.' + activeColorIndex + '.images.' + ii)"
                                           class="text-[10px] text-error" role="alert"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- BẢNG SIZE, GIÁ RIÊNG & MÃ SKU --}}
                        <div class="space-y-3 pt-2 border-t border-border/70">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold text-main uppercase">Danh sách size &amp; giá riêng (<span x-text="activeColor.variants.length"></span>)</h3>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" @click="activeColor.showSkuColumn = !activeColor.showSkuColumn"
                                            class="text-[11px] font-semibold text-muted hover:text-main">
                                        <span x-text="activeColor.showSkuColumn ? 'Ẩn cột mã SKU' : 'Hiện / sửa mã SKU'"></span>
                                    </button>
                                    <button type="button" @click="addVariant(activeColor, '')"
                                            class="text-xs font-bold text-accent hover:underline">
                                        + Thêm 1 size
                                    </button>
                                </div>
                            </div>

                            {{-- Thanh sinh nhanh dải size có xem trước --}}
                            <div class="rounded-xl border border-border bg-surface/60 p-3 space-y-2.5">
                                <div class="flex flex-wrap items-end gap-2.5">
                                    <div class="w-24">
                                        <label class="text-[11px] font-semibold text-muted block">Từ size</label>
                                        <input type="text" x-model="activeColor.rangeStart" placeholder="39"
                                               class="input mt-1 w-full border-border bg-surface-card text-xs text-main font-mono">
                                    </div>
                                    <div class="w-24">
                                        <label class="text-[11px] font-semibold text-muted block">Đến size</label>
                                        <input type="text" x-model="activeColor.rangeEnd" placeholder="43"
                                               class="input mt-1 w-full border-border bg-surface-card text-xs text-main font-mono">
                                    </div>
                                    <div class="w-32">
                                        <label class="text-[11px] font-semibold text-muted block">Bước nhảy</label>
                                        <select x-model="activeColor.rangeStep" class="input mt-1 w-full border-border bg-surface-card text-xs text-main">
                                            <option value="0.5">Nửa size (0.5)</option>
                                            <option value="1">Nguyên size (1)</option>
                                        </select>
                                    </div>
                                    <button type="button" @click="previewSizeRange(activeColor)"
                                            class="px-3 py-2 rounded-xl border border-border bg-surface-card hover:bg-surface text-xs font-bold text-main transition-colors">
                                        Xem trước dải size
                                    </button>
                                    <button type="button" @click="applySizeRange(activeColor)"
                                            class="px-3.5 py-2 rounded-xl bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold transition-colors">
                                        Áp dụng vào bảng
                                    </button>
                                </div>
                                <p x-show="activeColor.rangePreviewError" x-text="activeColor.rangePreviewError" class="text-[11px] text-error font-medium"></p>
                                <div x-show="activeColor.rangePreview.length > 0" class="flex flex-wrap items-center gap-1.5 pt-1">
                                    <span class="text-[11px] text-muted mr-1">Xem trước:</span>
                                    <template x-for="item in activeColor.rangePreview" :key="item.size">
                                        <span class="px-2 py-0.5 rounded-md text-[11px] font-mono border"
                                              :class="item.exists ? 'border-border bg-muted/10 text-muted line-through' : 'border-accent/40 bg-accent/10 text-accent font-bold'"
                                              x-text="item.size"
                                              :title="item.exists ? 'Size đã có (sẽ bỏ qua)' : 'Size mới sẽ thêm'"></span>
                                    </template>
                                </div>
                            </div>

                            <p x-show="getError('colors.' + activeColorIndex + '.variants')"
                               x-text="getError('colors.' + activeColorIndex + '.variants')"
                               class="text-xs font-medium text-error" role="alert"></p>

                            {{-- Bảng biến thể --}}
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-surface text-muted font-bold border-b border-border">
                                        <tr>
                                            <th class="p-2.5 whitespace-nowrap">Size <span class="text-error">*</span></th>
                                            <th x-show="activeColor.showSkuColumn" class="p-2.5 whitespace-nowrap">Mã SKU (tự sinh nếu trống)</th>
                                            <th class="p-2.5 whitespace-nowrap">Giá riêng (₫)</th>
                                            <th class="p-2.5 text-center whitespace-nowrap">Bán</th>
                                            <th class="p-2.5 text-right whitespace-nowrap">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-surface-card">
                                        <template x-for="(variant, vi) in activeColor.variants" :key="variant.uid">
                                            <tr class="hover:bg-surface/40">
                                                <td class="p-2.5 align-top w-28">
                                                    <input type="text" x-model="variant.size_value"
                                                           :readonly="variant.documented"
                                                           @input="clearError('colors.' + activeColorIndex + '.variants.' + vi + '.size_value')"
                                                           :data-has-error="Boolean(getError('colors.' + activeColorIndex + '.variants.' + vi + '.size_value'))"
                                                           :class="[variant.documented ? 'bg-surface-alt/70 cursor-not-allowed opacity-80' : 'bg-surface', getError('colors.' + activeColorIndex + '.variants.' + vi + '.size_value') ? 'border-error ring-1 ring-error' : 'border-border']"
                                                           class="input w-20 text-xs font-mono text-main py-1.5 px-2" placeholder="40">
                                                    <span x-show="variant.documented" class="block text-[10px] text-muted mt-0.5">Có chứng từ</span>
                                                    <p x-show="getError('colors.' + activeColorIndex + '.variants.' + vi + '.size_value')"
                                                       x-text="getError('colors.' + activeColorIndex + '.variants.' + vi + '.size_value')"
                                                       class="mt-1 text-[10px] text-error" role="alert"></p>
                                                </td>

                                                <td x-show="activeColor.showSkuColumn" class="p-2.5 align-top min-w-[11rem]">
                                                    <input type="text" x-model="variant.sku"
                                                           :readonly="variant.documented"
                                                           @input="variant.skuManuallySet = true; clearError('colors.' + activeColorIndex + '.variants.' + vi + '.sku')"
                                                           :data-has-error="Boolean(getError('colors.' + activeColorIndex + '.variants.' + vi + '.sku'))"
                                                           :class="[variant.documented ? 'bg-surface-alt/70 cursor-not-allowed opacity-80' : 'bg-surface', getError('colors.' + activeColorIndex + '.variants.' + vi + '.sku') ? 'border-error ring-1 ring-error' : 'border-border']"
                                                           class="input w-full text-xs font-mono uppercase text-main py-1.5 px-2"
                                                           placeholder="Tự sinh khi lưu">
                                                    <p x-show="getError('colors.' + activeColorIndex + '.variants.' + vi + '.sku')"
                                                       x-text="getError('colors.' + activeColorIndex + '.variants.' + vi + '.sku')"
                                                       class="mt-1 text-[10px] text-error" role="alert"></p>
                                                </td>

                                                <td class="p-2.5 align-top min-w-[10rem]">
                                                    <input type="text" inputmode="numeric"
                                                           :value="variant.price_override_display"
                                                           @input="onVariantPriceInput(variant, $event.target.value); clearError('colors.' + activeColorIndex + '.variants.' + vi + '.price_override')"
                                                           :class="getError('colors.' + activeColorIndex + '.variants.' + vi + '.price_override') ? 'border-error ring-1 ring-error' : 'border-border'"
                                                           class="input w-full bg-surface text-xs font-mono text-main py-1.5 px-2"
                                                           placeholder="Kế thừa giá chung">
                                                    <span class="block text-[10px] mt-0.5"
                                                          :class="variant.price_override ? 'text-accent font-semibold' : 'text-muted'"
                                                          x-text="variant.price_override ? 'Ghi đè giá riêng' : ('Kế thừa: ' + (promotion_price_display || base_price_display || '0') + ' ₫')"></span>
                                                    <p x-show="getError('colors.' + activeColorIndex + '.variants.' + vi + '.price_override')"
                                                       x-text="getError('colors.' + activeColorIndex + '.variants.' + vi + '.price_override')"
                                                       class="mt-1 text-[10px] text-error" role="alert"></p>
                                                </td>

                                                <td class="p-2.5 align-top text-center pt-3.5">
                                                    <input type="checkbox" class="native-checkbox text-accent rounded" x-model="variant.is_active" aria-label="Bán size này">
                                                </td>

                                                <td class="p-2.5 align-top text-right pt-3 whitespace-nowrap">
                                                    <button type="button" x-show="!variant.documented"
                                                            @click="removeVariant(activeColor, variant.uid)"
                                                            class="text-xs font-bold text-error hover:underline">
                                                        Bỏ size
                                                    </button>
                                                    <span x-show="variant.documented" class="text-[11px] text-muted">Khóa</span>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="activeColor.variants.length === 0">
                                            <td colspan="5" class="p-6 text-center text-xs text-muted">
                                                Chưa có size nào cho màu này. Dùng bộ sinh nhanh dải size ở trên hoặc bấm "+ Thêm 1 size".
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        {{-- CỘT PHẢI: Trạng thái xuất bản, Lưu & Phân loại --}}
        <aside class="space-y-5 lg:sticky lg:top-6">
            {{-- Card Lưu & Trạng thái --}}
            <section class="admin-card space-y-4">
                <h2 class="text-sm font-bold text-main">Trạng thái &amp; Lưu</h2>

                <div class="space-y-2.5">
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                        <input type="checkbox" name="is_active" value="1" class="native-checkbox text-accent rounded" x-model="is_active">
                        <span>Đang bán sản phẩm này</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                        <input type="checkbox" name="is_featured" value="1" class="native-checkbox text-accent rounded" x-model="is_featured">
                        <span>Sản phẩm nổi bật (Trang chủ)</span>
                    </label>
                </div>

                <div class="rounded-xl bg-surface p-3 border border-border text-[11px] space-y-1">
                    <div class="flex justify-between">
                        <span class="text-muted">Phối màu:</span>
                        <span class="font-bold text-main font-mono" x-text="colors.length"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">Tổng biến thể size:</span>
                        <span class="font-bold text-main font-mono" x-text="totalVariantsCount"></span>
                    </div>
                    <div x-show="colorsMissingImages > 0" class="pt-1 text-warning-text font-medium">
                        Còn <span x-text="colorsMissingImages"></span> màu chưa có ảnh.
                    </div>
                </div>

                <div class="space-y-2 pt-1">
                    <button type="submit" :disabled="submitting"
                            class="w-full py-2.5 px-4 rounded-xl bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!submitting">Lưu sản phẩm</span>
                        <span x-show="submitting" x-cloak>Đang lưu dữ liệu...</span>
                    </button>
                    <a href="{{ route('admin.products.index') }}"
                       class="block w-full text-center py-2 px-4 rounded-xl border border-border bg-surface hover:bg-border/30 text-xs font-semibold text-muted hover:text-main transition-colors">
                        Hủy / Quay lại
                    </a>
                </div>
            </section>

            {{-- Card Phân loại / Nhãn (Tìm kiếm tiếng Việt không dấu) --}}
            <section class="admin-card space-y-3" aria-label="Phân loại sản phẩm">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-main">Phân loại / Nhãn</h2>
                    <span class="text-[11px] text-muted font-mono" x-text="selectedClassificationIds.length + ' đã chọn'"></span>
                </div>

                {{-- Chip đã chọn (luôn giữ khi gõ tìm kiếm mới) --}}
                <div x-show="selectedClassificationObjects.length > 0" class="flex flex-wrap gap-1.5">
                    <template x-for="sel in selectedClassificationObjects" :key="sel.id">
                        <span class="inline-flex items-center gap-1 rounded-full bg-accent/10 border border-accent/30 px-2.5 py-0.5 text-[11px] font-semibold text-accent">
                            <span x-text="sel.name"></span>
                            <button type="button" @click="removeClassification(sel.id)"
                                    class="hover:text-error font-bold leading-none" :aria-label="'Bỏ chọn ' + sel.name">×</button>
                        </span>
                    </template>
                </div>

                <div class="relative">
                    <input type="search" x-model="classificationQuery"
                           class="input w-full border-border bg-surface text-xs text-main pl-8"
                           placeholder="Tìm phân loại (không dấu)...">
                    <svg class="w-3.5 h-3.5 text-muted absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>

                <div class="max-h-64 overflow-y-auto space-y-3 pr-1">
                    <template x-for="group in filteredClassifications" :key="group.label">
                        <div class="space-y-1.5">
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-muted" x-text="group.label"></h3>
                            <div class="space-y-1">
                                <template x-for="item in group.items" :key="item.id">
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs transition-colors"
                                           :class="!item.is_active && !isClassificationSelected(item.id) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-surface'">
                                        <input type="checkbox" name="classification_ids[]" :value="item.id" class="native-checkbox text-accent rounded"
                                               :checked="isClassificationSelected(item.id)"
                                               :disabled="!item.is_active && !isClassificationSelected(item.id)"
                                               @change="toggleClassification(item.id)">
                                        <span class="text-main truncate" x-text="item.name"></span>
                                        <span x-show="!item.is_active" class="ml-auto text-[10px] text-muted">Ngưng</span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p x-show="filteredClassifications.length === 0" class="py-4 text-center text-xs text-muted">
                        Không tìm thấy phân loại phù hợp.
                    </p>
                </div>
                <p x-show="getError('classification_ids')" x-text="getError('classification_ids')" class="text-xs font-medium text-error" role="alert"></p>
            </section>
        </aside>
    </form>
</div>
@endsection
