@props(['animated' => true])
@php($genderLinks = ['Men' => ['men', 'men'], 'Women' => ['women', 'women'], 'Kids' => ['kids', 'kid'], 'Unisex' => ['unisex', 'unisex']])
<header @class(['legacy-navigation' => $animated, '-mb-10'])>
    <nav class="relative z-10 flex items-center justify-between px-10 py-5 transition-colors xl:px-24 2xl:px-48" aria-label="Main navigation">
        <h2><a href="{{ route('home') }}" class="text-xl font-bold">Cena</a></h2>
        <div class="hidden gap-6 px-24 md:flex">
            @foreach ($genderLinks as $label => [$gender, $image])
                <a href="{{ route('shoes.index', ['gender' => $gender]) }}" class="transition-all hover:underline" @mouseenter="hoverImage = '{{ asset('legacy/img/'.$image.'.jpg') }}'" @mouseleave="hoverImage = null" @focus="hoverImage = null">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex shrink-0 items-center">
            <button type="button" class="btn-icon" aria-label="Account" @click="openNotice('Account')"><x-storefront.icon name="user"/></button>
            <button type="button" class="btn-icon relative ml-3" aria-label="Cart" @click="openPanel('cart')" :aria-expanded="(panel === 'cart').toString()" aria-controls="cart-panel"><x-storefront.icon name="cart"/></button>
            <button type="button" class="btn-icon ml-3 inline md:hidden" aria-label="Menu" @click="openPanel('menu')" :aria-expanded="(panel === 'menu').toString()" aria-controls="mobile-menu"><x-storefront.icon name="menu"/></button>
        </div>
    </nav>
</header>
<div x-cloak x-show="hoverImage" class="pointer-events-none fixed left-0 top-0 z-20 w-48" :style="`transform: translate(${pointerX + 20}px, ${pointerY + 20}px)`" aria-hidden="true"><img :src="hoverImage" alt="" class="h-full w-full object-cover"></div>
