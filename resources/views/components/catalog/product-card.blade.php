@props(['product' => [], 'detailUrl' => null, 'imageUrl' => null])
@php
    $name = $product['name'] ?? 'Sản phẩm';
    $brand = $product['brand_line'] ?? null;
    $effectivePrice = $product['effective_price'] ?? null;
    $basePrice = $product['base_price'] ?? null;
    $soldOut = $product['sold_out'] ?? null;
@endphp
<article {{ $attributes->class(['group flex h-full flex-col gap-3 bg-white text-black']) }}>
    <div class="relative aspect-square overflow-hidden bg-zinc-100">
        @if ($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $name }}" loading="lazy" class="h-full w-full object-cover transition-transform group-hover:scale-105 motion-reduce:transform-none">
        @else<div class="flex h-full items-center justify-center text-xs text-zinc-500">Chưa có ảnh sản phẩm</div>@endif
        @if ($soldOut === true)<span class="absolute bottom-2 left-2 bg-black px-2 py-1 text-xs text-white">Hết hàng</span>@endif
    </div>
    <div class="space-y-1">
        @if ($brand)<p class="text-xs text-zinc-600">{{ $brand }}</p>@endif
        <h3 class="text-base font-semibold">@if ($detailUrl)<a class="focus-visible:ring-4" href="{{ $detailUrl }}">{{ $name }}</a>@else{{ $name }}@endif</h3>
        @if (is_int($effectivePrice))
            <p class="text-sm font-semibold">{{ number_format($effectivePrice, 0, ',', '.') }} ₫
            @if (is_int($basePrice) && $basePrice > $effectivePrice)<span class="ml-2 font-normal text-zinc-500 line-through">{{ number_format($basePrice, 0, ',', '.') }} ₫</span>@endif
            </p>
        @else<p class="text-sm text-zinc-500">Giá chưa khả dụng</p>@endif
    </div>
</article>
