@extends('layouts.store')

@section('title', 'Cena Store — Giày thể thao & thời trang chính hãng')
@section('meta_description', 'Khám phá bộ sưu tập giày thể thao, chạy bộ, bóng rổ và sneaker thời trang chính hãng tại Cena Store. Giao hàng toàn quốc, đổi size 7 ngày.')

@section('content')
<div class="space-y-14 sm:space-y-16 max-w-7xl mx-auto">

    {{-- =====================================================================
         1. HERO BANNER SECTION (FULL CONTAINER WIDTH)
         ===================================================================== --}}
    <section aria-label="Banner quảng cáo nổi bật" class="relative">
        @if ($banners->isEmpty())
            {{-- Fallback: Brand Hero khi chưa có banner trong database --}}
            <div class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-b from-surface via-bg to-surface-card p-8 sm:p-14 lg:p-16 text-center shadow-card">
                <div class="absolute inset-0 pointer-events-none opacity-20" aria-hidden="true">
                    <svg class="h-full w-full" viewBox="0 0 1200 600" fill="none">
                        <circle cx="980" cy="280" r="260" fill="var(--accent)" fill-opacity="0.25"/>
                        <circle cx="220" cy="180" r="220" fill="var(--accent-sub)" fill-opacity="0.18"/>
                    </svg>
                </div>
                <div class="relative z-10 max-w-3xl mx-auto space-y-5">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent/10 border border-accent/25 text-accent font-mono text-xs font-bold uppercase tracking-widest">
                        CENA • TINH THẦN BỀN BỈ & ÊM ÁI
                    </span>
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-main leading-tight">
                        Thể thao là <br class="hidden sm:inline">
                        <span class="text-accent underline decoration-accent-sub decoration-4 underline-offset-8">không ngừng bỏ cuộc.</span>
                    </h1>
                    <p class="text-base sm:text-lg text-muted max-w-xl mx-auto leading-relaxed font-normal">
                        Tìm lại sự thanh thản và tự tin trong từng chuyển động. Tuyển chọn giày thể thao chính hãng với độ nâng đỡ êm ái suốt ngày dài.
                    </p>
                    <div class="pt-3 flex flex-wrap items-center justify-center gap-4">
                        <a href="{{ route('shoes.index') }}"
                           class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl bg-accent hover:bg-accent-hover text-accent-text font-bold text-xs tracking-wider uppercase shadow-md transition-transform duration-200 transform hover:-translate-y-0.5">
                            Khám phá ngay
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                        <a href="#categories"
                           class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl border border-border bg-surface-card hover:bg-surface text-main font-bold text-xs tracking-wider uppercase transition-colors">
                            Xem phân mục
                        </a>
                    </div>
                </div>
            </div>
        @elseif ($banners->count() === 1)
            {{-- 1 Banner tĩnh --}}
            @php $singleBanner = $banners->first(); @endphp
            <div class="relative overflow-hidden rounded-2xl border border-border bg-surface-card shadow-card group">
                @if ($singleBanner->link_url)
                    <a href="{{ $singleBanner->link_url }}" class="block relative">
                @else
                    <div class="relative">
                @endif
                    <picture>
                        @if ($singleBanner->mobile_image_path)
                            <source media="(max-width: 640px)" srcset="{{ $singleBanner->mobileImageUrl() }}">
                        @endif
                        <img src="{{ $singleBanner->imageUrl() }}"
                             alt="{{ $singleBanner->title }}"
                             class="w-full h-[280px] sm:h-[400px] lg:h-[480px] object-cover group-hover:scale-[1.01] transition-transform duration-500 ease-out"
                             loading="eager"
                             fetchpriority="high">
                    </picture>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent flex items-end p-6 sm:p-10 lg:p-14">
                        <div class="max-w-2xl text-white space-y-2.5 sm:space-y-3.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-accent text-accent-text">
                                Cena Spotlight
                            </span>
                            <h2 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight drop-shadow-md leading-tight">{{ $singleBanner->title }}</h2>
                            @if ($singleBanner->subtitle)
                                <p class="text-sm sm:text-base text-slate-200 line-clamp-2 drop-shadow">{{ $singleBanner->subtitle }}</p>
                            @endif
                            @if ($singleBanner->cta_text)
                                <div class="pt-2">
                                    <span class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-accent-text font-bold text-xs tracking-wider uppercase transition-transform group-hover:translate-x-1">
                                        {{ $singleBanner->cta_text }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @if ($singleBanner->link_url)
                    </a>
                @else
                    </div>
                @endif
            </div>
        @else
            {{-- Carousel (>= 2 Banners) với Alpine.js tự động chuyển slide mỗi 5.0 giây --}}
            <div x-data="{
                    active: 0,
                    total: {{ $banners->count() }},
                    interval: null,
                    start() {
                        this.interval = setInterval(() => { this.next() }, 5000);
                    },
                    stop() {
                        if (this.interval) clearInterval(this.interval);
                    },
                    next() {
                        this.active = (this.active + 1) % this.total;
                    },
                    prev() {
                        this.active = (this.active - 1 + this.total) % this.total;
                    }
                 }"
                 x-init="start()"
                 @mouseenter="stop()"
                 @mouseleave="start()"
                 class="relative overflow-hidden rounded-2xl border border-border bg-surface-card shadow-card group">

                <div class="relative h-[280px] sm:h-[400px] lg:h-[480px]">
                    @foreach ($banners as $index => $banner)
                        <div x-show="active === {{ $index }}"
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-300 absolute inset-0"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0 -translate-x-4"
                             class="h-full w-full">
                            @if ($banner->link_url)
                                <a href="{{ $banner->link_url }}" class="block h-full w-full relative">
                            @else
                                <div class="h-full w-full relative">
                            @endif
                                <picture>
                                    @if ($banner->mobile_image_path)
                                        <source media="(max-width: 640px)" srcset="{{ $banner->mobileImageUrl() }}">
                                    @endif
                                    <img src="{{ $banner->imageUrl() }}"
                                         alt="{{ $banner->title }}"
                                         class="w-full h-full object-cover"
                                         @if ($index === 0) loading="eager" fetchpriority="high" @else loading="lazy" @endif>
                                </picture>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent flex items-end p-6 sm:p-10 lg:p-14">
                                    <div class="max-w-2xl text-white space-y-2.5 sm:space-y-3.5">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-accent text-accent-text">
                                            Cena Spotlight
                                        </span>
                                        <h2 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight drop-shadow-md leading-tight">{{ $banner->title }}</h2>
                                        @if ($banner->subtitle)
                                            <p class="text-sm sm:text-base text-slate-200 line-clamp-2 drop-shadow">{{ $banner->subtitle }}</p>
                                        @endif
                                        @if ($banner->cta_text)
                                            <div class="pt-2">
                                                <span class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-accent-text font-bold text-xs tracking-wider uppercase transition-transform group-hover:translate-x-1">
                                                    {{ $banner->cta_text }}
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @if ($banner->link_url)
                                </a>
                            @else
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Previous / Next Controls --}}
                <button type="button"
                        @click="prev()"
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-surface-card/85 hover:bg-surface-card text-main flex items-center justify-center backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity border border-border shadow-subtle focus:outline-none focus:opacity-100"
                        aria-label="Banner trước">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button"
                        @click="next()"
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-surface-card/85 hover:bg-surface-card text-main flex items-center justify-center backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity border border-border shadow-subtle focus:outline-none focus:opacity-100"
                        aria-label="Banner kế tiếp">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>

                {{-- Indicators --}}
                <div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20">
                    <template x-for="i in total" :key="i">
                        <button type="button"
                                @click="active = i - 1"
                                :class="active === (i - 1) ? 'w-6 bg-accent' : 'w-2 bg-white/60 hover:bg-white/90'"
                                class="h-2 rounded-full transition-all duration-300"
                                :aria-label="'Chuyển đến banner ' + i">
                        </button>
                    </template>
                </div>
            </div>
        @endif
    </section>

    {{-- =====================================================================
         BỐ CỤC 2 CỘT XUYÊN SUỐT TRÊN DESKTOP:
         - CỘT TRÁI (~75% / 8-9 cột): TOÀN BỘ NỘI DUNG MUA SẮM CHÍNH
         - CỘT PHẢI (~25% / 3-4 cột): STICKY SIDEBAR BÀI VIẾT + TIỆN ÍCH DỊCH VỤ
         ===================================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">

        {{-- =================================================================
             CỘT TRÁI: NỘI DUNG CHÍNH (3 KHU VỰC SẢN PHẨM + CAM KẾT DỊCH VỤ)
             (order-1 lg:col-span-8 xl:col-span-9)
             ================================================================= --}}
        @php($headingVariant = request()->query('headings') === 'b' ? 'b' : 'a')
        <main class="order-1 lg:col-span-8 xl:col-span-9 space-y-10 sm:space-y-12 min-w-0">

            {{-- -------------------------------------------------------------
                 A. KHU VỰC 1: SẢN PHẨM NỔI BẬT — INTERACTIVE SPOTLIGHT
                 ------------------------------------------------------------- --}}
            <section aria-labelledby="heading-featured" class="space-y-5">
                <div class="relative flex items-center justify-between border-b border-border/80 pb-3">
                    <h2 id="heading-featured" @class(['text-xl sm:text-2xl font-extrabold text-main tracking-tight', 'flex items-center gap-2' => $headingVariant === 'b'])>
                        @if ($headingVariant === 'b')
                            <svg class="w-5 h-5 shrink-0 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        @endif
                        <span>Sản phẩm nổi bật</span>
                    </h2>
                    <span aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-[3px] w-14 rounded-full bg-accent"></span>
                    <a href="{{ route('shoes.index', ['sort' => 'featured']) }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                        <span>Xem tất cả</span>
                        <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if (empty($featuredProducts))
                    <div class="p-10 text-center rounded-2xl border border-dashed border-border text-sm text-muted">
                        Hiện chưa có sản phẩm nổi bật nào được đánh dấu.
                    </div>
                @else
                    {{-- Interactive Spotlight Component with Alpine.js --}}
                    <div x-data="{
                            items: {{ Js::from($featuredProducts) }},
                            active: 0,
                            timer: null,
                            paused: false,
                            reducedMotion: false,
                            init() {
                                this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                                this.start();

                                document.addEventListener('visibilitychange', () => {
                                    if (document.hidden) {
                                        this.stop();
                                    } else {
                                        this.start();
                                    }
                                });
                            },
                            get current() {
                                return this.items[this.active] || this.items[0];
                            },
                            start() {
                                if (this.reducedMotion || this.items.length <= 1) return;
                                this.stop();
                                this.timer = setInterval(() => {
                                    if (!this.paused && !document.hidden) {
                                        this.active = (this.active + 1) % this.items.length;
                                    }
                                }, 7000);
                            },
                            stop() {
                                if (this.timer) {
                                    clearInterval(this.timer);
                                    this.timer = null;
                                }
                            },
                            select(index, manual = false) {
                                this.active = index;
                                if (manual) {
                                    this.paused = true;
                                    this.stop();
                                    setTimeout(() => {
                                        this.paused = false;
                                        this.start();
                                    }, 12000);
                                }
                            },
                            prev() {
                                this.active = (this.active - 1 + this.items.length) % this.items.length;
                            },
                            next() {
                                this.active = (this.active + 1) % this.items.length;
                            },
                            formatPrice(val) {
                                return new Intl.NumberFormat('vi-VN').format(val) + ' ₫';
                            }
                         }"
                         @mouseenter="paused = true"
                         @mouseleave="paused = false"
                         class="space-y-4">

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-stretch">

                            {{-- CARD LỚN TIÊU ĐIỂM (LEAD SPOTLIGHT CARD - Chiếm 7/12 trên md/lg) --}}
                            <div class="md:col-span-7 xl:col-span-7 rounded-2xl border border-border bg-surface-card p-4 sm:p-5 shadow-card flex flex-col justify-between group relative overflow-hidden">
                                <div class="space-y-3">
                                    {{-- Huy hiệu và tiêu đề --}}
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-accent text-accent-text">
                                            Nổi bật
                                        </span>
                                        <template x-if="current.product.has_discount">
                                            <span class="badge-ruby text-[10px]" x-text="'-' + current.product.discount_percent + '%'"></span>
                                        </template>
                                    </div>

                                    {{-- Khung ảnh sản phẩm tiêu điểm: Căn giữa hoàn hảo, tỷ lệ cân đối ở zoom 100% --}}
                                    <div class="h-[210px] sm:h-[230px] w-full rounded-xl overflow-hidden bg-surface/40 relative border border-border/60 flex items-center justify-center p-3">
                                        <a :href="current.detailUrl" class="w-full h-full flex items-center justify-center">
                                            <img :src="current.imageUrl"
                                                 :alt="current.product.name"
                                                 class="max-h-full max-w-full object-contain object-center group-hover:scale-105 transition-transform duration-500 ease-out"
                                                 loading="eager"
                                                 fetchpriority="high">
                                        </a>
                                        <template x-if="current.product.sold_out">
                                            <span class="absolute bottom-2.5 left-2.5 rounded-md bg-main px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">
                                                Hết hàng
                                            </span>
                                        </template>
                                    </div>

                                    {{-- Thông tin sản phẩm --}}
                                    <div class="space-y-1">
                                        <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted" x-text="current.product.brand_line || 'Cena Store'"></p>
                                        <h3 class="text-base sm:text-lg font-extrabold text-main leading-snug line-clamp-1">
                                            <a :href="current.detailUrl" class="hover:text-accent transition-colors" x-text="current.product.name"></a>
                                        </h3>
                                        <div class="flex items-baseline gap-2.5 pt-0.5">
                                            <span class="text-lg sm:text-xl font-black text-accent" x-text="formatPrice(current.product.effective_price)"></span>
                                            <template x-if="current.product.has_discount">
                                                <span class="text-xs text-muted line-through" x-text="formatPrice(current.product.base_price)"></span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Dải phối màu sắc nếu có --}}
                                    <template x-if="current.product.colors && current.product.colors.length > 0">
                                        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                            <span class="text-[11px] text-muted mr-1">Màu sắc:</span>
                                            <template x-for="color in current.product.colors" :key="color.id">
                                                <a :href="color.url"
                                                   :title="color.name"
                                                   class="w-5 h-5 rounded-md overflow-hidden border border-border/80 hover:border-accent shadow-subtle transition-transform hover:scale-110">
                                                    <img :src="color.image_url" :alt="color.name" class="w-full h-full object-cover">
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                {{-- CTA Button --}}
                                <div class="pt-3.5 mt-3 border-t border-border/60 flex items-center justify-between">
                                    <span class="text-[11px] text-muted">Bảo hành 6T • Đổi size 7N</span>
                                    <a :href="current.detailUrl"
                                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-accent hover:bg-accent-hover text-accent-text font-bold text-xs tracking-wider uppercase transition-all shadow-md transform hover:-translate-y-0.5">
                                        Xem chi tiết
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </a>
                                </div>
                            </div>

                            {{-- DANH SÁCH CARD NHỎ TƯƠNG TÁC (Chiếm 5/12 trên md/lg, co giãn khớp chiều cao card lớn) --}}
                            <div class="md:col-span-5 xl:col-span-5 flex flex-col gap-2.5 min-h-0">
                                <div class="relative flex-1 md:min-h-0">
                                    <div class="space-y-2 max-h-[340px] md:max-h-none md:absolute md:inset-0 overflow-y-auto p-0.5 pr-1.5 scrollbar-none">
                                        <template x-for="(item, idx) in items" :key="item.product.id">
                                            <div @mouseenter="select(idx)"
                                                 @click="select(idx, true)"
                                                 :class="active === idx ? 'border-accent bg-accent/5 ring-1 ring-accent/30 shadow-sm' : 'border-border bg-surface-card hover:border-accent/50 hover:bg-surface'"
                                                 class="p-2 sm:p-2.5 rounded-xl border transition-colors duration-200 cursor-pointer flex items-center gap-2.5 group/item">
                                                <div class="w-12 h-12 shrink-0 rounded-lg overflow-hidden bg-surface/50 border border-border/60 flex items-center justify-center p-1">
                                                    <img :src="item.imageUrl" :alt="item.product.name" class="max-h-full max-w-full object-contain group-hover/item:scale-105 transition-transform duration-300">
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="text-xs sm:text-sm font-bold text-main line-clamp-1 group-hover/item:text-accent transition-colors" x-text="item.product.name"></h4>
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <span class="text-xs font-bold text-accent" x-text="formatPrice(item.product.effective_price)"></span>
                                                        <template x-if="item.product.has_discount">
                                                            <span class="text-[10px] text-muted line-through" x-text="formatPrice(item.product.base_price)"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="shrink-0 text-muted group-hover/item:text-accent">
                                                    <svg class="w-4 h-4 transform group-hover/item:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Điều khiển thủ công & Trạng thái --}}
                                <div class="shrink-0 pt-2.5 border-t border-border/60 flex items-center justify-between text-xs text-muted">
                                    <div class="flex items-center gap-1.5 font-mono">
                                        <span>Mục</span>
                                        <span class="font-bold text-main" x-text="active + 1"></span>
                                        <span>/</span>
                                        <span x-text="items.length"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                @click="prev()"
                                                class="w-8 h-8 rounded-lg border border-border bg-surface-card hover:bg-surface text-main flex items-center justify-center transition-colors"
                                                aria-label="Sản phẩm nổi bật trước">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                        </button>
                                        <button type="button"
                                                @click="next()"
                                                class="w-8 h-8 rounded-lg border border-border bg-surface-card hover:bg-surface text-main flex items-center justify-center transition-colors"
                                                aria-label="Sản phẩm nổi bật kế tiếp">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                @endif
            </section>

            {{-- -------------------------------------------------------------
                 C. KHU VỰC 2: SẢN PHẨM GIẢM GIÁ — HORIZONTAL SHELF
                 ------------------------------------------------------------- --}}
            @if (! empty($saleProducts))
                <section aria-labelledby="heading-sale" class="space-y-6">
                    {{-- Alpine shelf scroller container --}}
                    <div x-data="{
                            canPrev: false,
                            canNext: true,
                            checkScroll() {
                                const el = this.$refs.shelf;
                                if (!el) return;
                                this.canPrev = el.scrollLeft > 10;
                                this.canNext = el.scrollLeft < (el.scrollWidth - el.clientWidth - 10);
                            },
                            scrollPrev() {
                                this.$refs.shelf.scrollBy({ left: -280, behavior: 'smooth' });
                            },
                            scrollNext() {
                                this.$refs.shelf.scrollBy({ left: 280, behavior: 'smooth' });
                            }
                         }"
                         x-init="setTimeout(() => checkScroll(), 300)"
                         class="space-y-5">

                        <div class="relative flex items-center justify-between border-b border-border/80 pb-3">
                            <h2 id="heading-sale" @class(['text-xl sm:text-2xl font-extrabold text-main tracking-tight', 'flex items-center gap-2' => $headingVariant === 'b'])>
                                @if ($headingVariant === 'b')
                                    <svg class="w-5 h-5 shrink-0 text-accent-sub" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                @endif
                                <span>Sản phẩm giảm giá</span>
                            </h2>
                            <span aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-[3px] w-14 rounded-full bg-accent-sub"></span>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('shoes.index', ['on_sale' => 1]) }}" class="hidden sm:inline-flex text-xs font-bold text-accent hover:text-accent-hover items-center gap-1 group">
                                    <span>Xem tất cả</span>
                                    <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>

                                {{-- Nút chuyển sản phẩm kệ trượt --}}
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="scrollPrev()"
                                            :disabled="!canPrev"
                                            class="w-9 h-9 rounded-xl border border-border bg-surface-card hover:bg-surface text-main flex items-center justify-center shadow-subtle disabled:opacity-35 transition-all"
                                            aria-label="Xem sản phẩm giảm giá trước">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <button type="button"
                                            @click="scrollNext()"
                                            :disabled="!canNext"
                                            class="w-9 h-9 rounded-xl border border-border bg-surface-card hover:bg-surface text-main flex items-center justify-center shadow-subtle disabled:opacity-35 transition-all"
                                            aria-label="Xem sản phẩm giảm giá kế tiếp">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Dải trượt ngang Shelf --}}
                        <div x-ref="shelf"
                             @scroll.passive="checkScroll()"
                             class="flex gap-4 sm:gap-5 overflow-x-auto scrollbar-none snap-x snap-mandatory py-2 scroll-smooth -mx-1 px-1">
                            @foreach ($saleProducts as $item)
                                <div class="w-[230px] sm:w-[260px] shrink-0 snap-start">
                                    <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- -------------------------------------------------------------
                 D. KHU VỰC 3: SẢN PHẨM MỚI — PRODUCT GRID
                 ------------------------------------------------------------- --}}
            <section aria-labelledby="heading-new" class="space-y-5">
                <div class="relative flex items-center justify-between border-b border-border/80 pb-3">
                    <h2 id="heading-new" class="text-xl sm:text-2xl font-extrabold text-main tracking-tight">
                        Sản phẩm mới
                    </h2>
                    @if ($headingVariant === 'a')
                        <span aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-[2px] w-10 rounded-full bg-muted/35"></span>
                    @endif
                    <a href="{{ route('shoes.index', ['sort' => 'newest']) }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                        <span>Xem tất cả</span>
                        <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if (empty($newProducts))
                    <div class="p-10 text-center rounded-2xl border border-dashed border-border text-sm text-muted">
                        Đang cập nhật các mẫu giày mới nhất.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5 sm:gap-4.5">
                        @foreach ($newProducts as $item)
                            <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- -------------------------------------------------------------
                 E. CAM KẾT DỊCH VỤ CENA STORE (VERIFIED POLICIES)
                 ------------------------------------------------------------- --}}
            <section aria-label="Cam kết dịch vụ" class="grid grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-border">
                <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-surface-card border border-border shadow-subtle">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-main">100% Chính hãng</h3>
                        <p class="text-[11px] text-muted mt-0.5 leading-snug">Cam kết nguồn gốc minh bạch</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-surface-card border border-border shadow-subtle">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-main">Đổi size 7 ngày</h3>
                        <p class="text-[11px] text-muted mt-0.5 leading-snug">Hỗ trợ đổi cỡ chân an tâm</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-surface-card border border-border shadow-subtle">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-main">Bảo hành 6 tháng</h3>
                        <p class="text-[11px] text-muted mt-0.5 leading-snug">Bảo hành keo chỉ chuẩn hãng</p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-surface-card border border-border shadow-subtle">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-main">Giao hàng toàn quốc</h3>
                        <p class="text-[11px] text-muted mt-0.5 leading-snug">Kiểm tra trước khi thanh toán</p>
                    </div>
                </div>
            </section>

        </main>

        {{-- =================================================================
             CỘT PHẢI: BÀI VIẾT & DANH MỤC PHÂN LOẠI
             (order-2 lg:col-span-4 xl:col-span-3 space-y-5 lg:sticky lg:top-24 self-start lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto scrollbar-none)
             ================================================================= --}}
        <aside class="order-2 lg:col-span-4 xl:col-span-3 space-y-5 lg:sticky lg:top-24 self-start lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto scrollbar-none">

            {{-- KHỐI 1: BÀI VIẾT (Ở TRÊN CÙNG ĐỂ THẤY NGAY KHI VÀO TRANG) --}}
            <div class="rounded-2xl border border-border bg-surface-card p-5 shadow-card space-y-4">
                <div class="flex items-center justify-between pb-3.5 border-b border-border/80">
                    <h3 class="font-extrabold text-base sm:text-lg text-main flex items-center gap-2">
                        <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                        <span>Bài viết</span>
                    </h3>
                    <a href="{{ route('news.index') }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                        <span>Tất cả bài viết</span>
                        <svg class="w-3.5 h-3.5 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if ($articles->isEmpty())
                    <p class="text-xs text-muted py-6 text-center">
                        Chưa có bài viết mới.
                    </p>
                @else
                    <ul class="divide-y divide-border/60">
                        @foreach ($articles as $article)
                            <li class="py-3 first:pt-1 last:pb-1 group">
                                <a href="{{ route('news.show', $article->slug) }}" class="block">
                                    <h4 class="text-xs sm:text-sm font-semibold text-main group-hover:text-accent transition-colors line-clamp-2 leading-snug">
                                        {{ $article->title }}
                                    </h4>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-muted">
                                        <time datetime="{{ $article->published_at?->toIso8601String() }}">
                                            {{ $article->published_at?->format('d/m/Y') }}
                                        </time>
                                        <span>•</span>
                                        <span class="group-hover:underline text-accent font-medium">Chi tiết →</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- KHỐI 2: DANH MỤC & PHÂN LOẠI (NẰM DƯỚI BÀI VIẾT) --}}
            <div id="categories" class="rounded-2xl border border-border bg-surface-card p-5 shadow-card space-y-4">
                <div class="flex items-center justify-between pb-3.5 border-b border-border/80">
                    <h3 class="font-extrabold text-base sm:text-lg text-main">
                        <a href="{{ route('categories.index') }}" class="flex items-center gap-2 hover:text-accent transition-colors">
                            <svg class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            <span>Danh mục &amp; Phân loại</span>
                        </a>
                    </h3>
                    <a href="{{ route('categories.index') }}" class="text-muted hover:text-accent transition-colors p-1 -mr-1" title="Mở trang Danh mục &amp; Phân loại" aria-label="Mở trang Danh mục &amp; Phân loại">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- 1. Nhóm Đối tượng (4 ô gọn gàng 2x2) --}}
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted font-mono">
                        Đối tượng
                    </h4>

                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($categories as $category)
                            <a href="{{ $category['url'] }}"
                               class="group flex items-center gap-2.5 p-2 rounded-xl border border-border bg-surface hover:bg-surface-card hover:border-accent text-main transition-colors shadow-subtle">
                                <div class="w-7 h-7 rounded-lg bg-surface-card flex items-center justify-center shrink-0 border border-border/70 group-hover:border-accent group-hover:text-accent text-muted transition-colors">
                                    @if ($category['slug'] === 'men')
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3h5m0 0v5m0-5l-6 6M14 14a5 5 0 11-10 0 5 5 0 0110 0z"/></svg>
                                    @elseif ($category['slug'] === 'women')
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14a5 5 0 100-10 5 5 0 000 10zm0 0v6m-3-3h6"/></svg>
                                    @elseif ($category['slug'] === 'kids')
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @else
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    @endif
                                </div>
                                <span class="font-bold text-xs text-main group-hover:text-accent transition-colors truncate">
                                    {{ $category['name'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- 2. Nhóm Kiểu dáng --}}
                @if ($silhouettes->isNotEmpty())
                    <div class="space-y-2 pt-1 border-t border-border/50">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-muted font-mono flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                                <span>Kiểu dáng</span>
                            </h4>
                            <span class="text-[10px] font-mono text-muted">{{ $silhouettes->count() }} mục</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5 max-h-[95px] overflow-y-auto scrollbar-none">
                            @foreach ($silhouettes as $silhouette)
                                <a href="{{ route('shoes.index', ['silhouette' => [$silhouette->slug]]) }}"
                                   class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-surface hover:bg-surface-card text-main hover:text-accent border border-border hover:border-accent transition-colors shadow-subtle">
                                    {{ $silhouette->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- 3. Nhóm Mục đích sử dụng --}}
                @if ($activities->isNotEmpty())
                    <div class="space-y-2 pt-1 border-t border-border/50">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-muted font-mono flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span>Mục đích</span>
                            </h4>
                            <span class="text-[10px] font-mono text-muted">{{ $activities->count() }} mục</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5 max-h-[95px] overflow-y-auto scrollbar-none">
                            @foreach ($activities as $activity)
                                <a href="{{ route('shoes.index', ['activity' => [$activity->slug]]) }}"
                                   class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-accent/5 hover:bg-accent/15 text-main hover:text-accent border border-accent/25 hover:border-accent transition-colors shadow-subtle gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                                    <span>{{ $activity->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

        </aside>

    </div>

</div>
@endsection
