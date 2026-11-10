@props(['animated' => true])
@php
    $genderLinks = ['Giày Nam' => ['men', 'men'], 'Giày Nữ' => ['women', 'women'], 'Trẻ Em' => ['kids', 'kid'], 'Unisex' => ['unisex', 'unisex']];
    $isAdminAccount = auth()->user()?->hasRole(App\Enums\RoleSlug::Admin) ?? false;
    $isCustomerAccount = auth()->user()?->hasRole(App\Enums\RoleSlug::Customer) && ! $isAdminAccount;
    $unreadNotificationCount = $isCustomerAccount ? auth()->user()->storeNotifications()->whereNull('read_at')->count() : 0;
@endphp
<header @class(['sticky top-0 z-40 border-b border-border bg-surface-card shadow-subtle transition-colors duration-150', 'legacy-navigation' => $animated])>
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-8 lg:px-10" aria-label="Main navigation">
        <h2>
            <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="Cena — trang chủ">
                <img src="{{ asset('cena-logo.svg') }}" alt="Cena" class="theme-logo-light h-8 w-auto sm:h-9">
                <img src="{{ asset('cena-logo-white.svg') }}" alt="Cena" class="theme-logo-dark h-8 w-auto sm:h-9">
            </a>
        </h2>
        <div class="hidden items-center gap-7 md:flex" @mouseleave="hoverImage = null">
            @foreach ($genderLinks as $label => [$gender, $image])
                <a href="{{ route('shoes.index', ['gender' => $gender]) }}" class="text-[13px] font-semibold tracking-wide text-muted transition-colors hover:text-accent" @mouseenter="hoverImage = '{{ asset('legacy/img/'.$image.'.jpg') }}'" @mouseleave="hoverImage = null" @focus="hoverImage = null">{{ $label }}</a>
            @endforeach
            <a href="{{ route('shoes.index') }}" class="text-[13px] font-bold text-accent transition-colors hover:text-accent-hover flex items-center gap-1.5">
                <span>Bộ Sưu Tập</span>
                <span class="w-1.5 h-1.5 rounded-full bg-accent-sub" aria-hidden="true"></span>
            </a>
            <a href="{{ route('news.index') }}" class="text-[13px] font-semibold tracking-wide text-muted transition-colors hover:text-accent">News</a>
        </div>
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
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
                <div x-cloak x-show="themeOpen" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="absolute right-0 top-full mt-2 w-44 rounded-xl border border-border bg-surface-card p-1.5 shadow-elevated z-50 text-xs">
                    <button type="button" @click="setTheme('light'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'light' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                            <span>Sáng</span>
                        </span>
                        <span x-show="theme === 'light'" class="text-accent font-bold">✓</span>
                    </button>
                    <button type="button" @click="setTheme('dark'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'dark' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                            <span>Tối</span>
                        </span>
                        <span x-show="theme === 'dark'" class="text-accent font-bold">✓</span>
                    </button>
                    <button type="button" @click="setTheme('system'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'system' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                            <span>Theo hệ thống</span>
                        </span>
                        <span x-show="theme === 'system'" class="text-accent font-bold">✓</span>
                    </button>
                </div>
            </div>
            @auth
                <button type="button" class="btn-icon" aria-label="Tài khoản" @click="openPanel('account')" :aria-expanded="(panel === 'account').toString()" aria-controls="account-panel"><x-storefront.icon name="user"/></button>
            @else
                <a href="{{ route('login') }}" class="btn-icon" aria-label="Đăng nhập"><x-storefront.icon name="user"/></a>
            @endauth
            @if ($isCustomerAccount)
                <a href="{{ route('notifications.index') }}" class="btn-icon relative" aria-label="Thông báo{{ $unreadNotificationCount > 0 ? ', '.$unreadNotificationCount.' chưa đọc' : '' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    @if ($unreadNotificationCount > 0)
                        <span class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-accent-sub px-1 text-center text-xs font-bold leading-5 text-accent-sub-text">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    @endif
                </a>
            @endif
            @unless ($isAdminAccount)
            <button type="button" class="btn-icon relative" aria-label="Giỏ hàng" @click="openPanel('cart')" :aria-expanded="(panel === 'cart').toString()" aria-controls="cart-panel"><x-storefront.icon name="cart"/><span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-accent-sub px-1 text-center text-xs font-bold leading-5 text-accent-sub-text" aria-label="Số đôi trong giỏ"></span></button>
            @endunless
            <button type="button" class="btn-icon inline md:hidden" aria-label="Menu" @click="openPanel('menu')" :aria-expanded="(panel === 'menu').toString()" aria-controls="mobile-menu"><x-storefront.icon name="menu"/></button>
        </div>
    </nav>
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
