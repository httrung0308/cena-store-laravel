@props(['variants' => [], 'colors' => [], 'initialColorId' => null, 'action' => null])
<section x-data="variantPicker({variants: @js($variants), initialColorId: @js($initialColorId)})" class="space-y-5" aria-label="Chọn màu và size">
    <div><label class="block text-lg font-semibold" for="variant-color">Màu</label><select id="variant-color" class="input w-full" x-on:change="changeColor($event.target.value)"><option value="">Chọn màu</option>
        @foreach ($colors as $color)<option value="{{ $color['id'] }}" @selected((string) $initialColorId === (string) $color['id'])>{{ $color['name'] }}</option>@endforeach
    </select></div>
    <fieldset><legend class="text-lg font-semibold">Size</legend><div class="flex flex-wrap gap-2">
        <template x-for="variant in available" :key="variant.id"><button type="button" class="border px-3 py-2 focus-visible:ring-4" x-bind:aria-pressed="(variantId===variant.id).toString()" x-text="variant.size_value" x-on:click="choose(variant.id)"></button></template>
        <p x-show="available.length === 0" class="text-sm text-zinc-500">Size chưa khả dụng</p>
    </div></fieldset>
    <p x-show="selected && !priceAvailable" class="text-sm text-zinc-500">Giá chưa khả dụng</p>
    <p class="text-sm" x-show="priceAvailable" x-text="selected?.effective_price?.toLocaleString('vi-VN') + ' ₫'"></p>
    <button type="button" disabled class="btn disabled:opacity-50">Thêm giỏ hàng (chưa kết nối)</button>
</section>
