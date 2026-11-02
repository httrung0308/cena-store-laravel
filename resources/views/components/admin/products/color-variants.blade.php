@props(['colors' => []])
@php($colors = array_map(fn($c)=>array_merge($c,['name'=>$c['color_name']??$c['name']??'', 'hex'=>$c['primary_hex']??$c['hex']??'#000000']),is_array($colors)?$colors:[]))
<section class="admin-card space-y-4" x-data="colorVariants({ colors: @js($colors) })" aria-label="Phối màu và size">
<div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">Phối màu và size</h2><button type="button" class="btn-pill-dark" x-on:click="addColor({is_active:true,color_key:'black',hex:'#000000',images:[]})">+ Thêm phối màu</button></div>
<template x-for="(color,ci) in colors.filter(c=>!c.removed)" :key="color.uid">
<fieldset class="min-w-0 space-y-4 rounded-xl border border-zinc-200 p-4">
<legend class="px-2 font-semibold text-zinc-700" x-text="color.name || 'Phối màu mới'"></legend>
<input type="hidden" x-bind:name="'colors['+ci+'][id]'" x-bind:value="color.id || ''">
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-semibold text-zinc-600">Tên màu <span class="text-red-500">*</span><input class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-model="color.name" x-bind:name="'colors['+ci+'][color_name]'" required></label>
<label class="text-xs font-semibold text-zinc-600">Mã phối màu <span class="text-red-500">*</span><input class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-model="color.style_color_code" x-bind:name="'colors['+ci+'][style_color_code]'" required></label>
<label class="text-xs font-semibold text-zinc-600">Nhóm màu<select class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-model="color.color_key" x-bind:name="'colors['+ci+'][color_key]'">@foreach(config('store.palette') as $key=>$option)<option value="{{ $key }}">{{ $option['label'] }}</option>@endforeach</select></label>
<label class="text-xs font-semibold text-zinc-600">HEX <span class="text-red-500">*</span><input class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-model="color.hex" x-bind:name="'colors['+ci+'][primary_hex]'" pattern="#[A-Fa-f0-9]{6}" required></label>
</div>
<input type="hidden" x-bind:name="'colors['+ci+'][is_active]'" x-bind:value="Number(color.is_active ?? 1) ? 1:0">
<label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" class="native-checkbox accent-black" x-model="color.is_active"> Đang bán màu này</label>
<div class="space-y-2">
<template x-for="(variant,vi) in color.variants.filter(v=>!v.removed)" :key="variant.uid">
<div class="flex flex-wrap items-end gap-3 rounded-lg bg-zinc-50 p-3">
<input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][id]'" x-bind:value="variant.id || ''">
<label class="text-xs font-semibold text-zinc-600">Size<input class="input mt-1 block w-24 border-zinc-300 text-sm font-normal" x-model="variant.size_value" x-bind:name="'colors['+ci+'][variants]['+vi+'][size_value]'" required></label>
<label class="text-xs font-semibold text-zinc-600">SKU<input class="input mt-1 block w-44 border-zinc-300 text-sm font-normal" x-model="variant.sku" x-bind:name="'colors['+ci+'][variants]['+vi+'][sku]'" required></label>
<label class="text-xs font-semibold text-zinc-600">Giá riêng (₫)<input class="input mt-1 block w-36 border-zinc-300 text-sm font-normal" inputmode="numeric" x-model="variant.price_override" x-bind:name="'colors['+ci+'][variants]['+vi+'][price_override]'"></label>
<input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][is_active]'" x-bind:value="Number(variant.is_active ?? 1) ? 1:0">
<label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" class="native-checkbox accent-black" x-model="variant.is_active"> Bán</label>
<button type="button" class="pb-2 text-sm font-semibold text-red-500 hover:text-red-700" x-on:click="removeVariant(color.uid,variant.uid)">Bỏ size</button>
</div></template>
<button type="button" class="btn-pill" x-on:click="addVariant(color.uid,{size_value:'',sku:'',is_active:true})">Thêm size riêng</button>
</div>
<details class="rounded-xl border border-zinc-200 p-3"><summary class="cursor-pointer whitespace-normal text-sm font-semibold">Sinh nhanh dải size (chỉ dùng khi chưa nhập SKU riêng)</summary>
<div class="grid gap-2 py-3 sm:grid-cols-3">
<label class="text-xs font-semibold text-zinc-600">Từ size<input class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-bind:name="'colors['+ci+'][range_start]'"></label>
<label class="text-xs font-semibold text-zinc-600">Đến size<input class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-bind:name="'colors['+ci+'][range_end]'"></label>
<label class="text-xs font-semibold text-zinc-600">Tiền tố SKU<input class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-bind:name="'colors['+ci+'][sku_prefix]'"></label>
</div><p class="text-xs text-zinc-500">Server sinh từng nửa size bằng ShoeSizeSequence. Ví dụ40→41 tạo40,40.5,41.</p>
</details>
<x-admin.products.images dynamic />
<button type="button" class="text-sm font-semibold text-red-500 hover:text-red-700" x-on:click="removeColor(color.uid)">Bỏ phối màu</button>
</fieldset>
</template>
<p class="text-sm text-zinc-600">Không nhập tồn kho hoặc giá vốn ở đây. Bỏ màu/size sẽ ngừng bán, không xóa lịch sử.</p>
</section>
