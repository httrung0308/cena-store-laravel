@props(['product' => [], 'categories' => [], 'action' => null, 'method' => 'POST'])
@php
    $product = is_array($product) ? $product : [];
    $isNew = empty($product['id']);
    $initialName = old('name', $product['name'] ?? '');
    $initialSlug = old('slug', $product['slug'] ?? '');
@endphp
<form @if ($action) action="{{ $action }}" @endif method="POST" enctype="multipart/form-data" class="space-y-8"
      x-data="liveSlug({ isNew: {{ $isNew ? 'true' : 'false' }}, initialName: @js($initialName), initialSlug: @js($initialSlug) })">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <section class="admin-card">
        <h2 class="mb-5 text-base font-bold text-main">Thông tin cơ bản</h2>
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="product-name" class="admin-label">Tên sản phẩm <span class="text-error">*</span></label>
                <input id="product-name" name="name" type="text" x-model="name" x-on:input="onNameInput"
                       class="input w-full border-border focus:border-accent text-main text-xs @error('name') border-error @enderror"
                       placeholder="Ví dụ: Giày Chạy Bộ Nam Pegasus 40" required>
                <x-forms.field-error name="name" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="product-slug" class="admin-label">Đường dẫn slug <span class="text-error">*</span></label>
                    <template x-if="isLocked">
                        <button type="button" x-on:click="unlockSlug" class="text-[11px] font-semibold text-accent hover:underline flex items-center gap-1">
                            <span>🔒</span> Mở khóa sửa slug
                        </button>
                    </template>
                    <template x-if="!isLocked && isNew">
                        <button type="button" x-on:click="regenerateSlug" class="text-[11px] text-muted hover:text-main flex items-center gap-1">
                            <span>🔄</span> Làm mới theo tên
                        </button>
                    </template>
                </div>
                <div class="relative mt-1">
                    <input id="product-slug" name="slug" type="text" x-ref="slugInput" x-model="slug" x-on:input="onSlugInput"
                           x-bind:readonly="isLocked"
                           x-bind:class="isLocked ? 'bg-surface-alt/70 cursor-not-allowed opacity-85' : 'bg-surface'"
                           class="input w-full border-border focus:border-accent text-main font-mono text-xs @error('slug') border-error @enderror"
                           placeholder="giay-chay-bo-nam-pegasus-40" required>
                </div>
                <p class="mt-1 text-[11px] text-muted">
                    <span x-show="isNew && !manuallyModified">Được tự động tạo theo tên sản phẩm. Bạn có thể sửa tay.</span>
                    <span x-show="isNew && manuallyModified">Đã chỉnh sửa thủ công (không tự ghi đè khi đổi tên).</span>
                    <span x-show="!isNew && isLocked">Được khóa để bảo vệ liên kết SEO. Bấm "Mở khóa" nếu thực sự cần đổi.</span>
                    <span x-show="!isNew && !isLocked" class="text-warning-text font-medium">Lưu ý: Thay đổi slug của sản phẩm cũ có thể ảnh hưởng liên kết ngoài.</span>
                </p>
                <x-forms.field-error name="slug" />
            </div>

            <x-admin.field name="brand_line" label="Dòng thương hiệu" :value="$product['brand_line'] ?? ''" placeholder="Ví dụ: Air Zoom, Ultraboost..." />

            <div>
                <label for="product-category" class="admin-label">Danh mục <span class="text-error">*</span></label>
                <select id="product-category" name="category_id" class="input w-full border-border bg-surface text-main text-xs" required>
                    <option value="">Chọn danh mục</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['id'] }}" @selected((string) old('category_id', $product['category_id'] ?? '') === (string) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
                <x-forms.field-error name="category_id" />
            </div>

            {{-- Base Price with currency formatting --}}
            <div x-data="currencyInput({ initialValue: @js(old('base_price', $product['base_price'] ?? '')) })">
                <label for="product-base-price" class="admin-label">Giá gốc (₫) <span class="text-error">*</span></label>
                <div class="relative mt-1">
                    <input id="product-base-price" type="text" inputmode="numeric" x-model="displayValue" x-on:input="onInput"
                           class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-10 @error('base_price') border-error @enderror"
                           placeholder="1.500.000" required>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted pointer-events-none">₫</span>
                </div>
                <input type="hidden" name="base_price" x-bind:value="rawValue">
                <x-forms.field-error name="base_price" />
            </div>

            {{-- Promotion Price with currency formatting --}}
            <div x-data="currencyInput({ initialValue: @js(old('promotion_price', $product['promotion_price'] ?? '')) })">
                <label for="product-promotion-price" class="admin-label">Giá khuyến mãi (₫, tùy chọn)</label>
                <div class="relative mt-1">
                    <input id="product-promotion-price" type="text" inputmode="numeric" x-model="displayValue" x-on:input="onInput"
                           class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-10 @error('promotion_price') border-error @enderror"
                           placeholder="1.200.000">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted pointer-events-none">₫</span>
                </div>
                <input type="hidden" name="promotion_price" x-bind:value="rawValue">
                <p class="mt-1 text-[11px] text-muted">Để trống nếu không áp dụng giá khuyến mãi. Phải nhỏ hơn hoặc bằng giá gốc.</p>
                <x-forms.field-error name="promotion_price" />
            </div>

            <x-admin.field name="warranty_months" id="product-warranty_months" label="Bảo hành (tháng)" :value="$product['warranty_months'] ?? 6" type="number" min="0" max="120" required hint="Mặc định 6 tháng." />
            <x-admin.field name="exchange_days" id="product-exchange_days" label="Đổi size (ngày)" :value="$product['exchange_days'] ?? 7" type="number" min="0" max="365" required hint="Mặc định 7 ngày." />

            <div class="md:col-span-2">
                <label for="product-description" class="admin-label">Mô tả sản phẩm</label>
                <textarea id="product-description" name="description" class="input w-full border-border bg-surface text-main text-xs leading-relaxed" rows="5" placeholder="Mô tả công nghệ đệm, chất liệu, độ thoáng khí...">{{ old('description', $product['description'] ?? '') }}</textarea>
                <x-forms.field-error name="description" />
            </div>
        </div>
    </section>
    {{ $slot ?? '' }}
    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" @disabled(! $action)>Lưu sản phẩm</button>
        <a href="{{ route('admin.products.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
    </div>
    @unless ($action)<p role="status" class="text-xs text-muted">Chưa thể lưu sản phẩm.</p>@endunless
</form>
