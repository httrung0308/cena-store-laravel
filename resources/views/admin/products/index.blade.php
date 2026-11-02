@extends('layouts.admin')
@section('title', 'Sản phẩm')
@section('eyebrow', 'Quản lý sản phẩm')
@section('actions')
    <a class="btn-pill-dark" href="{{ route('admin.products.create') }}">+ Thêm sản phẩm</a>
@endsection
@php($images = app(App\Services\CatalogQueryService::class))
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.products.index')" id="product-search" label="Tìm sản phẩm" placeholder="Tìm sản phẩm..." :value="request('search', '')" />
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Ảnh</th><th>Sản phẩm</th><th>Danh mục</th><th>Giá</th><th>Size</th><th>Tồn kho</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody>
        @forelse ($products as $product)
            @php($thumbnail = $product->colors->first()?->thumbnail)
            @php($stock = (int) ($product->total_stock ?? 0))
            <tr>
                <td>@if ($thumbnail)<img src="{{ $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="h-16 w-16 rounded-lg object-cover" loading="lazy">@else<div class="flex h-16 w-16 items-center justify-center rounded-lg bg-zinc-100 text-xs text-zinc-400">Chưa có ảnh</div>@endif</td>
                <td class="min-w-[12rem]"><span class="font-semibold text-zinc-900">{{ $product->name }}</span><span class="block text-xs text-zinc-500">{{ $product->slug }}</span></td>
                <td>{{ $product->category?->name }}</td>
                <td class="whitespace-nowrap">
                    @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                        <span class="block font-semibold text-zinc-900">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                        <span class="block text-xs text-zinc-500 line-through">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                    @else
                        <span class="font-semibold text-zinc-900">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                    @endif
                </td>
                <td class="text-center">{{ $product->variants_count }}</td>
                <td><span @class(['badge', 'bg-green-100 text-green-600' => $stock > 0, 'bg-red-100 text-red-600' => $stock <= 0])>{{ $stock }}</span></td>
                <td>@if ($product->is_active)<span class="badge bg-green-100 text-green-600">Đang bán</span>@else<span class="badge bg-zinc-100 text-zinc-600">Đã ẩn</span>@endif</td>
                <td class="whitespace-nowrap">{{ $product->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y') }}</td>
                <td><div class="flex flex-wrap gap-2">
                    <a class="btn-pill" href="{{ route('admin.products.edit', $product) }}">Sửa</a>
                    @if ($product->is_active)<form method="POST" action="{{ route('admin.products.deactivate', $product) }}">@csrf @method('PATCH')<button class="btn-pill">Ngừng bán</button></form>@endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-20 text-center text-zinc-500">Chưa có sản phẩm.</td></tr>
        @endforelse
        </tbody></table></div>
        <x-ui.pagination :paginator="$products" />
    </div>
@endsection
