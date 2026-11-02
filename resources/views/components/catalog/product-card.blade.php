@props(['product' => [], 'detailUrl' => null, 'imageUrl' => null])
@php
    $name = $product['name'] ?? 'Sản phẩm';
    $brand = $product['brand_line'] ?? null;
    $effectivePrice = $product['effective_price'] ?? null;
    $basePrice = $product['base_price'] ?? null;
    $soldOut = $product['sold_out'] ?? null;
    $fallbackImage = asset('legacy/img/men.jpg');
@endphp
<article x-data="{ image: @js($imageUrl) }" {{ $attributes->class(['group flex h-full flex-col gap-3 bg-white text-black']) }}>
    <div class="relative aspect-square overflow-hidden bg-zinc-100">
        @if ($imageUrl)<a @if ($detailUrl) href="{{ $detailUrl }}" @endif aria-label="{{ $name }}" tabindex="-1"><img src="{{ $imageUrl }}" x-bind:src="image" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" alt="{{ $name }}" loading="lazy" class="h-full w-full object-cover transition-transform group-hover:scale-105 motion-reduce:transform-none"></a>
        @else<div class="flex h-full items-center justify-center text-xs text-zinc-500">Chưa có ảnh sản phẩm</div>@endif
        @if ($soldOut === true)<span class="absolute bottom-2 left-2 bg-black px-2 py-1 text-xs text-white">Hết hàng</span>@endif
    </div>
    @if (! empty($product['colors']))<div class="flex flex-wrap gap-2" aria-label="Phối màu">@foreach ($product['colors'] as $color)<a href="{{ $color['url'] }}" aria-label="{{ $color['name'] }}" class="h-12 w-12 overflow-hidden rounded border hover:border-black focus-visible:ring-4" x-on:mouseenter="image = @js($color['image_url'])" x-on:mouseleave="image = @js($imageUrl)" x-on:focus="image = @js($color['image_url'])" x-on:blur="image = @js($imageUrl)"><img src="{{ $color['image_url'] }}" alt="{{ $color['name'] }}" loading="lazy" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-cover"></a>@endforeach</div>@endif
    <div class="space-y-1">
        @if ($brand)<p class="text-xs text-zinc-600">{{ $brand }}</p>@endif
        <h3 class="text-base font-semibold">@if ($detailUrl)<a class="focus-visible:ring-4" href="{{ $detailUrl }}">{{ $name }}</a>@else{{ $name }}@endif</h3>
        @if (is_int($effectivePrice))
            <p class="text-sm font-semibold">{{ ($product['from_price'] ?? false) ? 'Từ ' : '' }}{{ number_format($effectivePrice, 0, ',', '.') }} ₫
            @if (is_int($basePrice) && $basePrice > $effectivePrice)<span class="ml-2 font-normal text-zinc-500 line-through">{{ number_format($basePrice, 0, ',', '.') }} ₫</span>@endif
            </p>
        @else<p class="text-sm text-zinc-500">Giá chưa khả dụng</p>@endif
    </div>
</article>
