@props([
    'variants' => [],
    'colors' => [],
    'initialColorId' => null,
    'fallbackPriceLabel' => null,
    'action' => null,
])
@php
    $fallbackImage = asset('legacy/img/men.jpg');
    $maxPerVariant = (int) config('store.limits.variant_quantity', 10);
@endphp
<section x-data="variantPicker({
        variants: @js($variants),
        colors: @js($colors),
        initialColorId: @js($initialColorId),
        maxPerVariant: @js($maxPerVariant),
    })"
    class="space-y-6"
    aria-label="Chọn màu, size và đặt mua">

    {{-- Unified Price & Stock Summary --}}
    <div class="rounded-2xl border border-border bg-surface-card p-4 sm:p-5" aria-live="polite">
        <div x-show="priceAvailable" class="space-y-1.5">
            <div class="flex flex-wrap items-baseline gap-2.5">
                <p class="text-2xl font-extrabold tracking-tight text-main sm:text-3xl" x-text="selected?.price_label"></p>
                <span x-show="selected?.base_price_label"
                    x-text="selected?.base_price_label"
                    class="text-sm font-normal text-muted line-through"></span>
                <span x-cloak
                    x-show="selected?.discount_percent > 0"
                    x-text="'-' + selected?.discount_percent + '%'"
                    class="badge-ruby text-[11px]"></span>
            </div>
            <p class="text-xs font-medium text-muted">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-success" aria-hidden="true"></span>
                    <span x-text="selected?.available_qty <= 5 ? 'Chỉ còn ' + selected?.available_qty + ' đôi sẵn sàng giao' : 'Còn hàng · Giao hàng tiêu chuẩn'"></span>
                </span>
            </p>
        </div>

        <div x-show="selected && !priceAvailable" x-cloak class="space-y-1">
            <p class="text-lg font-bold text-main">Giá chưa khả dụng</p>
            <p class="text-xs text-muted">Biến thể này chưa được niêm yết giá bán.</p>
        </div>

        <div x-show="!selected" class="space-y-1.5">
            <template x-if="colorSummary.hasStock">
                <div>
                    <div class="flex flex-wrap items-baseline gap-2.5">
                        <p class="text-2xl font-extrabold tracking-tight text-main sm:text-3xl">
                            <span x-show="colorSummary.varies" x-text="'Từ ' + colorSummary.minPriceLabel + ' – ' + colorSummary.maxPriceLabel"></span>
                            <span x-show="!colorSummary.varies" x-text="colorSummary.minPriceLabel"></span>
                        </p>
                        <span x-show="colorSummary.singleBasePriceLabel"
                            x-text="colorSummary.singleBasePriceLabel"
                            class="text-sm font-normal text-muted line-through"></span>
                        <span x-show="colorSummary.singleDiscountPercent > 0"
                            x-text="'-' + colorSummary.singleDiscountPercent + '%'"
                            class="badge-ruby text-[11px]"></span>
                    </div>
                    <p class="mt-1 text-xs text-muted">Chọn size còn hàng bên dưới để xem giá chính xác và đặt mua.</p>
                </div>
            </template>
            <template x-if="!colorSummary.hasStock">
                <div>
                    <p class="text-xl font-bold text-main">
                        {{ $fallbackPriceLabel ? 'Tham khảo từ '.$fallbackPriceLabel : 'Tạm hết hàng' }}
                    </p>
                    <p class="mt-1 text-xs font-medium text-error">Phối màu này hiện đã hết hàng. Vui lòng chọn màu khác nếu còn hàng.</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Color Swatches --}}
    <div>
        <div class="mb-2.5 flex items-baseline justify-between gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-muted">Phối màu</span>
            <span class="text-xs font-semibold text-main" x-text="currentColor?.name ?? ''"></span>
        </div>
        <div class="flex flex-wrap gap-2.5" role="group" aria-label="Phối màu">
            @foreach ($colors as $color)
                @php($colorAvailable = (bool) ($color['has_available'] ?? true))
                <button type="button"
                    class="relative h-16 w-16 overflow-hidden rounded-xl border-2 bg-surface-card transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent shadow-subtle {{ $colorAvailable ? '' : 'opacity-70' }}"
                    x-bind:class="String(colorId) === @js((string) $color['id']) ? 'border-accent ring-2 ring-accent/30 opacity-100' : 'border-border/80 hover:border-accent/60'"
                    x-bind:aria-pressed="(String(colorId) === @js((string) $color['id'])).toString()"
                    aria-label="{{ $color['name'] }}{{ $colorAvailable ? '' : ' — Hết hàng' }}"
                    x-on:click="changeColor(@js($color['id'])); $dispatch('catalog-color-changed', {id: colorId})">
                    <img src="{{ $color['image_url'] ?? $fallbackImage }}"
                        alt="{{ $color['name'] }}"
                        loading="lazy"
                        decoding="async"
                        width="64"
                        height="64"
                        x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))"
                        class="h-full w-full object-cover aspect-square">
                    @unless ($colorAvailable)
                        <span class="absolute inset-x-0 bottom-0 bg-main/80 py-0.5 text-center text-[9px] font-bold uppercase tracking-wider text-white">Hết</span>
                    @endunless
                </button>
            @endforeach
        </div>
    </div>

    {{-- Accessible select mirror (screen-readers / fallback) --}}
    <div class="hidden">
        <label for="variant-color">Màu</label>
        <select id="variant-color" x-bind:value="colorId" x-on:change="changeColor($event.target.value); $dispatch('catalog-color-changed', {id: colorId})">
            @foreach ($colors as $color)
                <option value="{{ $color['id'] }}" @selected((string) $initialColorId === (string) $color['id'])>{{ $color['name'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- Size Matrix (distinguishes selected, available, out of stock, and unavailable combination) --}}
    <fieldset x-ref="sizeSection">
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
            <legend class="text-xs font-bold uppercase tracking-wider text-muted">Kích cỡ (Size EU)</legend>
            <x-ui.dialog id="shoe-size-guide" title="Hướng dẫn chọn size">
                <x-slot:trigger>
                    <span class="cursor-pointer text-xs font-semibold text-main underline decoration-border underline-offset-4 transition-colors hover:opacity-80">Hướng dẫn chọn size</span>
                </x-slot:trigger>
                <div class="space-y-3 text-sm leading-relaxed text-muted">
                    <p>Chọn đúng size EU bạn thường mang đối với giày thể thao. Các size có đuôi <strong class="text-main">.5</strong> là nửa size để tinh chỉnh độ ôm bàn chân.</p>
                    <p>Trên trang sản phẩm, những size gạch ngang là đã hết hàng ở phối màu đang xem; size viền nét đứt là tổ hợp không áp dụng cho phối màu đó.</p>
                </div>
            </x-ui.dialog>
        </div>

        <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
            <template x-for="item in sizeMatrix" :key="item.size_value">
                <button type="button"
                    x-bind:disabled="item.status !== 'available'"
                    x-bind:aria-label="'Size ' + item.size_value + (item.status === 'out_of_stock' ? ' — Hết hàng' : (item.status === 'unavailable' ? ' — Không có ở màu này' : ''))"
                    x-bind:title="item.status === 'out_of_stock' ? 'Hết hàng' : (item.status === 'unavailable' ? 'Không có ở màu này' : 'Chọn size ' + item.size_value)"
                    x-bind:aria-pressed="(item.status === 'available' && variantId === item.id).toString()"
                    x-bind:class="item.status !== 'available'
                        ? (item.status === 'out_of_stock'
                            ? 'cursor-not-allowed border-border/50 bg-surface text-muted/45 line-through'
                            : 'cursor-not-allowed border-dashed border-border/50 bg-transparent text-muted/35')
                        : (variantId === item.id
                            ? 'border-accent bg-accent text-accent-text shadow-sm'
                            : 'border-border bg-surface-card text-main hover:border-accent')"
                    class="min-h-[2.75rem] rounded-xl border px-2 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    x-text="item.size_value"
                    x-on:click="if (item.status === 'available') choose(item.id)"></button>
            </template>
        </div>

        <div x-show="sizeMatrix.some(i => i.status !== 'available')" class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted">
            <span x-show="sizeMatrix.some(i => i.status === 'out_of_stock')" class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-4 rounded border border-border/60 bg-surface line-through" aria-hidden="true"></span>
                <span>Hết hàng</span>
            </span>
            <span x-show="sizeMatrix.some(i => i.status === 'unavailable')" class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-4 rounded border border-dashed border-border/60" aria-hidden="true"></span>
                <span>Không có ở màu này</span>
            </span>
        </div>

        <p x-show="colorSummary.hasStock && !selected" class="mt-2.5 text-xs font-medium text-muted">
            Vui lòng chọn size còn hàng trước khi thêm vào giỏ.
        </p>
        <p x-show="selected" x-cloak class="mt-2.5 text-xs font-medium text-main">
            Đã chọn: <span class="font-bold" x-text="(currentColor?.name ?? '') + ' · Size ' + (selected?.size_value ?? '')"></span>
        </p>
    </fieldset>

    {{-- Purchase Section (Quantity Stepper + Add to Cart + Buy Now + Admin Guard) --}}
    @if (auth()->user()?->hasRole(App\Enums\RoleSlug::Admin))
        <p class="rounded-xl border border-warning/30 bg-warning-bg p-3.5 text-xs font-medium text-warning-text">
            Tài khoản quản trị không dùng để mua sắm. Đăng nhập bằng tài khoản khách hàng để đặt hàng.
        </p>
    @else
        <div class="space-y-3 border-t border-border pt-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Số lượng</span>
                <div class="flex items-center gap-3">
                    <div class="inline-flex items-center rounded-xl border border-border bg-surface-card shadow-subtle">
                        <button type="button"
                            x-on:click="stepQuantity(-1)"
                            x-bind:disabled="!selected || quantity <= 1 || $store.cart.isBusy()"
                            aria-label="Giảm số lượng"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-l-xl text-base font-semibold text-main transition hover:bg-surface disabled:cursor-not-allowed disabled:opacity-35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            −
                        </button>
                        <input type="number"
                            inputmode="numeric"
                            min="1"
                            x-bind:max="maxQty"
                            x-model.number="quantity"
                            x-on:change="setQuantity($event.target.value)"
                            x-bind:disabled="!selected || $store.cart.isBusy()"
                            aria-label="Số lượng sản phẩm"
                            class="h-10 w-12 border-x border-border bg-transparent text-center text-sm font-bold text-main focus:outline-none disabled:opacity-50">
                        <button type="button"
                            x-on:click="stepQuantity(1)"
                            x-bind:disabled="!selected || quantity >= maxQty || $store.cart.isBusy()"
                            aria-label="Tăng số lượng"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-r-xl text-base font-semibold text-main transition hover:bg-surface disabled:cursor-not-allowed disabled:opacity-35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            +
                        </button>
                    </div>
                    <span x-cloak x-show="selected" class="text-[11px] text-muted" x-text="'Tối đa ' + maxQty + ' đôi'"></span>
                </div>
            </div>

            <div class="flex flex-col gap-2.5 sm:flex-row sm:gap-3">
                <button type="button"
                    disabled
                    data-add-to-cart
                    x-bind:data-variant-id="selected?.id ?? ''"
                    x-bind:data-quantity="quantity"
                    x-bind:disabled="!selected || !priceAvailable || $store.cart.isBusy()"
                    class="btn flex-1 py-3.5 text-sm disabled:cursor-not-allowed disabled:opacity-45">
                    <span x-show="!$store.cart.isBusy('add-' + (selected?.id ?? ''))">Thêm vào giỏ hàng</span>
                    <span x-cloak x-show="$store.cart.isBusy('add-' + (selected?.id ?? ''))" class="inline-flex items-center gap-2">
                        <x-ui.spinner :compact="true" />
                        <span>Đang thêm...</span>
                    </span>
                </button>
                @if (Route::has('checkout.show'))
                    <button type="button"
                        disabled
                        data-add-to-cart
                        data-buy-now="{{ parse_url(route('checkout.show'), PHP_URL_PATH) }}"
                        x-bind:data-variant-id="selected?.id ?? ''"
                        x-bind:data-quantity="quantity"
                        x-bind:disabled="!selected || !priceAvailable || $store.cart.isBusy()"
                        class="btn-secondary flex-1 py-3.5 text-sm font-semibold hover:border-accent disabled:cursor-not-allowed disabled:opacity-45">
                        Mua ngay
                    </button>
                @endif
            </div>

            <p x-cloak
                x-show="$store.cart.error"
                role="alert"
                x-text="$store.cart.error"
                class="rounded-xl border border-error bg-error-bg px-3.5 py-2.5 text-xs font-medium text-error-text"></p>
            <p x-cloak
                x-show="$store.cart.message && !$store.cart.error"
                role="status"
                x-text="$store.cart.message"
                class="rounded-xl border border-success bg-success-bg px-3.5 py-2.5 text-xs font-medium text-success-text"></p>
        </div>
    @endif
</section>
