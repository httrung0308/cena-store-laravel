<div x-cloak x-show="panel" class="fixed inset-0 z-30 bg-black/50" @click="closePanel()" x-transition.opacity></div>
<section id="mobile-menu" x-cloak x-show="panel === 'menu'" x-transition.opacity role="dialog" aria-modal="true" aria-label="Menu" class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-5 bg-white" @keydown.tab="trapFocus($event)">
    <button type="button" class="btn-icon absolute right-10 top-5" @click="closePanel()" aria-label="Close menu"><x-storefront.icon name="close"/></button>
    @foreach (['Men' => 'men', 'Women' => 'women', 'Kids' => 'kid', 'Unisex' => 'unisex'] as $label => $image)
        <a href="#collection-{{ $image }}" class="text-2xl font-semibold" @click="closePanel()">{{ $label }}</a>
    @endforeach
</section>
<section id="cart-panel" x-cloak x-show="panel === 'cart'" x-transition:enter="transition-transform duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition-transform duration-300" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed right-0 top-0 z-40 flex h-full w-full flex-col gap-7 bg-white p-5 sm:w-[32rem] sm:p-10 lg:w-[36rem]" role="dialog" aria-modal="true" aria-label="Cart" @keydown.tab="trapFocus($event)">
    <div class="flex w-full items-center justify-between"><div class="flex items-center gap-2"><h2 class="text-xl font-semibold">Cart (0)</h2><button type="button" class="text-sm hover:underline" @click="openNotice('Cart')">(Clear cart)</button></div><button type="button" class="btn-icon" @click="closePanel()" aria-label="Close cart"><x-storefront.icon name="close"/></button></div>
    <div class="overflow-overlay -mr-1 flex flex-1 flex-col gap-5 overflow-x-hidden p-1"></div>
    <div><div class="mb-2 flex w-full items-center justify-between"><h3 class="text-xl font-semibold">Total: 0₫</h3></div><button type="button" class="btn block w-full text-center" @click="openNotice('Checkout')">Login to Checkout</button></div>
</section>
<section id="notice-panel" x-cloak x-show="panel === 'notice'" x-transition.opacity class="fixed left-1/2 top-1/2 z-40 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 bg-white p-8" role="dialog" aria-modal="true" aria-labelledby="notice-title" @keydown.tab="trapFocus($event)">
    <button type="button" class="btn-icon absolute right-3 top-3" @click="closePanel()" aria-label="Close notification"><x-storefront.icon name="close"/></button>
    <h2 id="notice-title" class="mb-4 text-xl font-semibold" x-text="noticeTitle"></h2>
    <p class="text-sm leading-6">Our online shop is getting ready. Please check back soon.</p>
</section>
