@props(['variants' => [], 'colors' => [], 'initialColorId' => null, 'action' => null])
@php($fallbackImage = asset('legacy/img/men.jpg'))
<section x-data="variantPicker({variants: @js($variants), initialColorId: @js($initialColorId)})" class="space-y-6" aria-label="Chọn màu và size">
    <div>
        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-muted">Phối màu</label>
        <div class="flex flex-wrap gap-2.5" role="group" aria-label="Phối màu">
            @foreach ($colors as $color)
                <button type="button" class="h-16 w-16 overflow-hidden rounded-xl border-2 transition-colors focus-visible:ring-2 shadow-subtle" x-bind:class="String(colorId) === @js((string) $color['id']) ? 'border-accent ring-2 ring-accent/30' : 'border-border/80 hover:border-accent/50'" x-bind:aria-pressed="(String(colorId) === @js((string) $color['id'])).toString()" aria-label="{{ $color['name'] }}" x-on:click="changeColor(@js($color['id'])); $dispatch('catalog-color-changed', {id: colorId})">
                    <img src="{{ $color['image_url'] ?? $fallbackImage }}" alt="{{ $color['name'] }}" loading="lazy" decoding="async" width="64" height="64" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-cover aspect-square">
                </button>
            @endforeach
        </div>
    </div>
    <div class="hidden">
        <label class="block text-sm font-semibold" for="variant-color">Màu</label>
        <select id="variant-color" class="input w-full" x-bind:value="colorId" x-on:change="changeColor($event.target.value); $dispatch('catalog-color-changed', {id: colorId})">
            @foreach ($colors as $color)
                <option value="{{ $color['id'] }}" @selected((string) $initialColorId === (string) $color['id'])>{{ $color['name'] }}</option>
            @endforeach
        </select>
    </div>
    <fieldset>
        <legend class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">Kích cỡ (Size EU)</legend>
        <div class="flex flex-wrap gap-2">
            <template x-for="variant in variants.filter(v => String(v.product_color_id) === String(colorId) && v.is_active === true)" :key="variant.id">
                <button type="button" class="min-w-[3.25rem] rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition-colors focus-visible:ring-2 disabled:cursor-not-allowed disabled:line-through disabled:opacity-30 disabled:border-border/40" x-bind:disabled="variant.available_qty <= 0" x-bind:aria-label="'Size ' + variant.size_value + (variant.available_qty <= 0 ? ' — Hết hàng' : '')" x-bind:class="variantId === variant.id ? 'bg-accent text-accent-text border-accent shadow-sm' : 'bg-surface-card border-border text-main hover:border-accent'" x-bind:aria-pressed="(variantId === variant.id).toString()" x-text="variant.size_value" x-on:click="choose(variant.id)"></button>
            </template>
        </div>
        <p x-show="available.length === 0" class="mt-2 text-xs text-error font-medium">Màu này hiện đã hết hàng.</p>
    </fieldset>
    <div class="rounded-xl border border-border/80 bg-surface/50 p-4">
        <p class="text-2xl font-bold text-accent" x-show="priceAvailable" aria-live="polite"><span x-text="selected?.price_label"></span> <span class="text-sm font-normal text-muted line-through ml-2" x-show="selected?.base_price_label" x-text="selected?.base_price_label"></span></p>
        <p class="text-xs text-muted" x-show="!selected">Vui lòng chọn size còn hàng để tiếp tục.</p>
    </div>
    @if (auth()->user()?->hasRole(App\Enums\RoleSlug::Admin))
        <p class="rounded-xl border border-warning/30 bg-warning-bg p-3.5 text-xs font-medium text-warning-text">Tài khoản quản trị không dùng để mua sắm. Đăng nhập bằng tài khoản khách hàng để đặt hàng.</p>
    @else
    <div class="flex flex-wrap gap-3 pt-2">
        <button type="button" disabled data-add-to-cart x-bind:data-variant-id="selected?.id ?? ''" x-bind:disabled="!selected || !priceAvailable || $store.cart.isBusy()" class="btn flex-1 sm:flex-initial px-8 py-3.5 disabled:cursor-not-allowed disabled:opacity-50">
            <span x-show="!$store.cart.isBusy('add-' + (selected?.id ?? ''))">Thêm vào giỏ</span>
            <span x-cloak x-show="$store.cart.isBusy('add-' + (selected?.id ?? ''))" class="inline-flex items-center gap-2">
                <x-ui.spinner :compact="true" />
                <span>Đang thêm...</span>
            </span>
        </button>
        @if (Route::has('checkout.show'))
            <button type="button" disabled data-add-to-cart data-buy-now="{{ parse_url(route('checkout.show'), PHP_URL_PATH) }}" x-bind:data-variant-id="selected?.id ?? ''" x-bind:disabled="!selected || !priceAvailable || $store.cart.isBusy()" class="btn-secondary flex-1 sm:flex-initial px-8 py-3.5 font-semibold transition-colors hover:border-accent hover:text-accent disabled:cursor-not-allowed disabled:opacity-50">Mua ngay</button>
        @endif
    </div>
    @endif
</section>
