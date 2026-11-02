@props(['albums' => [], 'initialColor' => null])
@php($fallbackImage = asset('legacy/img/men.jpg'))
<section x-data="galleryController({albums: @js($albums), initialColor: @js($initialColor)})" x-on:catalog-color-changed.window="changeColor($event.detail.id)" aria-label="Thư viện ảnh sản phẩm" class="min-w-0 space-y-3">
    {{-- Hover zoom (pointer devices only): the picture scales 2.5x around the cursor position. --}}
    <div class="relative aspect-square overflow-hidden bg-zinc-100"
        x-data="{ zoom: false, originX: 50, originY: 50 }"
        x-on:mouseenter="zoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches"
        x-on:mouseleave="zoom = false"
        x-on:mousemove="const box = $el.getBoundingClientRect(); originX = Math.min(100, Math.max(0, ($event.clientX - box.left) / box.width * 100)); originY = Math.min(100, Math.max(0, ($event.clientY - box.top) / box.height * 100))"
        x-bind:class="zoom && selected ? 'cursor-zoom-in' : ''"
        data-gallery-zoom>
        <template x-if="selected"><img x-bind:src="selected.url" x-bind:alt="selected.alt || 'Ảnh sản phẩm'" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))"
            x-bind:style="{ transformOrigin: `${originX}% ${originY}%`, transform: zoom ? 'scale(2.5)' : 'scale(1)' }"
            class="h-full w-full object-contain transition-transform duration-200 ease-out motion-reduce:transition-none"></template>
        <p x-show="!selected" class="p-4 text-sm text-zinc-500">Chưa có ảnh sản phẩm.</p>
    </div>
    <div class="flex justify-between" x-show="images.length > 1"><button type="button" x-on:click="step(-1)" class="underline" aria-label="Ảnh trước">← Trước</button><button type="button" x-on:click="step(1)" class="underline" aria-label="Ảnh tiếp">Tiếp →</button></div>
    <div x-on:keydown="onKeydown($event)" class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Chọn ảnh">
        <template x-for="(image, index) in images" :key="image.id"><button type="button" x-on:click="choose(image.id)" x-on:mouseenter="choose(image.id)" x-bind:aria-pressed="(selectedId===image.id).toString()" x-bind:aria-label="'Ảnh ' + (index + 1)"
            x-bind:class="selectedId === image.id ? 'border-black' : 'border-zinc-200 hover:border-zinc-400'"
            class="h-20 w-20 shrink-0 overflow-hidden border-2 bg-zinc-100 transition focus-visible:ring-4"><img x-bind:src="image.url" x-bind:alt="image.alt || 'Ảnh thu nhỏ'" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full object-contain"></button></template>
    </div>
    <p class="text-xs text-zinc-500">Rê chuột lên ảnh để phóng to; dùng phím mũi tên trái/phải để chuyển ảnh.</p>
</section>
