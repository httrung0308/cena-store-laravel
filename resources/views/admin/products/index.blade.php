@extends('layouts.admin')
@section('title', 'Danh Sách Sản Phẩm')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5" href="{{ route('admin.products.create') }}">
        <span>+ Thêm sản phẩm</span>
    </a>
@endsection
@php($images = app(App\Services\CatalogQueryService::class))
@section('content')
    <div x-data="adminStatusToggle()" class="space-y-6">
        <x-admin.search-form :action="route('admin.products.index')" id="product-search" label="Tìm sản phẩm" placeholder="Tìm kiếm theo tên sản phẩm, mã slug..." :value="request('search', '')" />

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4 w-20">Ảnh</th>
                        <th class="p-4">Sản phẩm</th>
                        <th class="p-4">Danh mục</th>
                        <th class="p-4">Giá bán</th>
                        <th class="p-4 text-center">Tồn kho</th>
                        <th class="p-4 text-center">Trạng thái</th>
                        <th class="p-4 text-center">Biến thể</th>
                        <th class="p-4">Ngày tạo</th>
                        <th class="p-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($products as $product)
                    @php($thumbnail = $product->colors->first()?->thumbnail)
                    @php($stock = (int) ($product->total_stock ?? 0))
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4">
                            @if ($thumbnail)
                                <img src="{{ $thumbnail->thumb_64_url ?? $thumbnail->thumbnail_url ?? $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="w-14 h-14 object-cover rounded-xl border border-border bg-surface" loading="lazy" decoding="async" width="56" height="56">
                            @else
                                <div class="flex w-14 h-14 items-center justify-center rounded-xl border border-border bg-surface text-[10px] text-muted">Chưa có</div>
                            @endif
                        </td>
                        <td class="p-4">
                            <strong class="font-bold text-main text-sm block">{{ $product->name }}</strong>
                            <span class="text-[11px] text-muted font-mono">{{ $product->slug }}</span>
                        </td>
                        <td class="p-4 text-main font-bold">{{ $product->category?->name ?? '—' }}</td>
                        <td class="p-4 whitespace-nowrap">
                            @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                                <span class="font-extrabold text-accent text-sm block">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                                <span class="text-[11px] text-muted line-through">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @else
                                <span class="font-extrabold text-main text-sm block">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            <span @class([
                                'border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]',
                                'bg-surface text-main border-border' => $stock > 0,
                                'bg-error-bg text-error border-error/30' => $stock <= 0,
                            ])>{{ $stock }} đôi</span>
                        </td>
                        <td class="p-4 text-center">
                            @if ($product->is_active)
                                <span class="bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang bán</span>
                            @else
                                <span class="bg-muted/15 text-muted px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã ẩn</span>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            <span class="bg-surface text-muted border border-border px-2 py-0.5 rounded text-[11px] font-mono">{{ $product->variants_count }}</span>
                        </td>
                        <td class="p-4 whitespace-nowrap text-xs text-muted">{{ $product->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y') }}</td>
                        <td class="p-4 text-right whitespace-nowrap">
                            <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold mr-1 inline-block transition-colors" href="{{ route('admin.products.edit', $product) }}">Sửa</a>
                            @if ($product->is_active)
                                <form method="POST" action="{{ route('admin.products.deactivate', $product) }}" @submit.prevent="submitToggle($event, 'product-{{ $product->id }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="isBusy()" class="px-2.5 py-1 text-error hover:bg-error-bg rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Ngừng</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.products.activate', $product) }}" @submit.prevent="submitToggle($event, 'product-{{ $product->id }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="isBusy()" class="px-2.5 py-1 text-success hover:bg-success-bg rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed">Mở bán</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-12 text-center text-xs text-muted">Chưa có sản phẩm.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-ui.pagination :paginator="$products" />
        </div>
    </div>
@endsection
