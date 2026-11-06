@props(['animated' => true])
@php
    $genderLinks = ['Men' => ['men', 'men'], 'Women' => ['women', 'women'], 'Kids' => ['kids', 'kid'], 'Unisex' => ['unisex', 'unisex']];
    $isAdminAccount = auth()->user()?->hasRole(App\Enums\RoleSlug::Admin) ?? false;
@endphp
<header @class(['sticky top-0 z-40 border-b border-border bg-surface-card shadow-subtle transition-colors duration-150', 'legacy-navigation' => $animated])>
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-8 lg:px-10" aria-label="Main navigation">
        <h2>
            <a href="{{ route('home') }}" class="flex items-center gap-2" aria-label="Cena — trang chủ">
                <img src="{{ asset('cena-logo.svg') }}" alt="Cena" class="h-8 w-auto sm:h-9">
                <span class="text-[11px] font-serif italic text-accent/70 tracking-widest hidden sm:inline">· 履物</span>
            </a>
        </h2>
        <div class="hidden items-center gap-8 md:flex" @mouseleave="hoverImage = null">
            @foreach ($genderLinks as $label => [$gender, $image])
                <a href="{{ route('shoes.index', ['gender' => $gender]) }}" class="text-xs font-semibold uppercase tracking-wider text-muted transition-colors hover:text-accent" @mouseenter="hoverImage = '{{ asset('legacy/img/'.$image.'.jpg') }}'" @mouseleave="hoverImage = null" @focus="hoverImage = null">{{ $label }}</a>
            @endforeach
            <a href="{{ route('news.index') }}" class="text-xs font-semibold uppercase tracking-wider text-muted transition-colors hover:text-accent">News</a>
        </div>
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            @auth
                <button type="button" class="btn-icon" aria-label="Tài khoản" @click="openPanel('account')" :aria-expanded="(panel === 'account').toString()" aria-controls="account-panel"><x-storefront.icon name="user"/></button>
            @else
                <a href="{{ route('login') }}" class="btn-icon" aria-label="Đăng nhập"><x-storefront.icon name="user"/></a>
            @endauth
            @unless ($isAdminAccount)
            <button type="button" class="btn-icon relative" aria-label="Cart" @click="openPanel('cart')" :aria-expanded="(panel === 'cart').toString()" aria-controls="cart-panel"><x-storefront.icon name="cart"/><span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-1 -top-1 min-w-[1.25rem] rounded-full bg-accent px-1 text-center text-xs font-semibold leading-5 text-accent-text" aria-label="Số đôi trong giỏ"></span></button>
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
        <img :src="previewUrl" alt="" class="h-full w-full object-cover"
             x-on:load="if (hoverImage === previewUrl && $el.naturalWidth > 0) previewReady = true"
             x-on:error="if (hoverImage === previewUrl) { previewReady = false; hoverImage = null }">
    </template>
</div>
