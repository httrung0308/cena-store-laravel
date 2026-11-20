@props(['albums' => [], 'initialColor' => null])
@php($fallbackImage = asset('legacy/img/men.jpg'))
<section x-data="galleryController({albums: @js($albums), initialColor: @js($initialColor)})"
    x-on:catalog-color-changed.window="changeColor($event.detail.id)"
    x-on:keydown.window="onKeydown($event)"
    aria-label="Thư viện ảnh sản phẩm"
    class="min-w-0 space-y-3.5">

    {{-- Main Image Stage --}}
    <div class="group relative aspect-square overflow-hidden rounded-3xl border border-border bg-surface-card shadow-card"
        x-data="{ zoom: false, originX: 50, originY: 50 }"
        x-on:mouseenter="zoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches && !!selected"
        x-on:mouseleave="zoom = false"
        x-on:mousemove="const box = $el.getBoundingClientRect(); originX = Math.min(100, Math.max(0, ($event.clientX - box.left) / box.width * 100)); originY = Math.min(100, Math.max(0, ($event.clientY - box.top) / box.height * 100))"
        x-bind:class="zoom && selected ? 'cursor-zoom-in' : ''"
        data-gallery-zoom>

        <template x-if="selected">
            <img x-bind:src="selected.url"
                x-bind:alt="selected.alt || 'Ảnh sản phẩm'"
                x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))"
                x-on:click="openLightbox()"
                x-bind:style="{ transformOrigin: `${originX}% ${originY}%`, transform: zoom ? 'scale(2.25)' : 'scale(1)' }"
                class="h-full w-full object-cover transition-transform duration-200 ease-out motion-reduce:transition-none">
        </template>

        <div x-show="!selected" class="flex h-full flex-col items-center justify-center gap-2 p-8 text-center text-sm text-muted">
            <svg class="h-10 w-10 text-muted/40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z" />
            </svg>
            <span>Chưa có ảnh cho phối màu này.</span>
        </div>

        {{-- Prev / Next arrows on main stage --}}
        <template x-if="images.length > 1">
            <div>
                <button type="button"
                    x-on:click.stop="step(-1)"
                    aria-label="Ảnh trước"
                    class="absolute left-3 top-1/2 -translate-y-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full border border-border bg-surface-card/90 text-main shadow-md transition hover:border-accent hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button type="button"
                    x-on:click.stop="step(1)"
                    aria-label="Ảnh kế tiếp"
                    class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full border border-border bg-surface-card/90 text-main shadow-md transition hover:border-accent hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </template>

        {{-- Counter & Lightbox Trigger --}}
        <div class="pointer-events-none absolute inset-x-3 bottom-3 flex items-center justify-between gap-2">
            <span x-cloak
                x-show="images.length > 1"
                x-text="currentIndex + ' / ' + images.length"
                class="pointer-events-auto rounded-full border border-border bg-surface-card/95 px-2.5 py-1 text-[11px] font-bold text-main shadow-subtle"></span>

            <button type="button"
                x-cloak
                x-show="selected"
                x-on:click.stop="openLightbox()"
                aria-label="Phóng to ảnh sản phẩm"
                class="pointer-events-auto ml-auto inline-flex items-center gap-1.5 rounded-full border border-border bg-surface-card/95 px-3 py-1.5 text-[11px] font-semibold text-main shadow-subtle transition hover:border-accent hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                </svg>
                <span>Phóng to</span>
            </button>
        </div>
    </div>

    {{-- Thumbnail Rail (renders ALL images of current color cleanly) --}}
    <div x-show="images.length > 1"
        class="grid grid-cols-5 gap-2 sm:grid-cols-6 sm:gap-2.5"
        role="group"
        aria-label="Chọn ảnh">
        <template x-for="(image, index) in images" :key="image.id">
            <button type="button"
                x-on:click="choose(image.id)"
                x-bind:aria-pressed="(selectedId === image.id).toString()"
                x-bind:aria-label="'Ảnh ' + (index + 1)"
                x-bind:class="selectedId === image.id ? 'border-accent ring-2 ring-accent/30' : 'border-border hover:border-accent/60'"
                class="aspect-square w-full overflow-hidden rounded-xl border-2 bg-surface-card p-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <img x-bind:src="image.thumb_url || image.url"
                    x-bind:alt="image.alt || 'Ảnh thu nhỏ ' + (index + 1)"
                    loading="lazy"
                    decoding="async"
                    width="100"
                    height="100"
                    x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))"
                    class="h-full w-full rounded-lg object-cover aspect-square">
            </button>
        </template>
    </div>

    {{-- Fullscreen Lightbox Dialog --}}
    <div x-cloak
        x-show="lightboxOpen"
        x-transition.opacity
        role="dialog"
        aria-modal="true"
        aria-label="Xem ảnh phóng to"
        x-on:click.self="closeLightbox()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4 sm:p-8">
        <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col items-center gap-4">
            <div class="flex w-full items-center justify-between text-white">
                <span class="text-xs font-semibold" x-show="images.length > 1" x-text="'Ảnh ' + currentIndex + ' / ' + images.length"></span>
                <button type="button"
                    x-on:click="closeLightbox()"
                    aria-label="Đóng xem ảnh phóng to"
                    class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/25 bg-black/50 text-white transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="relative flex max-h-[75vh] w-full items-center justify-center overflow-hidden rounded-2xl bg-surface-card">
                <template x-if="selected">
                    <img x-bind:src="selected.url"
                        x-bind:alt="selected.alt || 'Ảnh sản phẩm phóng to'"
                        x-on:error="$el.src !== @js($fallbackImage) && ($el.src = @js($fallbackImage))"
                        class="max-h-[75vh] w-auto max-w-full object-contain">
                </template>
                <template x-if="images.length > 1">
                    <div>
                        <button type="button"
                            x-on:click="step(-1)"
                            aria-label="Ảnh trước"
                            class="absolute left-3 top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface-card/90 text-main shadow-lg transition hover:border-accent">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        <button type="button"
                            x-on:click="step(1)"
                            aria-label="Ảnh kế tiếp"
                            class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface-card/90 text-main shadow-lg transition hover:border-accent">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</section>
