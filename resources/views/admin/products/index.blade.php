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
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="w-20">Ảnh</th>
                        <th>Sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá</th>
                        <th class="text-center">Size</th>
                        <th class="text-center">Tồn kho</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th class="text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    @php($thumbnail = $product->colors->first()?->thumbnail)
                    @php($stock = (int) ($product->total_stock ?? 0))
                    <tr>
                        <td>
                            @if ($thumbnail)
                                <img src="{{ $thumbnail->thumb_64_url ?? $thumbnail->thumbnail_url ?? $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="h-14 w-14 rounded-xl border border-border/60 bg-surface object-cover" loading="lazy">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-border/60 bg-surface text-[10px] text-muted">Chưa có</div>
                            @endif
                        </td>
                        <td class="min-w-[12rem]">
                            <span class="font-bold text-main block">{{ $product->name }}</span>
                            <span class="block text-xs text-muted font-mono mt-0.5">{{ $product->slug }}</span>
                        </td>
                        <td class="text-xs text-muted font-medium">{{ $product->category?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap">
                            @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                                <span class="block font-bold text-accent">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                                <span class="block text-xs text-muted line-through">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @else
                                <span class="font-bold text-main">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @endif
                        </td>
                        <td class="text-center font-medium">{{ $product->variants_count }}</td>
                        <td class="text-center">
                            <span @class(['badge text-xs', 'bg-success-bg border border-success/30 text-success-text' => $stock > 0, 'bg-error-bg border border-error/30 text-error-text' => $stock <= 0])>{{ $stock }}</span>
                        </td>
                        <td>
                            @if ($product->is_active)
                                <span class="badge bg-success-bg border border-success/30 text-success-text text-xs">Đang bán</span>
                            @else
                                <span class="badge bg-surface border border-border text-muted text-xs">Đã ẩn</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-xs text-muted">{{ $product->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y') }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <a class="btn-pill text-xs py-1.5 px-3" href="{{ route('admin.products.edit', $product) }}">Sửa</a>
                                @if ($product->is_active)
                                    <form method="POST" action="{{ route('admin.products.deactivate', $product) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn-pill text-xs py-1.5 px-3 hover:border-warning hover:text-warning-text">Ngừng bán</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-20 text-center text-sm text-muted">Chưa có sản phẩm.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-ui.pagination :paginator="$products" />
    </div>
@endsection
