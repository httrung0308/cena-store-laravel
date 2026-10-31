@props(['product' => [], 'categories' => [], 'action' => null, 'method' => 'POST'])
@php($product = is_array($product) ? $product : [])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-5">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <x-forms.text-field name="name" label="Tên sản phẩm" :value="$product['name'] ?? ''" required />
    <x-forms.text-field name="slug" label="Đường dẫn slug" :value="$product['slug'] ?? ''" required />
    <x-forms.text-field name="brand_line" label="Dòng thương hiệu" :value="$product['brand_line'] ?? ''" />
    <div><label for="product-category" class="text-lg font-semibold">Danh mục</label><select id="product-category" name="category_id" class="input block w-full" required><option value="">Chọn</option>
        @foreach ($categories as $category)<option value="{{ $category['id'] }}" @selected((string) old('category_id', $product['category_id'] ?? '') === (string) $category['id'])>{{ $category['name'] }}</option>@endforeach
    </select><x-forms.field-error name="category_id" /></div>
    <div><label for="product-description" class="text-lg font-semibold">Mô tả</label><textarea id="product-description" name="description" class="input block w-full" rows="5">{{ old('description', $product['description'] ?? '') }}</textarea><x-forms.field-error name="description" /></div>
    @foreach (['base_price' => 'Giá gốc (VND)', 'promotion_price' => 'Giá khuyến mãi (VND)', 'warranty_months' => 'Bảo hành (tháng)', 'exchange_days' => 'Đổi size (ngày)'] as $field => $label)
        <div><label for="product-{{ $field }}" class="text-lg font-semibold">{{ $label }}</label><input id="product-{{ $field }}" type="text" inputmode="numeric" pattern="[0-9]*" name="{{ $field }}" class="input block w-full" value="{{ old($field, $product[$field] ?? '') }}"><x-forms.field-error :name="$field" /></div>
    @endforeach
    <x-ui.button type="submit" :disabled="! $action">Lưu sản phẩm</x-ui.button>
    @unless ($action)<p role="status">Chưa thể lưu sản phẩm.</p>@endunless
</form>
