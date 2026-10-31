@props(['images' => []])
<section class="space-y-4" x-data="productImages({ initial: @js($images) })" aria-label="Ảnh sản phẩm">
    <h2 class="text-lg font-semibold">Ảnh phối màu</h2>
    <template x-for="image in images" :key="image.key">
        <div class="flex flex-col gap-3 border border-zinc-200 p-3 sm:flex-row sm:items-start">
            <div class="h-24 w-24 bg-zinc-100"><img x-show="image.previewUrl" x-bind:src="image.previewUrl" alt="Ảnh xem trước được chọn" class="h-full w-full object-contain"></div>
            <div class="flex-1 space-y-2">
                <label class="block text-sm">Tệp JPEG/PNG/WebP <input type="file" accept="image/jpeg,image/png,image/webp" x-on:change="setFile(image.key,$event.target.files[0])"></label>
                <label class="block text-sm">Hoặc URL HTTPS <input type="url" class="input w-full" x-bind:value="image.external_url || ''" x-on:change="setExternal(image.key,$event.target.value)"></label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="thumbnail_intent" x-bind:checked="image.thumbnail" x-on:change="makeThumbnail(image.key)"> Ảnh đại diện</label>
                <button type="button" class="underline" x-on:click="remove(image.key)">Xóa ảnh nháp</button>
            </div>
        </div>
    </template>
    <button type="button" class="btn" x-on:click="add()">Thêm ảnh</button>
    <p class="text-xs text-zinc-600">Đây chỉ là xem trước cục bộ. Chưa tải ảnh lên server.</p>
</section>
