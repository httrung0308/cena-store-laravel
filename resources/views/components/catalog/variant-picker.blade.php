@props(['variants' => [], 'colors' => [], 'initialColorId' => null, 'action' => null])
@php($fallbackImage = asset('legacy/img/men.jpg'))
<section x-data="variantPicker({variants: @js($variants), initialColorId: @js($initialColorId)})" class="space-y-5" aria-label="Chọn màu và size">
    <div class="flex flex-wrap gap-2" role="group" aria-label="Phối màu">
        @foreach ($colors as $color)
            <button type="button" class="h-20 w-20 overflow-hidden rounded border-2 focus-visible:ring-4" x-bind:class="String(colorId) === @js((string) $color['id']) ? 'border-black' : 'border-transparent'" x-bind:aria-pressed="(String(colorId) === @js((string) $color['id'])).toString()" aria-label="{{ $color['name'] }}" x-on:click="changeColor(@js($color['id'])); $dispatch('catalog-color-changed', {id: colorId})"><img src="{{ $color['image_url'] ?? $fallbackImage }}" alt="{{ $color['name'] }}" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-cover"></button>
        @endforeach
    </div>
    <div>
        <label class="block text-lg font-semibold" for="variant-color">Màu</label>
        <select id="variant-color" class="input w-full" x-bind:value="colorId" x-on:change="changeColor($event.target.value); $dispatch('catalog-color-changed', {id: colorId})">
            @foreach ($colors as $color)
                <option value="{{ $color['id'] }}" @selected((string) $initialColorId === (string) $color['id'])>{{ $color['name'] }}</option>
            @endforeach
        </select>
    </div>
    <fieldset>
        <legend class="text-lg font-semibold">Size</legend>
        <div class="flex flex-wrap gap-2">
            <template x-for="variant in variants.filter(v => String(v.product_color_id) === String(colorId) && v.is_active === true)" :key="variant.id">
                <button type="button" class="min-w-[3rem] border px-3 py-2 focus-visible:ring-4 disabled:cursor-not-allowed disabled:line-through disabled:opacity-30" x-bind:disabled="variant.available_qty <= 0" x-bind:aria-label="'Size ' + variant.size_value + (variant.available_qty <= 0 ? ' — Hết hàng' : '')" x-bind:class="variantId === variant.id ? 'bg-black text-white' : ''" x-bind:aria-pressed="(variantId === variant.id).toString()" x-text="variant.size_value" x-on:click="choose(variant.id)"></button>
            </template>
        </div>
        <p x-show="available.length === 0" class="mt-2 text-sm text-zinc-500">Màu này hiện đã hết hàng.</p>
    </fieldset>
    <p class="text-2xl" x-show="priceAvailable" aria-live="polite"><span x-text="selected?.price_label"></span> <span class="text-base text-zinc-500 line-through" x-show="selected?.base_price_label" x-text="selected?.base_price_label"></span></p>
    <p class="text-sm text-zinc-500" x-show="!selected">Chọn size còn hàng để thêm vào giỏ.</p>
    <div class="flex flex-wrap gap-3">
        <button type="button" disabled data-add-to-cart x-bind:data-variant-id="selected?.id ?? ''" x-bind:disabled="!selected || !priceAvailable || $store.cart.isBusy()" class="btn disabled:cursor-not-allowed disabled:opacity-50">Thêm vào giỏ</button>
        @if (Route::has('checkout.show'))
            <button type="button" disabled data-add-to-cart data-buy-now="{{ parse_url(route('checkout.show'), PHP_URL_PATH) }}" x-bind:data-variant-id="selected?.id ?? ''" x-bind:disabled="!selected || !priceAvailable || $store.cart.isBusy()" class="rounded-none border-2 border-black px-5 py-3 font-semibold transition-all hover:scale-105 disabled:cursor-not-allowed disabled:opacity-50">Mua ngay</button>
        @endif
    </div>
</section>
