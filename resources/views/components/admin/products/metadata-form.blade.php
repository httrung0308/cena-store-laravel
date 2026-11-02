@props(['product' => [], 'categories' => [], 'action' => null, 'method' => 'POST'])
@php($product = is_array($product) ? $product : [])
<form @if ($action) action="{{ $action }}" @endif method="POST" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <section class="admin-card">
        <h2 class="mb-4 text-xl font-semibold">Thông tin cơ bản</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <x-admin.field name="name" label="Tên sản phẩm" :value="$product['name'] ?? ''" required />
            <x-admin.field name="slug" label="Đường dẫn slug" :value="$product['slug'] ?? ''" required />
            <x-admin.field name="brand_line" label="Dòng thương hiệu" :value="$product['brand_line'] ?? ''" />
            <div><label for="product-category" class="admin-label">Danh mục <span class="text-red-500">*</span></label><select id="product-category" name="category_id" class="input w-full border-zinc-300" required><option value="">Chọn</option>
                @foreach ($categories as $category)<option value="{{ $category['id'] }}" @selected((string) old('category_id', $product['category_id'] ?? '') === (string) $category['id'])>{{ $category['name'] }}</option>@endforeach
            </select><x-forms.field-error name="category_id" /></div>
            @foreach (['base_price' => 'Giá gốc (₫)', 'promotion_price' => 'Giá khuyến mãi (₫)', 'warranty_months' => 'Bảo hành (tháng)', 'exchange_days' => 'Đổi size (ngày)'] as $field => $label)
                <x-admin.field :name="$field" :id="'product-'.$field" :label="$label" :value="$product[$field] ?? ''" inputmode="numeric" pattern="[0-9]*" />
            @endforeach
            <div class="md:col-span-2"><label for="product-description" class="admin-label">Mô tả</label><textarea id="product-description" name="description" class="input w-full border-zinc-300" rows="5">{{ old('description', $product['description'] ?? '') }}</textarea><x-forms.field-error name="description" /></div>
        </div>
    </section>
    {{ $slot ?? '' }}
    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-pill-dark px-8 py-3" @disabled(! $action)>Lưu sản phẩm</button>
        <a href="{{ route('admin.products.index') }}" class="btn-pill px-8 py-3">Hủy</a>
    </div>
    @unless ($action)<p role="status">Chưa thể lưu sản phẩm.</p>@endunless
</form>
