@props(['action' => null, 'resetUrl' => null, 'filters' => [], 'genderOptions' => [], 'colorOptions' => [], 'categoryOptions' => []])
@php
    $filters = is_array($filters) ? $filters : [];
    $sortOptions = ['newest' => 'Mới nhất', 'price_asc' => 'Giá tăng', 'price_desc' => 'Giá giảm', 'name_asc' => 'Tên A–Z', 'name_desc' => 'Tên Z–A'];
@endphp
<form @if ($action) action="{{ $action }}" @endif method="GET" class="space-y-5 rounded-2xl border border-border bg-surface-card p-6 shadow-card" aria-label="Bộ lọc sản phẩm">
    <div class="flex items-center justify-between border-b border-border pb-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-main">Bộ Lọc</h2>
        @if ($resetUrl)<a href="{{ $resetUrl }}" class="text-xs font-medium text-muted hover:text-accent hover:underline">Xóa lọc</a>@endif
    </div>

    <x-forms.text-field name="search" label="Tìm kiếm" :value="$filters['search'] ?? ''" />

    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-muted" for="catalog-category">Danh mục</label>
        <select id="catalog-category" name="category" class="input block w-full">
            <option value="">Tất cả</option>
            @foreach ($categoryOptions as $item)<option value="{{ $item['slug'] }}" @selected(($filters['category'] ?? '') === $item['slug'])>{{ $item['name'] }}</option>@endforeach
        </select>
    </div>

    <x-forms.choice-group name="gender" label="Giới tính" mode="radio" :options="$genderOptions" :value="$filters['gender'] ?? ''" />
    <x-forms.choice-group name="colors" label="Màu" mode="checkbox" :options="$colorOptions" :value="$filters['colors'] ?? []" />
    <x-forms.text-field name="size" label="Size" :value="$filters['size'] ?? ''" />
    <x-forms.text-field name="min_price" label="Giá tối thiểu (VND)" :value="$filters['min_price'] ?? ''" />
    <x-forms.text-field name="max_price" label="Giá tối đa (VND)" :value="$filters['max_price'] ?? ''" />

    <div class="space-y-2 pt-1">
        <label class="flex items-center gap-2.5 text-xs font-medium text-main cursor-pointer">
            <input type="checkbox" name="promotion" value="1" class="native-checkbox rounded border-border text-accent focus:ring-accent" @checked(($filters['promotion'] ?? false) === true || ($filters['promotion'] ?? '') === '1')>
            <span>Khuyến mãi</span>
        </label>
        <label class="flex items-center gap-2.5 text-xs font-medium text-main cursor-pointer">
            <input type="checkbox" name="in_stock" value="1" class="native-checkbox rounded border-border text-accent focus:ring-accent" @checked(($filters['in_stock'] ?? false) === true || ($filters['in_stock'] ?? '') === '1')>
            <span>Chỉ còn hàng</span>
        </label>
    </div>

    <div>
        <label for="catalog-sort" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-muted">Sắp xếp</label>
        <select id="catalog-sort" name="sort" class="input block w-full">
            @foreach ($sortOptions as $key => $display)<option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $display }}</option>@endforeach
        </select>
    </div>

    <div class="pt-2">
        <x-ui.button type="submit" :disabled="! $action" class="w-full">Áp dụng bộ lọc</x-ui.button>
    </div>
    @unless ($action)<p role="status" class="text-xs text-muted">Chưa có trang danh mục để lọc.</p>@endunless
</form>
