@props(['images'=>[], 'dynamic'=>false])
<section class="space-y-3" x-data="productImages({ initial: {{ $dynamic ? '(color.images || []).map(i=>({...i,thumbnail:i.thumbnail ?? !!Number(i.is_thumbnail)}))' : Illuminate\Support\Js::from($images) }} })" aria-label="Ảnh sản phẩm">
<h3 class="text-sm font-semibold text-main">Ảnh phối màu</h3>
<template x-for="(image,ii) in images" :key="image.key">
<div class="space-y-2 rounded-xl border border-border p-3 text-sm bg-surface-alt/40">
<input type="hidden" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][id]'" : "'images['+ii+'][id]'" }}" x-bind:value="image.id || ''">
<label class="block text-xs font-semibold text-muted">Tệp JPEG/PNG/WebP (tối đa 5MB) <input class="block w-full min-w-0 max-w-full text-sm text-main file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-surface file:text-main file:border file:border-border hover:file:bg-surface-alt cursor-pointer mt-1" type="file" accept="image/jpeg,image/png,image/webp" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][file]'" : "'images['+ii+'][file]'" }}" x-on:change="setFile(image.key,$event.target.files[0])"></label>
<label class="block text-xs font-semibold text-muted">Hoặc URL HTTPS <input class="input mt-1 w-full border-border text-sm font-normal text-main" type="url" x-model="image.external_url" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][external_url]'" : "'images['+ii+'][external_url]'" }}"></label>
<label class="block text-xs font-semibold text-muted">Mô tả ảnh <input class="input mt-1 w-full border-border text-sm font-normal text-main" x-model="image.alt_text" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][alt_text]'" : "'images['+ii+'][alt_text]'" }}" required></label>
<input type="hidden" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][is_thumbnail]'" : "'images['+ii+'][is_thumbnail]'" }}" x-bind:value="image.thumbnail ? 1:0">
<label class="inline-flex items-center gap-2 text-main text-xs font-medium cursor-pointer"><input type="radio" class="accent-accent" x-bind:name="{{ $dynamic ? "'thumbnail-'+ci" : "'thumbnail'" }}" x-bind:checked="image.thumbnail" x-on:change="makeThumbnail(image.key)"> Ảnh đại diện</label>
<img x-show="image.previewUrl || image.external_url || image.existingUrl" x-bind:src="image.previewUrl || image.external_url || image.existingUrl" alt="Ảnh xem trước được chọn" class="h-24 w-24 rounded-lg object-cover border border-border">
<div class="flex flex-wrap gap-2 pt-1"><button type="button" class="btn-pill" x-on:click="move(ii,ii-1)">Lên</button><button type="button" class="btn-pill" x-on:click="move(ii,ii+1)">Xuống</button><button type="button" class="btn-pill text-error hover:bg-error-bg" x-on:click="remove(image.key)">Xóa ảnh nháp</button></div>
</div>
</template>
<button type="button" class="btn-pill" x-on:click="add()">+ Thêm ảnh</button>
<p class="text-xs text-muted">Chọn đúng một ảnh đại diện cho mỗi màu. Ảnh chỉ được lưu khi lưu toàn bộ sản phẩm.</p>
</section>
