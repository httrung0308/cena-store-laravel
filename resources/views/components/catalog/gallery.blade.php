@props(['albums' => [], 'initialColor' => null])
@php($fallbackImage = asset('legacy/img/men.jpg'))
<section x-data="galleryController({albums: @js($albums), initialColor: @js($initialColor)})" x-on:catalog-color-changed.window="changeColor($event.detail.id)" aria-label="Thư viện ảnh sản phẩm" class="min-w-0 space-y-4">
    {{-- Hover zoom (pointer devices only): the picture scales 2.5x around the cursor position. --}}
    <div class="relative aspect-square overflow-hidden rounded-3xl border border-border bg-surface-card p-3 shadow-card"
        x-data="{ zoom: false, originX: 50, originY: 50 }"
        x-on:mouseenter="zoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches"
        x-on:mouseleave="zoom = false"
        x-on:mousemove="const box = $el.getBoundingClientRect(); originX = Math.min(100, Math.max(0, ($event.clientX - box.left) / box.width * 100)); originY = Math.min(100, Math.max(0, ($event.clientY - box.top) / box.height * 100))"
        x-bind:class="zoom && selected ? 'cursor-zoom-in' : ''"
        data-gallery-zoom>
        <template x-if="selected"><img x-bind:src="selected.url" x-bind:alt="selected.alt || 'Ảnh sản phẩm'" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))"
            x-bind:style="{ transformOrigin: `${originX}% ${originY}%`, transform: zoom ? 'scale(2.5)' : 'scale(1)' }"
            class="h-full w-full rounded-2xl object-cover transition-transform duration-200 ease-out motion-reduce:transition-none"></template>
        <p x-show="!selected" class="p-8 text-sm text-muted">Chưa có ảnh sản phẩm.</p>
    </div>
    {{-- Up to four angles, sharing the width of the main image. --}}
    <div x-on:keydown="onKeydown($event)" x-show="images.length > 1" class="grid grid-cols-4 gap-3" role="group" aria-label="Chọn ảnh">
        <template x-for="(image, index) in images.slice(0, 4)" :key="image.id"><button type="button" x-on:click="choose(image.id)" x-on:mouseenter="choose(image.id)" x-bind:aria-pressed="(selectedId===image.id).toString()" x-bind:aria-label="'Ảnh ' + (index + 1)"
            x-bind:class="selectedId === image.id ? 'border-accent ring-2 ring-accent/30' : 'border-border hover:border-accent/60'"
            class="aspect-square w-full overflow-hidden rounded-xl border-2 bg-surface p-1 transition focus-visible:ring-2"><img x-bind:src="image.url" x-bind:alt="image.alt || 'Ảnh thu nhỏ'" x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))" class="h-full w-full rounded-lg object-cover"></button></template>
    </div>
    <p class="text-xs text-muted">Rê chuột lên ảnh để phóng to chi tiết.</p>
</section>
