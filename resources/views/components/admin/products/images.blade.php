@props(['images'=>[], 'dynamic'=>false])
<section class="space-y-3" x-data="productImages({ initial: {{ $dynamic ? '(color.images || []).map(i=>({...i,thumbnail:i.thumbnail ?? !!Number(i.is_thumbnail)}))' : Illuminate\Support\Js::from($images) }} })" aria-label="Ảnh sản phẩm">
<h3 class="font-semibold">Ảnh phối màu</h3>
<template x-for="(image,ii) in images" :key="image.key">
<div class="space-y-2 border border-zinc-200 p-3">
<input type="hidden" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][id]'" : "'images['+ii+'][id]'" }}" x-bind:value="image.id || ''">
<label class="block">Tệp JPEG/PNG/WebP (tối đa5MB) <input class="block w-full min-w-0 max-w-full text-sm" type="file" accept="image/jpeg,image/png,image/webp" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][file]'" : "'images['+ii+'][file]'" }}" x-on:change="setFile(image.key,$event.target.files[0])"></label>
<label class="block">Hoặc URL HTTPS <input class="input w-full" type="url" x-model="image.external_url" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][external_url]'" : "'images['+ii+'][external_url]'" }}"></label>
<label class="block">Mô tả ảnh <input class="input w-full" x-model="image.alt_text" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][alt_text]'" : "'images['+ii+'][alt_text]'" }}" required></label>
<input type="hidden" x-bind:name="{{ $dynamic ? "'colors['+ci+'][images]['+ii+'][is_thumbnail]'" : "'images['+ii+'][is_thumbnail]'" }}" x-bind:value="image.thumbnail ? 1:0">
<label><input type="radio" x-bind:name="{{ $dynamic ? "'thumbnail-'+ci" : "'thumbnail'" }}" x-bind:checked="image.thumbnail" x-on:change="makeThumbnail(image.key)"> Ảnh đại diện</label>
<img x-show="image.previewUrl || image.external_url || image.existingUrl" x-bind:src="image.previewUrl || image.external_url || image.existingUrl" alt="Ảnh xem trước được chọn" class="h-24 w-24 object-contain">
<div class="flex flex-wrap gap-3"><button type="button" class="underline" x-on:click="move(ii,ii-1)">Lên</button><button type="button" class="underline" x-on:click="move(ii,ii+1)">Xuống</button><button type="button" class="underline" x-on:click="remove(image.key)">Xóa ảnh nháp</button></div>
</div>
</template>
<button type="button" class="underline" x-on:click="add()">Thêm ảnh</button>
<p class="text-xs">Chọn đúng một ảnh đại diện cho mỗi màu. Ảnh chỉ được lưu khi lưu toàn bộ sản phẩm.</p>
</section>
