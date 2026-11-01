@props(['colors' => []])
<section class="space-y-4" x-data="colorVariants({ colors: @js($colors) })" aria-label="Phối màu và size">
    <h2 class="text-lg font-semibold">Phối màu và size</h2>
    <template x-for="color in colors.filter(c => !c.removed)" :key="color.uid">
        <fieldset class="space-y-3 border border-zinc-200 p-4">
            <legend class="text-sm font-semibold" x-text="color.name || 'Phối màu'"></legend>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm">Tên màu <input class="input block w-full" x-model="color.name" type="text"></label>
                <label class="text-sm">Mã màu <input class="input block w-full" x-model="color.style_color_code" type="text"></label>
                <label class="text-sm">HEX <input class="input block w-full" x-model="color.hex" type="text"></label>
            </div>
            <template x-for="variant in color.variants.filter(v => !v.removed)" :key="variant.uid">
                <div class="flex flex-wrap items-center gap-2">
                    <label class="text-sm">Size <input class="input w-24" x-model="variant.size_value" type="text"></label>
                    <label class="text-sm">Giá riêng (VND) <input class="input w-32" x-model="variant.price_override" inputmode="numeric" type="text"></label>
                    <button type="button" class="underline" x-on:click="removeVariant(color.uid, variant.uid)">Bỏ size</button>
                </div>
            </template>
            <button type="button" class="underline" x-on:click="addVariant(color.uid, {size_value: ''})">Thêm size</button>
            <button type="button" class="underline" x-on:click="removeColor(color.uid)">Bỏ phối màu</button>
        </fieldset>
    </template>
    <button type="button" class="btn" x-on:click="addColor()">Thêm phối màu</button>
    <p class="text-xs text-zinc-500">Các thay đổi là bản nháp; chỉ được lưu khi form/backend hợp lệ do người quản trị tích hợp.</p>
</section>
