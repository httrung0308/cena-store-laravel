@extends('layouts.store')

@section('title', 'Cena Store — Giày thể thao & thời trang chính hãng')
@section('meta_description', 'Khám phá bộ sưu tập giày thể thao, chạy bộ, bóng rổ và sneaker thời trang chính hãng tại Cena Store. Giao hàng toàn quốc, đổi size 7 ngày.')

@section('content')
<div class="space-y-16 sm:space-y-20 max-w-7xl mx-auto">

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
         2. KHÁM PHÁ THEO ĐỐI TƯỢNG (4 CATEGORY CARDS NGUYÊN BẢN V4 CÓ ẢNH THỰC)
         ===================================================================== --}}
    <section id="categories" aria-labelledby="heading-categories" class="space-y-6">
        <div class="flex items-end justify-between border-b border-border/80 pb-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-accent font-mono">PHÂN MỤC NỔI BẬT</span>
                <h2 id="heading-categories" class="text-2xl sm:text-3xl font-extrabold text-main mt-1 tracking-tight">
                    Khám phá theo đối tượng
                </h2>
            </div>
            <a href="{{ route('shoes.index') }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                Xem tất cả
                <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            @forelse ($categories as $category)
                <a href="{{ $category['url'] }}"
                   class="group block bg-surface-card rounded-2xl p-3 sm:p-3.5 border border-border hover:border-accent transition-all duration-200 shadow-card hover:shadow-card-hover">
                    <div class="aspect-[4/5] rounded-xl overflow-hidden bg-surface mb-3.5 relative">
                        <img src="{{ $category['image'] }}"
                             alt="{{ $category['name'] }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                             loading="lazy"
                             decoding="async">
                        @if (! empty($category['badge']))
                            <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wider {{ $category['badge'] === 'HOT' ? 'bg-accent text-accent-text' : 'bg-accent-sub text-accent-sub-text' }}">
                                {{ $category['badge'] }}
                            </span>
                        @endif
                    </div>
                    <div class="px-1 pb-1">
                        <h3 class="font-bold text-base sm:text-lg text-main group-hover:text-accent transition-colors">
                            {{ $category['name'] }}
                        </h3>
                        <p class="text-xs text-muted mt-0.5 flex items-center justify-between">
                            <span>{{ $category['tagline'] }}</span>
                            @if (! empty($category['sr_label']))
                                <span class="sr-only">{{ $category['sr_label'] }}</span>
                            @endif
                            <svg class="w-3.5 h-3.5 text-muted group-hover:text-accent group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </a>
            @empty
                <div class="col-span-full text-center py-8 text-sm text-muted">
                    Đang cập nhật danh mục...
                </div>
            @endforelse
        </div>

        {{-- Cụm chip lọc theo Kiểu dáng & Mục đích tinh tế bên dưới --}}
        @if ($silhouettes->isNotEmpty() || $activities->isNotEmpty())
            <div class="p-4 sm:p-5 rounded-2xl bg-surface-card border border-border shadow-subtle space-y-3.5">
                @if ($silhouettes->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted font-mono min-w-[70px]">
                            Kiểu dáng:
                        </span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($silhouettes as $silhouette)
                                <a href="{{ route('shoes.index', ['silhouette' => [$silhouette->slug]]) }}"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-surface hover:bg-surface-card text-main hover:text-accent border border-border hover:border-accent transition-colors shadow-subtle">
                                    {{ $silhouette->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($activities->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-border/60">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted font-mono min-w-[70px]">
                            Mục đích:
                        </span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($activities as $activity)
                                <a href="{{ route('shoes.index', ['activity' => [$activity->slug]]) }}"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-surface hover:bg-surface-card text-main hover:text-accent border border-border hover:border-accent transition-colors shadow-subtle">
                                    {{ $activity->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- =====================================================================
         3. SECTION 1: SẢN PHẨM NỔI BẬT + GÓC BÀI VIẾT (ASYMMETRIC PAIRING)
         ===================================================================== --}}
    <section aria-label="Sản phẩm nổi bật và bài viết mới" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">

            {{-- KHỐI SẢN PHẨM NỔI BẬT (CHIẾM ~70% HOẶC 8/12 TRÊN DESKTOP) --}}
            <div class="lg:col-span-8 xl:col-span-8 space-y-5">
                <div class="flex items-end justify-between border-b border-border/80 pb-3">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-accent font-mono">TUYỂN CHỌN ĐẶC BIỆT</span>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-main mt-0.5 tracking-tight flex items-center gap-2">
                            <span>Sản phẩm nổi bật</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-accent/15 text-accent border border-accent/25">
                                Hot
                            </span>
                        </h2>
                    </div>
                    <a href="{{ route('shoes.index', ['sort' => 'featured']) }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                        Xem thêm
                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if (empty($featuredProducts))
                    <div class="p-10 text-center rounded-2xl border border-dashed border-border text-sm text-muted">
                        Hiện chưa có sản phẩm nổi bật nào được đánh dấu.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 sm:gap-5">
                        @foreach (array_slice($featuredProducts, 0, 6) as $item)
                            <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- KHỐI GÓC BÀI VIẾT (CHIẾM ~30% HOẶC 4/12 TRÊN DESKTOP) --}}
            <aside class="lg:col-span-4 xl:col-span-4 flex flex-col h-full space-y-4">
                <div class="rounded-2xl border border-border bg-surface-card p-5 sm:p-6 shadow-card flex flex-col justify-between h-full space-y-5">
                    <div>
                        <div class="flex items-center justify-between pb-3.5 border-b border-border/80">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-accent font-mono">GÓC BẠN ĐỌC</span>
                                <h3 class="font-extrabold text-base sm:text-lg text-main mt-0.5 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                    Bài viết mới
                                </h3>
                            </div>
                            <a href="{{ route('news.index') }}" class="text-xs font-bold text-accent hover:text-accent-hover">
                                Tất cả →
                            </a>
                        </div>

                        @if ($articles->isEmpty())
                            <p class="text-xs text-muted py-6 text-center">
                                Chưa có bài viết mới.
                            </p>
                        @else
                            <ul class="divide-y divide-border/60">
                                @foreach ($articles->take(4) as $article)
                                    <li class="py-3.5 first:pt-3 last:pb-1 group">
                                        <a href="{{ route('news.show', $article->slug) }}" class="block">
                                            <h4 class="text-sm font-semibold text-main group-hover:text-accent transition-colors line-clamp-2 leading-snug">
                                                {{ $article->title }}
                                            </h4>
                                            <div class="flex items-center gap-2 mt-1.5 text-[11px] text-muted">
                                                <time datetime="{{ $article->published_at?->toIso8601String() }}">
                                                    {{ $article->published_at?->format('d/m/Y') }}
                                                </time>
                                                <span>•</span>
                                                <span class="group-hover:underline">Đọc tiếp →</span>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-border/80">
                        <a href="{{ route('news.index') }}"
                           class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs font-bold border border-border bg-surface hover:bg-surface-card text-main hover:text-accent transition-all shadow-subtle group">
                            <span>Khám phá toàn bộ bài viết</span>
                            <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            </aside>

        </div>
    </section>

    {{-- =====================================================================
         4. SECTION 2: ƯU ĐÃI ĐẶC BIỆT / GIẢM GIÁ (SALE SHOWCASE — FULL WIDTH)
         ===================================================================== --}}
    @if (! empty($saleProducts))
        <section aria-labelledby="heading-sale" class="space-y-6">
            <div class="flex items-end justify-between border-b border-border/80 pb-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-accent font-mono">TIẾT KIỆM NHIỀU HƠN</span>
                    <h2 id="heading-sale" class="text-2xl sm:text-3xl font-extrabold text-main mt-1 tracking-tight flex items-center gap-2.5">
                        <span>Ưu đãi hấp dẫn</span>
                        <span class="badge-ruby text-[11px]">
                            Giảm giá
                        </span>
                    </h2>
                </div>
                <a href="{{ route('shoes.index', ['on_sale' => 1]) }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                    Xem tất cả ưu đãi
                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach (array_slice($saleProducts, 0, 4) as $item)
                    <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- =====================================================================
         5. SECTION 3: MỚI ĐĂNG BÁN (NEW ARRIVALS — FULL WIDTH)
         ===================================================================== --}}
    <section aria-labelledby="heading-new" class="space-y-6">
        <div class="flex items-end justify-between border-b border-border/80 pb-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-accent font-mono">BỘ SƯU TẬP MỚI</span>
                <h2 id="heading-new" class="text-2xl sm:text-3xl font-extrabold text-main mt-1 tracking-tight">
                    Mới đăng bán
                </h2>
            </div>
            <a href="{{ route('shoes.index', ['sort' => 'newest']) }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1 group">
                Xem tất cả
                <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        @if (empty($newProducts))
            <div class="p-10 text-center rounded-2xl border border-dashed border-border text-sm text-muted">
                Đang cập nhật các mẫu giày mới nhất.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach (array_slice($newProducts, 0, 4) as $item)
                    <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- =====================================================================
         6. CAM KẾT DỊCH VỤ CENA STORE (VERIFIED POLICIES)
         ===================================================================== --}}
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

</div>
@endsection
