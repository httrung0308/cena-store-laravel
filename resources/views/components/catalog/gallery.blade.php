@props(['albums' => [], 'initialColor' => null])
@php($fallbackImage = asset('legacy/img/men.jpg'))
<section x-data="galleryController({albums: @js($albums), initialColor: @js($initialColor)})" x-on:catalog-color-changed.window="changeColor($event.detail.id)" aria-label="Thư viện ảnh sản phẩm" class="min-w-0 space-y-3">
    <div class="aspect-square overflow-hidden bg-zinc-100">
        <template x-if="selected"><img x-bind:src="selected.url" x-bind:alt="selected.alt || 'Ảnh sản phẩm'" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-contain"></template>
        <p x-show="!selected" class="p-4 text-sm text-zinc-500">Chưa có ảnh sản phẩm.</p>
    </div>
    <div class="flex justify-between" x-show="images.length > 1"><button type="button" x-on:click="step(-1)" class="underline" aria-label="Ảnh trước">← Trước</button><button type="button" x-on:click="step(1)" class="underline" aria-label="Ảnh tiếp">Tiếp →</button></div>
    <div x-on:keydown="onKeydown($event)" class="flex gap-2 overflow-x-auto" role="group" aria-label="Chọn ảnh">
        <template x-for="image in images" :key="image.id"><button type="button" x-on:click="choose(image.id)" x-bind:aria-pressed="(selectedId===image.id).toString()" class="h-20 w-20 shrink-0 border focus-visible:ring-4"><img x-bind:src="image.url" x-bind:alt="image.alt || 'Ảnh thu nhỏ'" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-contain"></button></template>
    </div>
    <p class="text-xs text-zinc-500">Dùng phím mũi tên trái/phải để chuyển ảnh.</p>
</section>
