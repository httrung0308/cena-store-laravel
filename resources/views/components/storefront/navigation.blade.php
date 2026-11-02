@props(['animated' => true])
@php
    $genderLinks = ['Men' => ['men', 'men'], 'Women' => ['women', 'women'], 'Kids' => ['kids', 'kid'], 'Unisex' => ['unisex', 'unisex']];
    $isAdminAccount = auth()->user()?->hasRole(App\Enums\RoleSlug::Admin) ?? false;
@endphp
<header @class(['legacy-navigation' => $animated, '-mb-10'])>
    <nav class="relative z-10 flex items-center justify-between px-10 py-5 transition-colors xl:px-24 2xl:px-48" aria-label="Main navigation">
        <h2><a href="{{ route('home') }}" class="text-xl font-bold">Nike</a></h2>
        <div class="hidden gap-6 px-24 md:flex" @mouseleave="hoverImage = null">
            @foreach ($genderLinks as $label => [$gender, $image])
                <a href="{{ route('shoes.index', ['gender' => $gender]) }}" class="transition-all hover:underline" @mouseenter="hoverImage = '{{ asset('legacy/img/'.$image.'.jpg') }}'" @mouseleave="hoverImage = null" @focus="hoverImage = null">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex shrink-0 items-center">
            @auth
                <button type="button" class="btn-icon" aria-label="Tài khoản" @click="openPanel('account')" :aria-expanded="(panel === 'account').toString()" aria-controls="account-panel"><x-storefront.icon name="user"/></button>
            @else
                <a href="{{ route('login') }}" class="btn-icon" aria-label="Đăng nhập"><x-storefront.icon name="user"/></a>
            @endauth
            @unless ($isAdminAccount)
            <button type="button" class="btn-icon relative ml-3" aria-label="Cart" @click="openPanel('cart')" :aria-expanded="(panel === 'cart').toString()" aria-controls="cart-panel"><x-storefront.icon name="cart"/><span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-2 -top-2 min-w-[1.25rem] rounded-full bg-black px-1 text-center text-xs font-semibold leading-5 text-white" aria-label="Số đôi trong giỏ"></span></button>
            @endunless
            <button type="button" class="btn-icon ml-3 inline md:hidden" aria-label="Menu" @click="openPanel('menu')" :aria-expanded="(panel === 'menu').toString()" aria-controls="mobile-menu"><x-storefront.icon name="menu"/></button>
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
