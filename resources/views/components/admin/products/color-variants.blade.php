@props(['colors' => []])
@php($colors = array_map(fn($c)=>array_merge($c,['name'=>$c['color_name']??$c['name']??'', 'hex'=>$c['primary_hex']??$c['hex']??'#000000']),is_array($colors)?$colors:[]))
<section class="space-y-4" x-data="colorVariants({ colors: @js($colors) })" aria-label="Phối màu và size">
<h2 class="text-lg font-semibold">Phối màu và size</h2>
<template x-for="(color,ci) in colors.filter(c=>!c.removed)" :key="color.uid">
<fieldset class="min-w-0 space-y-4 border border-zinc-300 p-4">
<legend class="font-semibold" x-text="color.name || 'Phối màu mới'"></legend>
<input type="hidden" x-bind:name="'colors['+ci+'][id]'" x-bind:value="color.id || ''">
<div class="grid gap-3 sm:grid-cols-2">
<label>Tên màu <input class="input w-full" x-model="color.name" x-bind:name="'colors['+ci+'][color_name]'" required></label>
<label>Mã phối màu <input class="input w-full" x-model="color.style_color_code" x-bind:name="'colors['+ci+'][style_color_code]'" required></label>
<label>Nhóm màu <select class="input w-full" x-model="color.color_key" x-bind:name="'colors['+ci+'][color_key]'">@foreach(config('store.palette') as $key=>$option)<option value="{{ $key }}">{{ $option['label'] }}</option>@endforeach</select></label>
<label>HEX <input class="input w-full" x-model="color.hex" x-bind:name="'colors['+ci+'][primary_hex]'" pattern="#[A-Fa-f0-9]{6}" required></label>
</div>
<input type="hidden" x-bind:name="'colors['+ci+'][is_active]'" x-bind:value="Number(color.is_active ?? 1) ? 1:0">
<label><input type="checkbox" x-model="color.is_active"> Đang bán màu này</label>
<div class="space-y-2">
<template x-for="(variant,vi) in color.variants.filter(v=>!v.removed)" :key="variant.uid">
<div class="flex flex-wrap items-end gap-2 border-b py-2">
<input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][id]'" x-bind:value="variant.id || ''">
<label>Size <input class="input w-24" x-model="variant.size_value" x-bind:name="'colors['+ci+'][variants]['+vi+'][size_value]'" required></label>
<label>SKU <input class="input w-44" x-model="variant.sku" x-bind:name="'colors['+ci+'][variants]['+vi+'][sku]'" required></label>
<label>Giá riêng (VND) <input class="input w-36" inputmode="numeric" x-model="variant.price_override" x-bind:name="'colors['+ci+'][variants]['+vi+'][price_override]'"></label>
<input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][is_active]'" x-bind:value="Number(variant.is_active ?? 1) ? 1:0">
<label><input type="checkbox" x-model="variant.is_active"> Bán</label>
<button type="button" class="underline" x-on:click="removeVariant(color.uid,variant.uid)">Bỏ size</button>
</div></template>
<button type="button" class="underline" x-on:click="addVariant(color.uid,{size_value:'',sku:'',is_active:true})">Thêm size riêng</button>
</div>
<details class="border p-3"><summary class="whitespace-normal">Sinh nhanh dải size (chỉ dùng khi chưa nhập SKU riêng)</summary>
<div class="grid gap-2 py-3 sm:grid-cols-3">
<label>Từ size <input class="input w-full" x-bind:name="'colors['+ci+'][range_start]'"></label>
<label>Đến size <input class="input w-full" x-bind:name="'colors['+ci+'][range_end]'"></label>
<label>Tiền tố SKU <input class="input w-full" x-bind:name="'colors['+ci+'][sku_prefix]'"></label>
</div><p class="text-xs">Server sinh từng nửa size bằng ShoeSizeSequence. Ví dụ40→41 tạo40,40.5,41.</p>
</details>
<x-admin.products.images dynamic />
<button type="button" class="underline" x-on:click="removeColor(color.uid)">Bỏ phối màu</button>
</fieldset>
</template>
<button type="button" class="btn" x-on:click="addColor({is_active:true,color_key:'black',hex:'#000000',images:[]})">Thêm phối màu</button>
<p class="text-sm text-zinc-600">Không nhập tồn kho hoặc giá vốn ở đây. Bỏ màu/size sẽ ngừng bán, không xóa lịch sử.</p>
</section>
