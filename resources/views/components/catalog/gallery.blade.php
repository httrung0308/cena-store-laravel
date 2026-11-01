@props(['albums' => [], 'initialColor' => null])
<section x-data="galleryController({albums: @js($albums), initialColor: @js($initialColor)})" aria-label="Thư viện ảnh sản phẩm" class="space-y-3">
    <div class="aspect-square overflow-hidden bg-zinc-100">
        <template x-if="selected"><img x-bind:src="selected.url" x-bind:alt="selected.alt || 'Ảnh sản phẩm'" class="h-full w-full object-contain"></template>
        <p x-show="!selected" class="p-4 text-sm text-zinc-500">Chưa có ảnh sản phẩm.</p>
    </div>
    <div x-on:keydown="onKeydown($event)" class="flex gap-2 overflow-x-auto" role="group" aria-label="Chọn ảnh">
        <template x-for="image in images" :key="image.id"><button type="button" x-on:click="choose(image.id)" x-bind:aria-pressed="(selectedId===image.id).toString()" class="h-20 w-20 shrink-0 border focus-visible:ring-4"><img x-bind:src="image.url" x-bind:alt="image.alt || 'Ảnh thu nhỏ'" class="h-full w-full object-contain"></button></template>
    </div>
    <p class="text-xs text-zinc-500">Dùng phím mũi tên trái/phải để chuyển ảnh.</p>
</section>
