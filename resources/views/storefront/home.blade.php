@extends('layouts.store')

@section('title', 'Cena Store — Giày thể thao & thời trang chính hãng')
@section('meta_description', 'Khám phá bộ sưu tập giày thể thao, chạy bộ, bóng rổ và sneaker thời trang chính hãng tại Cena Store. Giao hàng toàn quốc, đổi size 7 ngày.')

@section('content')
<div class="space-y-12 sm:space-y-16">

    {{-- =====================================================================
         1. HERO BANNER SECTION (FULL WIDTH OF CONTAINER)
         ===================================================================== --}}
    <section aria-label="Banner quảng cáo nổi bật" class="relative">
        @if ($banners->isEmpty())
            {{-- Fallback: Brand Intro khi chưa có banner trong database --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-midnight-900 via-midnight-950 to-midnight-900 p-8 sm:p-12 lg:p-16 text-white border border-midnight-800 shadow-2xl">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.18),transparent_50%)]"></div>
                <div class="relative z-10 max-w-2xl space-y-4 sm:space-y-6">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                        Cena Store • Chính Hãng
                    </span>
                    <h1 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">
                        Thể thao là <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-sky-300 to-indigo-200">không ngừng bỏ cuộc</span>.
                    </h1>
                    <p class="text-base sm:text-lg text-slate-300 leading-relaxed max-w-xl">
                        Khám phá bộ sưu tập giày thể thao cao cấp, công nghệ đệm êm ái hàng đầu sẵn sàng đồng hành trên mọi cung đường.
                    </p>
                    <div class="pt-2 flex flex-wrap gap-3">
                        <a href="{{ route('shoes.index') }}"
                           class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold text-sm shadow-lg shadow-indigo-600/30 transition-all duration-200">
                            Khám phá ngay
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        @elseif ($banners->count() === 1)
            {{-- 1 Banner tĩnh --}}
            @php $singleBanner = $banners->first(); @endphp
            <div class="relative overflow-hidden rounded-2xl bg-midnight-950 border border-midnight-800 shadow-2xl">
                @if ($singleBanner->link_url)
                    <a href="{{ $singleBanner->link_url }}" class="block group relative">
                @else
                    <div class="relative">
                @endif
                    <picture>
                        @if ($singleBanner->mobile_image_path)
                            <source media="(max-width: 640px)" srcset="{{ $singleBanner->mobileImageUrl() }}">
                        @endif
                        <img src="{{ $singleBanner->imageUrl() }}"
                             alt="{{ $singleBanner->title }}"
                             class="w-full h-[260px] sm:h-[380px] lg:h-[460px] object-cover group-hover:scale-[1.01] transition-transform duration-500 ease-out"
                             loading="eager"
                             fetchpriority="high">
                    </picture>
                    <div class="absolute inset-0 bg-gradient-to-t from-midnight-950/85 via-midnight-950/30 to-transparent flex items-end p-6 sm:p-10 lg:p-12">
                        <div class="max-w-xl text-white space-y-2 sm:space-y-3">
                            <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight drop-shadow-md">{{ $singleBanner->title }}</h2>
                            @if ($singleBanner->subtitle)
                                <p class="text-sm sm:text-base text-slate-200 line-clamp-2 drop-shadow">{{ $singleBanner->subtitle }}</p>
                            @endif
                            @if ($singleBanner->cta_text)
                                <span class="inline-flex items-center gap-1.5 pt-2 text-indigo-300 font-semibold text-sm group-hover:text-indigo-200 transition-colors">
                                    {{ $singleBanner->cta_text }}
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </span>
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
            {{-- Carousel (>= 2 Banners) với Alpine.js --}}
            <div x-data="{
                    active: 0,
                    total: {{ $banners->count() }},
                    interval: null,
                    start() {
                        this.interval = setInterval(() => { this.next() }, 5500);
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
                 class="relative overflow-hidden rounded-2xl bg-midnight-950 border border-midnight-800 shadow-2xl group">

                <div class="relative h-[260px] sm:h-[380px] lg:h-[460px]">
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
                                <div class="absolute inset-0 bg-gradient-to-t from-midnight-950/85 via-midnight-950/30 to-transparent flex items-end p-6 sm:p-10 lg:p-12">
                                    <div class="max-w-xl text-white space-y-2 sm:space-y-3">
                                        <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight drop-shadow-md">{{ $banner->title }}</h2>
                                        @if ($banner->subtitle)
                                            <p class="text-sm sm:text-base text-slate-200 line-clamp-2 drop-shadow">{{ $banner->subtitle }}</p>
                                        @endif
                                        @if ($banner->cta_text)
                                            <span class="inline-flex items-center gap-1.5 pt-2 text-indigo-300 font-semibold text-sm">
                                                {{ $banner->cta_text }}
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                            </span>
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
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-midnight-900/60 hover:bg-midnight-900/90 text-white flex items-center justify-center backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity border border-white/10 shadow-lg focus:outline-none focus:opacity-100"
                        aria-label="Banner trước">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button"
                        @click="next()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-midnight-900/60 hover:bg-midnight-900/90 text-white flex items-center justify-center backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity border border-white/10 shadow-lg focus:outline-none focus:opacity-100"
                        aria-label="Banner kế tiếp">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>

                {{-- Indicators --}}
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20">
                    <template x-for="i in total" :key="i">
                        <button type="button"
                                @click="active = i - 1"
                                :class="active === (i - 1) ? 'w-6 bg-indigo-500' : 'w-2 bg-white/50 hover:bg-white/80'"
                                class="h-2 rounded-full transition-all duration-300"
                                :aria-label="'Chuyển đến banner ' + i">
                        </button>
                    </template>
                </div>
            </div>
        @endif
    </section>

    {{-- =====================================================================
         2. BỐ CỤC CHIA CỘT CHÍNH (MAIN ~75% TRÁI | ARTICLES ~25% PHẢI)
         ===================================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">

        {{-- CỘT TRÁI: NỘI DUNG MUA SẮM CHÍNH (8/12 hoặc 9/12) --}}
        <main class="lg:col-span-8 xl:col-span-9 space-y-12 sm:space-y-16">

            {{-- -------------------------------------------------------------
                 A. KHÁM PHÁ THEO ĐỐI TƯỢNG (4 CATEGORY CARDS)
                 ------------------------------------------------------------- --}}
            <section aria-labelledby="heading-categories" class="space-y-5">
                <div class="flex items-end justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Dành cho bạn</span>
                        <h2 id="heading-categories" class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                            Khám phá theo đối tượng
                        </h2>
                    </div>
                    <a href="{{ route('shoes.index') }}" class="text-xs sm:text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                        Xem tất cả
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                    @forelse ($categories as $category)
                        <a href="{{ $category['url'] }}"
                           class="group relative overflow-hidden rounded-xl border border-slate-200 dark:border-midnight-800 bg-white dark:bg-midnight-900/60 p-4 sm:p-5 shadow-sm hover:shadow-md hover:border-indigo-500/50 dark:hover:border-indigo-500/50 transition-all duration-200">
                            <div class="flex flex-col justify-between h-24 sm:h-28">
                                <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-base group-hover:scale-110 transition-transform">
                                    {{ mb_substr($category['name'], 0, 1) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        {{ $category['name'] }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                        <span>{{ $category['tagline'] }}</span>
                                        <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="col-span-full text-center py-6 text-sm text-slate-500">
                            Đang cập nhật danh mục...
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- -------------------------------------------------------------
                 B. KHÁM PHÁ THEO ĐẶC ĐIỂM (KIỂU DÁNG & MỤC ĐÍCH)
                 ------------------------------------------------------------- --}}
            @if ($silhouettes->isNotEmpty() || $activities->isNotEmpty())
                <section aria-label="Bộ sưu tập theo phong cách và mục đích" class="p-5 sm:p-6 rounded-2xl bg-slate-50 dark:bg-midnight-900/40 border border-slate-200/80 dark:border-midnight-800 space-y-4">
                    @if ($silhouettes->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mr-1 min-w-[75px]">
                                Kiểu dáng:
                            </span>
                            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                @foreach ($silhouettes as $silhouette)
                                    <a href="{{ route('shoes.index', ['silhouette' => [$silhouette->slug]]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-midnight-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-midnight-700 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/40 transition-colors">
                                        {{ $silhouette->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($activities->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200/60 dark:border-midnight-800">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mr-1 min-w-[75px]">
                                Mục đích:
                            </span>
                            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                @foreach ($activities as $activity)
                                    <a href="{{ route('shoes.index', ['activity' => [$activity->slug]]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-midnight-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-midnight-700 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/40 transition-colors">
                                        {{ $activity->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            {{-- -------------------------------------------------------------
                 MOBILE ONLY ARTICLE SUMMARY (HIỂN THỊ DƯỚI BỘ LỌC CHO MOBILE)
                 ------------------------------------------------------------- --}}
            @if ($articles->isNotEmpty())
                <div class="block lg:hidden rounded-2xl bg-indigo-500/5 dark:bg-midnight-900/60 border border-indigo-500/10 dark:border-midnight-800 p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                            Bài viết mới nhất
                        </span>
                        <a href="{{ route('news.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Tất cả →
                        </a>
                    </div>
                    <ul class="divide-y divide-slate-200/60 dark:divide-midnight-800">
                        @foreach ($articles->take(3) as $article)
                            <li class="py-2 first:pt-0 last:pb-0">
                                <a href="{{ route('news.show', $article->slug) }}" class="text-sm font-medium text-slate-800 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-1 transition-colors">
                                    {{ $article->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- -------------------------------------------------------------
                 SECTION 1: SẢN PHẨM NỔI BẬT (FEATURED PRODUCTS)
                 ------------------------------------------------------------- --}}
            <section aria-labelledby="heading-featured" class="space-y-5">
                <div class="flex items-end justify-between border-b border-slate-200 dark:border-midnight-800 pb-3">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Tuyển chọn</span>
                        <h2 id="heading-featured" class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                            <span>Sản phẩm nổi bật</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                Hot
                            </span>
                        </h2>
                    </div>
                    <a href="{{ route('shoes.index', ['sort' => 'featured']) }}" class="text-xs sm:text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                        Xem thêm
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if (empty($featuredProducts))
                    <div class="p-8 text-center rounded-xl border border-dashed border-slate-300 dark:border-midnight-800 text-sm text-slate-500">
                        Hiện chưa có sản phẩm nổi bật nào được đánh dấu.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                        @foreach ($featuredProducts as $item)
                            <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- -------------------------------------------------------------
                 SECTION 2: ƯU ĐÃI ĐẶC BIỆT / GIẢM GIÁ (SALE PRODUCTS)
                 ------------------------------------------------------------- --}}
            @if (! empty($saleProducts))
                <section aria-labelledby="heading-sale" class="space-y-5">
                    <div class="flex items-end justify-between border-b border-slate-200 dark:border-midnight-800 pb-3">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Tiết kiệm nhiều hơn</span>
                            <h2 id="heading-sale" class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                                <span>Ưu đãi hấp dẫn</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-500/10 text-rose-500 border border-rose-500/20">
                                    Giảm giá
                                </span>
                            </h2>
                        </div>
                        <a href="{{ route('shoes.index', ['on_sale' => 1]) }}" class="text-xs sm:text-sm font-semibold text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1">
                            Xem thêm
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                        @foreach ($saleProducts as $item)
                            <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- -------------------------------------------------------------
                 SECTION 3: MỚI ĐĂNG BÁN (NEW ARRIVALS)
                 ------------------------------------------------------------- --}}
            <section aria-labelledby="heading-new" class="space-y-5">
                <div class="flex items-end justify-between border-b border-slate-200 dark:border-midnight-800 pb-3">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Bộ sưu tập mới</span>
                        <h2 id="heading-new" class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                            Mới đăng bán
                        </h2>
                    </div>
                    <a href="{{ route('shoes.index', ['sort' => 'newest']) }}" class="text-xs sm:text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                        Xem tất cả
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if (empty($newProducts))
                    <div class="p-8 text-center rounded-xl border border-dashed border-slate-300 dark:border-midnight-800 text-sm text-slate-500">
                        Đang cập nhật các mẫu giày mới nhất.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                        @foreach ($newProducts as $item)
                            <x-catalog.product-card :product="$item['product']" :detail-url="$item['detailUrl']" :image-url="$item['imageUrl']" />
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- -------------------------------------------------------------
                 C. CAM KẾT DỊCH VỤ CENA STORE (VERIFIED POLICIES)
                 ------------------------------------------------------------- --}}
            <section aria-label="Cam kết dịch vụ" class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-200 dark:border-midnight-800">
                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-midnight-900/40 border border-slate-200/60 dark:border-midnight-800">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">100% Chính hãng</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Cam kết nguồn gốc minh bạch</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-midnight-900/40 border border-slate-200/60 dark:border-midnight-800">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">Đổi size 7 ngày</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Hỗ trợ nhanh chóng</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-midnight-900/40 border border-slate-200/60 dark:border-midnight-800">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">Bảo hành 6 tháng</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Lỗi keo chỉ từ nhà sản xuất</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-midnight-900/40 border border-slate-200/60 dark:border-midnight-800">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white">Giao hàng toàn quốc</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Kiểm tra trước thanh toán</p>
                    </div>
                </div>
            </section>

        </main>

        {{-- CỘT PHẢI: BÀI VIẾT GỌN GÀNG (DESKTOP STICKY SIDEBAR ~25%) --}}
        <aside class="hidden lg:block lg:col-span-4 xl:col-span-3 sticky top-24">
            <div class="rounded-2xl border border-slate-200 dark:border-midnight-800 bg-white dark:bg-midnight-900/60 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-midnight-800">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Bài viết mới
                    </h3>
                    <a href="{{ route('news.index') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Tất cả →
                    </a>
                </div>

                @if ($articles->isEmpty())
                    <p class="text-xs text-slate-500 dark:text-slate-400 py-4 text-center">
                        Chưa có bài viết mới.
                    </p>
                @else
                    <ul class="space-y-3.5">
                        @foreach ($articles as $article)
                            <li class="group">
                                <a href="{{ route('news.show', $article->slug) }}" class="block">
                                    <h4 class="text-sm font-medium text-slate-800 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors line-clamp-2 leading-snug">
                                        {{ $article->title }}
                                    </h4>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        <time datetime="{{ $article->published_at?->toIso8601String() }}">
                                            {{ $article->published_at?->format('d/m/Y') }}
                                        </time>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="pt-3 border-t border-slate-100 dark:border-midnight-800">
                    <a href="{{ route('news.index') }}"
                       class="w-full inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-medium bg-slate-50 dark:bg-midnight-800/80 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        Xem tất cả bài viết
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </aside>

    </div>

</div>
@endsection
