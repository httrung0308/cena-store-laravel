@props(['animated' => true])
@php
    $genderLinks = [
        'Giày Nam' => ['men', 'men'],
        'Giày Nữ' => ['women', 'women'],
        'Trẻ Em' => ['kids', 'kid'],
        'Unisex' => ['unisex', 'unisex'],
    ];
    $navSilhouettes = $navSilhouettes ?? collect();
    $navActivities = $navActivities ?? collect();
    $isAdminAccount = auth()->user()?->hasRole(App\Enums\RoleSlug::Admin) ?? false;

    $isProductsActive = request()->routeIs('shoes.*');
    $isCategoriesActive = request()->routeIs('categories.index');
    $isNewsActive = request()->routeIs('news.*');
@endphp
<header x-data="{ searchOpen: false }"
        @keydown.escape.window="searchOpen = false"
        @class(['sticky top-0 z-40 border-b border-border bg-surface-card shadow-subtle transition-colors duration-150', 'legacy-navigation' => $animated])>
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8" aria-label="Main navigation">
        {{-- Trái: Logo CENA --}}
        <h2 class="shrink-0">
            <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="Cena — trang chủ">
                <img src="{{ asset('cena-logo.svg') }}" alt="Cena" class="theme-logo-light h-8 w-auto sm:h-9">
                <img src="{{ asset('cena-logo-white.svg') }}" alt="Cena" class="theme-logo-dark h-8 w-auto sm:h-9">
            </a>
        </h2>

        {{-- Giữa: Sản phẩm · Danh mục & Phân loại · Bài viết --}}
        <div class="hidden items-center gap-6 md:flex lg:gap-8" @mouseleave="hoverImage = null">
            <a href="{{ route('shoes.index') }}"
               @if ($isProductsActive) aria-current="page" @endif
               @class([
                   'relative whitespace-nowrap py-1 text-[13px] tracking-wide transition-colors',
                   'font-bold text-accent' => $isProductsActive,
                   'font-semibold text-muted hover:text-accent' => ! $isProductsActive,
               ])>
                <span>Sản phẩm</span>
                @if ($isProductsActive)
                    <span aria-hidden="true" class="absolute inset-x-0 -bottom-0.5 h-0.5 rounded-full bg-accent"></span>
                @endif
            </a>

            {{-- Dropdown Danh mục & Phân loại (hover, click/touch và bàn phím) --}}
            <div x-data="{
                    open: false,
                    closeTimer: null,
                    openMenu() {
                        clearTimeout(this.closeTimer);
                        this.open = true;
                    },
                    scheduleClose() {
                        clearTimeout(this.closeTimer);
                        this.closeTimer = setTimeout(() => { this.open = false; }, 140);
                    },
                    closeMenu() {
                        clearTimeout(this.closeTimer);
                        this.open = false;
                        hoverImage = null;
                    }
                 }"
                 @mouseenter="openMenu()"
                 @mouseleave="scheduleClose(); hoverImage = null"
                 @keydown.escape.window="closeMenu()"
                 @click.outside="closeMenu()"
                 class="relative">
                <button type="button"
                        @click="open ? closeMenu() : openMenu()"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        aria-controls="header-categories-menu"
                        @if ($isCategoriesActive) aria-current="page" @endif
                        @class([
                            'relative inline-flex items-center gap-1.5 whitespace-nowrap py-1 text-[13px] tracking-wide transition-colors',
                            'font-bold text-accent' => $isCategoriesActive,
                            'font-semibold text-muted hover:text-accent' => ! $isCategoriesActive,
                        ])
                        :class="open && 'text-accent font-bold'">
                    <span>Danh mục &amp; Phân loại</span>
                    <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    @if ($isCategoriesActive)
                        <span aria-hidden="true" class="absolute inset-x-0 -bottom-0.5 h-0.5 rounded-full bg-accent"></span>
                    @endif
                </button>

                <div id="header-categories-menu"
                     x-cloak
                     x-show="open"
                     x-transition:enter="transition duration-150 ease-out"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition duration-100 ease-in"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-1"
                     @mouseenter="openMenu()"
                     @mouseleave="scheduleClose(); hoverImage = null"
                     class="absolute left-1/2 top-full z-50 mt-3 w-[min(44rem,calc(100vw-2rem))] -translate-x-1/2 rounded-2xl border border-border bg-surface-card p-5 shadow-elevated">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                        {{-- 1. Đối tượng --}}
                        <div class="space-y-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted font-mono">
                                Đối tượng
                            </p>
                            <ul class="space-y-1">
                                @foreach ($genderLinks as $label => [$gender, $image])
                                    <li>
                                        <a href="{{ route('shoes.index', ['gender' => $gender]) }}"
                                           @mouseenter="hoverImage = '{{ asset('legacy/img/'.$image.'.jpg') }}'"
                                           @mouseleave="hoverImage = null"
                                           @focus="hoverImage = null"
                                           @click="closeMenu()"
                                           class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-semibold text-main transition-colors hover:bg-surface hover:text-accent">
                                            <span>{{ $label }}</span>
                                            <svg class="h-3.5 w-3.5 text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- 2. Kiểu dáng (giới hạn 8 mục) --}}
                        <div class="space-y-2.5 border-border/60 md:border-l md:pl-5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted font-mono">
                                Kiểu dáng
                            </p>
                            @if ($navSilhouettes->isEmpty())
                                <p class="text-xs text-muted">Đang cập nhật.</p>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($navSilhouettes as $silhouette)
                                        <a href="{{ route('shoes.index', ['silhouette' => [$silhouette->slug]]) }}"
                                           @click="closeMenu()"
                                           class="inline-flex items-center rounded-lg border border-border bg-surface px-2.5 py-1 text-xs font-medium text-main transition-colors hover:border-accent hover:bg-surface-card hover:text-accent">
                                            {{ $silhouette->name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- 3. Mục đích sử dụng (giới hạn 8 mục) --}}
                        <div class="space-y-2.5 border-border/60 md:border-l md:pl-5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted font-mono">
                                Mục đích sử dụng
                            </p>
                            @if ($navActivities->isEmpty())
                                <p class="text-xs text-muted">Đang cập nhật.</p>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($navActivities as $activity)
                                        <a href="{{ route('shoes.index', ['activity' => [$activity->slug]]) }}"
                                           @click="closeMenu()"
                                           class="inline-flex items-center gap-1.5 rounded-full border border-accent/25 bg-accent/5 px-2.5 py-1 text-xs font-medium text-main transition-colors hover:border-accent hover:bg-accent/15 hover:text-accent">
                                            <span class="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden="true"></span>
                                            <span>{{ $activity->name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-border/70 pt-3">
                        <span class="text-[11px] text-muted">Hiển thị nhóm nổi bật</span>
                        <a href="{{ route('categories.index') }}"
                           @click="closeMenu()"
                           class="inline-flex items-center gap-1 text-xs font-bold text-accent transition-colors hover:text-accent-hover">
                            <span>Mở trang Danh mục &amp; Phân loại</span>
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <a href="{{ route('news.index') }}"
               @if ($isNewsActive) aria-current="page" @endif
               @class([
                   'relative whitespace-nowrap py-1 text-[13px] tracking-wide transition-colors',
                   'font-bold text-accent' => $isNewsActive,
                   'font-semibold text-muted hover:text-accent' => ! $isNewsActive,
               ])>
                <span>Bài viết</span>
                @if ($isNewsActive)
                    <span aria-hidden="true" class="absolute inset-x-0 -bottom-0.5 h-0.5 rounded-full bg-accent"></span>
                @endif
            </a>
        </div>

        {{-- Phải: Tìm kiếm · Light/Dark · Tài khoản · Giỏ hàng --}}
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            {{-- Ô tìm kiếm gọn trên desktop rộng --}}
            <form action="{{ route('shoes.index') }}" method="GET" role="search" class="relative hidden xl:flex items-center">
                <svg class="pointer-events-none absolute left-3 h-4 w-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Tìm kiếm giày..."
                       aria-label="Tìm kiếm sản phẩm"
                       class="w-44 2xl:w-52 rounded-xl border border-border bg-surface/70 py-1.5 pl-9 pr-3 text-xs text-main placeholder:text-muted transition-colors focus:border-accent focus:bg-surface-card">
            </form>

            {{-- Nút mở tìm kiếm trên màn hình hẹp (< xl) --}}
            <button type="button"
                    class="btn-icon xl:hidden"
                    aria-label="Tìm kiếm"
                    :aria-expanded="searchOpen.toString()"
                    aria-controls="header-search-bar"
                    @click="searchOpen = !searchOpen; if (searchOpen) $nextTick(() => $refs.compactSearchInput?.focus())">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>

            {{-- Theme toggle --}}
            <div x-data="{ themeOpen: false }" class="relative">
                <button type="button" class="btn-icon" aria-label="Chế độ giao diện" :aria-expanded="themeOpen.toString()" @click="themeOpen = !themeOpen" @click.outside="themeOpen = false">
                    <span x-show="theme === 'light'" class="inline-flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                    </span>
                    <span x-show="theme === 'dark'" class="inline-flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </span>
                    <span x-show="theme === 'system'" class="inline-flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                    </span>
                </button>
                <div x-cloak x-show="themeOpen" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="absolute right-0 top-full z-50 mt-2 w-44 rounded-xl border border-border bg-surface-card p-1.5 text-xs shadow-elevated">
                    <button type="button" @click="setTheme('light'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'light' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                            <span>Sáng</span>
                        </span>
                        <span x-show="theme === 'light'" class="font-bold text-accent">✓</span>
                    </button>
                    <button type="button" @click="setTheme('dark'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'dark' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                            <span>Tối</span>
                        </span>
                        <span x-show="theme === 'dark'" class="font-bold text-accent">✓</span>
                    </button>
                    <button type="button" @click="setTheme('system'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'system' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                            <span>Theo hệ thống</span>
                        </span>
                        <span x-show="theme === 'system'" class="font-bold text-accent">✓</span>
                    </button>
                </div>
            </div>

            @auth
                <button type="button" class="btn-icon" aria-label="Tài khoản" @click="openPanel('account')" :aria-expanded="(panel === 'account').toString()" aria-controls="account-panel"><x-storefront.icon name="user"/></button>
            @else
                <a href="{{ route('login') }}" class="btn-icon" aria-label="Đăng nhập"><x-storefront.icon name="user"/></a>
            @endauth

            @unless ($isAdminAccount)
                <button type="button" class="btn-icon relative" aria-label="Giỏ hàng" @click="openPanel('cart')" :aria-expanded="(panel === 'cart').toString()" aria-controls="cart-panel"><x-storefront.icon name="cart"/><span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-accent-sub px-1 text-center text-xs font-bold leading-5 text-accent-sub-text" aria-label="Số đôi trong giỏ"></span></button>
            @endunless

            <button type="button" class="btn-icon inline md:hidden" aria-label="Menu" @click="openPanel('menu')" :aria-expanded="(panel === 'menu').toString()" aria-controls="mobile-menu"><x-storefront.icon name="menu"/></button>
        </div>
    </nav>

    {{-- Thanh tìm kiếm trượt xuống cho màn hình < xl --}}
    <div id="header-search-bar"
         x-cloak
         x-show="searchOpen"
         x-transition:enter="transition duration-150 ease-out"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         @click.outside="searchOpen = false"
         class="xl:hidden border-t border-border/70 bg-surface-card px-4 py-3 sm:px-6">
        <form action="{{ route('shoes.index') }}" method="GET" role="search" class="mx-auto flex max-w-7xl items-center gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input x-ref="compactSearchInput"
                       type="search"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Tìm kiếm tên giày hoặc mã màu..."
                       aria-label="Tìm kiếm sản phẩm"
                       class="input w-full py-2 pl-9 pr-3 text-sm">
            </div>
            <button type="submit" class="btn py-2 px-4 text-xs">Tìm</button>
            <button type="button" class="btn-icon" @click="searchOpen = false" aria-label="Đóng tìm kiếm">
                <x-storefront.icon name="close"/>
            </button>
        </form>
    </div>
</header>
<div x-data="{ previewReady: false }" x-init="$watch('hoverImage', () => { previewReady = false })"
     x-cloak x-show="hoverImage && previewReady"
     @click.window="hoverImage = null" @blur.window="hoverImage = null" @pagehide.window="hoverImage = null" @keydown.escape.window="hoverImage = null" @scroll.window.capture="hoverImage = null"
     class="pointer-events-none fixed left-0 top-0 z-20 hidden w-48 md:block"
     :style="{ transform: `translate(${pointerX + 20}px, ${pointerY + 20}px)` }" aria-hidden="true" data-navigation-preview>
    <template x-for="previewUrl in (hoverImage ? [hoverImage] : [])" :key="previewUrl">
        <img :src="previewUrl" alt="Ảnh xem trước sản phẩm" class="h-full w-full object-cover" loading="lazy" decoding="async"
             x-on:load="if (hoverImage === previewUrl && $el.naturalWidth > 0) previewReady = true"
             x-on:error="if (hoverImage === previewUrl) { previewReady = false; hoverImage = null }">
    </template>
</div>
