@props(['colors' => []])
@php($colors = array_map(fn($c)=>array_merge($c,['name'=>$c['color_name']??$c['name']??'', 'hex'=>$c['primary_hex']??$c['hex']??'#000000']),is_array($colors)?$colors:[]))
<section class="admin-card space-y-4" x-data="colorVariants({ colors: @js($colors) })" aria-label="Phối màu và size">
<div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-main">Phối màu và size</h2><button type="button" class="btn-pill-dark" x-on:click="addColor({is_active:true,color_key:'black',hex:'#000000',images:[]})">+ Thêm phối màu</button></div>
<template x-for="(color,ci) in colors.filter(c=>!c.removed)" :key="color.uid">
<fieldset class="min-w-0 space-y-4 rounded-xl border border-border p-4">
<legend class="px-2 font-semibold text-main" x-text="color.name || 'Phối màu mới'"></legend>
<input type="hidden" x-bind:name="'colors['+ci+'][id]'" x-bind:value="color.id || ''">
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-semibold text-muted">Tên màu <span class="text-error">*</span><input class="input mt-1 w-full border-border text-sm font-normal text-main" x-model="color.name" x-bind:name="'colors['+ci+'][color_name]'" required></label>
<label class="text-xs font-semibold text-muted">Mã phối màu <span class="text-error">*</span><input class="input mt-1 w-full border-border text-sm font-normal text-main" x-model="color.style_color_code" x-bind:name="'colors['+ci+'][style_color_code]'" required></label>
<label class="text-xs font-semibold text-muted">Nhóm màu<select class="input mt-1 w-full border-border text-sm font-normal text-main" x-model="color.color_key" x-bind:name="'colors['+ci+'][color_key]'">@foreach(config('store.palette') as $key=>$option)<option value="{{ $key }}">{{ $option['label'] }}</option>@endforeach</select></label>
<label class="text-xs font-semibold text-muted">HEX <span class="text-error">*</span><input class="input mt-1 w-full border-border text-sm font-normal text-main" x-model="color.hex" x-bind:name="'colors['+ci+'][primary_hex]'" pattern="#[A-Fa-f0-9]{6}" required></label>
</div>
<input type="hidden" x-bind:name="'colors['+ci+'][is_active]'" x-bind:value="Number(color.is_active ?? 1) ? 1:0">
<label class="flex items-center gap-2 text-sm font-semibold text-main"><input type="checkbox" class="native-checkbox accent-accent" x-model="color.is_active"> Đang bán màu này</label>
<div class="space-y-2">
<template x-for="(variant,vi) in color.variants.filter(v=>!v.removed)" :key="variant.uid">
<div class="flex flex-wrap items-end gap-3 rounded-lg bg-surface-alt/70 p-3 border border-border/50">
<input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][id]'" x-bind:value="variant.id || ''">
<label class="text-xs font-semibold text-muted">Size<input class="input mt-1 block w-24 border-border text-sm font-normal text-main" x-model="variant.size_value" x-bind:name="'colors['+ci+'][variants]['+vi+'][size_value]'" required></label>
<label class="text-xs font-semibold text-muted">SKU<input class="input mt-1 block w-44 border-border text-sm font-normal text-main" x-model="variant.sku" x-bind:name="'colors['+ci+'][variants]['+vi+'][sku]'" required></label>
<label class="text-xs font-semibold text-muted">Giá riêng (₫)<input class="input mt-1 block w-36 border-border text-sm font-normal text-main" inputmode="numeric" x-model="variant.price_override" x-bind:name="'colors['+ci+'][variants]['+vi+'][price_override]'"></label>
<input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][is_active]'" x-bind:value="Number(variant.is_active ?? 1) ? 1:0">
<label class="flex items-center gap-2 pb-2 text-sm text-main"><input type="checkbox" class="native-checkbox accent-accent" x-model="variant.is_active"> Bán</label>
<button type="button" class="pb-2 text-sm font-semibold text-error hover:underline" x-on:click="removeVariant(color.uid,variant.uid)">Bỏ size</button>
</div></template>
<button type="button" class="btn-pill" x-on:click="addVariant(color.uid,{size_value:'',sku:'',is_active:true})">Thêm size riêng</button>
</div>
<details class="rounded-xl border border-border p-3"><summary class="cursor-pointer whitespace-normal text-sm font-semibold text-main">Sinh nhanh dải size (chỉ dùng khi chưa nhập SKU riêng)</summary>
<div class="grid gap-2 py-3 sm:grid-cols-3">
<label class="text-xs font-semibold text-muted">Từ size<input class="input mt-1 w-full border-border text-sm font-normal text-main" x-bind:name="'colors['+ci+'][range_start]'"></label>
<label class="text-xs font-semibold text-muted">Đến size<input class="input mt-1 w-full border-border text-sm font-normal text-main" x-bind:name="'colors['+ci+'][range_end]'"></label>
<label class="text-xs font-semibold text-muted">Tiền tố SKU<input class="input mt-1 w-full border-border text-sm font-normal text-main" x-bind:name="'colors['+ci+'][sku_prefix]'"></label>
</div><p class="text-xs text-muted">Server sinh từng nửa size bằng ShoeSizeSequence. Ví dụ40→41 tạo40,40.5,41.</p>
</details>
<x-admin.products.images dynamic />
<button type="button" class="text-sm font-semibold text-error hover:underline" x-on:click="removeColor(color.uid)">Bỏ phối màu</button>
</fieldset>
</template>
<p class="text-sm text-muted">Không nhập tồn kho hoặc giá vốn ở đây. Bỏ màu/size sẽ ngừng bán, không xóa lịch sử.</p>
</section>
