@props(['product' => [], 'detailUrl' => null, 'imageUrl' => null, 'eager' => false])
@php
    $name = $product['name'] ?? 'Sản phẩm';
    $brand = $product['brand_line'] ?? null;
    $effectivePrice = $product['effective_price'] ?? null;
    $basePrice = $product['base_price'] ?? null;
    $soldOut = $product['sold_out'] ?? null;
    $fallbackImage = asset('legacy/img/men.jpg');
    $hasDiscount = is_int($effectivePrice) && is_int($basePrice) && $basePrice > $effectivePrice;
    $discountPercent = $hasDiscount ? round((($basePrice - $effectivePrice) / $basePrice) * 100) : 0;
@endphp
<article x-data="{ image: @js($imageUrl) }" {{ $attributes->class(['group flex h-full flex-col justify-between gap-3 rounded-2xl border border-border bg-surface-card p-4 shadow-card transition-[box-shadow,border-color] duration-200 hover:shadow-card-hover']) }}>
    <div class="space-y-3">
        <div class="relative aspect-square overflow-hidden rounded-xl border border-border/50 bg-surface">
            @if ($imageUrl)
                <a @if ($detailUrl) href="{{ $detailUrl }}" @endif aria-label="{{ $name }}" tabindex="-1" class="block h-full w-full">
                    <img src="{{ $imageUrl }}" x-bind:src="image" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" alt="{{ $name }}" width="400" height="400" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif class="h-full w-full object-cover aspect-square transition-transform duration-300 group-hover:scale-105 motion-reduce:transform-none">
                </a>
            @else
                <div class="flex h-full items-center justify-center text-xs text-muted">Chưa có ảnh sản phẩm</div>
            @endif
            @if ($soldOut === true)
                <span class="absolute bottom-2.5 left-2.5 rounded-full bg-main px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">Hết hàng</span>
            @endif
            @if ($hasDiscount)
                <span class="absolute right-2.5 top-2.5 badge-ruby text-[10px]">-{{ $discountPercent }}%</span>
            @endif
        </div>
        @if (! empty($product['colors']))
            <div class="flex flex-wrap gap-1.5" aria-label="Phối màu">
                @foreach ($product['colors'] as $color)
                    <a href="{{ $color['url'] }}" aria-label="{{ $color['name'] }}" class="h-8 w-8 overflow-hidden rounded-lg border border-border/80 shadow-subtle transition-colors hover:border-accent focus-visible:ring-2" x-on:mouseenter="image = @js($color['card_image_url'] ?? $color['image_url'])" x-on:mouseleave="image = @js($imageUrl)" x-on:focus="image = @js($color['card_image_url'] ?? $color['image_url'])" x-on:blur="image = @js($imageUrl)">
                        <img src="{{ $color['image_url'] }}" alt="{{ $color['name'] }}" loading="lazy" decoding="async" width="32" height="32" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-cover aspect-square">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="space-y-1 pt-1">
        @if ($brand)<p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $brand }}</p>@endif
        <h3 class="text-sm font-semibold leading-snug text-main">
            @if ($detailUrl)
                <a class="transition-colors hover:text-accent focus-visible:ring-2" href="{{ $detailUrl }}">{{ $name }}</a>
            @else
                {{ $name }}
            @endif
        </h3>
        @if (is_int($effectivePrice))
            <p class="text-sm font-bold text-accent">
                {{ ($product['from_price'] ?? false) ? 'Từ ' : '' }}{{ number_format($effectivePrice, 0, ',', '.') }} ₫
                @if ($hasDiscount)
                    <span class="ml-2 font-normal text-muted line-through text-xs">{{ number_format($basePrice, 0, ',', '.') }} ₫</span>
                @endif
            </p>
        @else
            <p class="text-xs text-muted">Giá chưa khả dụng</p>
        @endif
    </div>
</article>
