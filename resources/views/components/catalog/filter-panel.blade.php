@props(['action' => null, 'resetUrl' => null, 'filters' => [], 'genderOptions' => [], 'colorOptions' => [], 'categoryOptions' => []])
@php
    $filters = is_array($filters) ? $filters : [];
    $sortOptions = ['newest' => 'Mới nhất', 'price_asc' => 'Giá tăng', 'price_desc' => 'Giá giảm', 'name_asc' => 'Tên A–Z', 'name_desc' => 'Tên Z–A'];
@endphp
<form @if ($action) action="{{ $action }}" @endif method="GET" class="space-y-5 border border-zinc-200 p-4" aria-label="Bộ lọc sản phẩm">
    <x-forms.text-field name="search" label="Tìm kiếm" :value="$filters['search'] ?? ''" />
    <div><label class="text-lg font-semibold" for="catalog-category">Danh mục</label><select id="catalog-category" name="category" class="input block w-full"><option value="">Tất cả</option>
        @foreach ($categoryOptions as $item)<option value="{{ $item['slug'] }}" @selected(($filters['category'] ?? '') === $item['slug'])>{{ $item['name'] }}</option>@endforeach
    </select></div>
    <x-forms.choice-group name="gender" label="Giới tính" mode="radio" :options="$genderOptions" :value="$filters['gender'] ?? ''" />
    <x-forms.choice-group name="colors" label="Màu" mode="checkbox" :options="$colorOptions" :value="$filters['colors'] ?? []" />
    <x-forms.text-field name="size" label="Size" :value="$filters['size'] ?? ''" />
    <x-forms.text-field name="min_price" label="Giá tối thiểu (VND)" :value="$filters['min_price'] ?? ''" />
    <x-forms.text-field name="max_price" label="Giá tối đa (VND)" :value="$filters['max_price'] ?? ''" />
    <label class="flex items-center gap-2"><input type="checkbox" name="promotion" value="1" @checked(($filters['promotion'] ?? false) === true || ($filters['promotion'] ?? '') === '1')> Khuyến mãi</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="in_stock" value="1" @checked(($filters['in_stock'] ?? false) === true || ($filters['in_stock'] ?? '') === '1')> Chỉ còn hàng</label>
    <div><label for="catalog-sort" class="text-lg font-semibold">Sắp xếp</label><select id="catalog-sort" name="sort" class="input block w-full">
        @foreach ($sortOptions as $key => $display)<option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $display }}</option>@endforeach
    </select></div>
    <x-ui.button type="submit" :disabled="! $action">Áp dụng bộ lọc</x-ui.button>
    @if ($resetUrl)<a href="{{ $resetUrl }}" class="ml-4 text-sm underline">Xóa lọc</a>@endif
    @unless ($action)<p role="status" class="text-sm text-zinc-500">Chưa có trang danh mục để lọc.</p>@endunless
</form>
