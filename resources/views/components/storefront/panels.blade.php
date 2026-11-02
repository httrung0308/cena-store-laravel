<div x-cloak x-show="panel" class="fixed inset-0 z-30 bg-black/50" @click="closePanel()" x-transition.opacity></div>
<section id="mobile-menu" x-cloak x-show="panel === 'menu'" x-transition.opacity role="dialog" aria-modal="true" aria-label="Menu" class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-5 bg-white" @keydown.tab="trapFocus($event)">
    <button type="button" class="btn-icon absolute right-10 top-5" @click="closePanel()" aria-label="Close menu"><x-storefront.icon name="close"/></button>
    @foreach (['Men' => 'men', 'Women' => 'women', 'Kids' => 'kids', 'Unisex' => 'unisex'] as $label => $gender)
        <a href="{{ route('shoes.index', ['gender' => $gender]) }}" class="text-2xl font-semibold" @click="closePanel()">{{ $label }}</a>
    @endforeach
</section>
<section id="cart-panel" x-cloak x-show="panel === 'cart'" x-transition:enter="transition-transform duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition-transform duration-300" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed right-0 top-0 z-40 flex h-full w-full flex-col gap-5 bg-white p-5 sm:w-[32rem] sm:p-10 lg:w-[36rem]" role="dialog" aria-modal="true" aria-label="Giỏ hàng" @keydown.tab="trapFocus($event)"
    x-init="$store.cart.configure(@js(['show' => route('cart.show'), 'add' => route('cart.items.store'), 'item' => route('cart.items.update', ['item' => '__ID__']), 'clear' => route('cart.clear')])); $store.cart.load()">
    <div class="flex w-full items-center justify-between">
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-semibold">Giỏ hàng (<span x-text="$store.cart.count">0</span>)</h2>
            <button type="button" class="text-sm hover:underline disabled:opacity-40" x-show="$store.cart.items.length > 0" x-bind:disabled="$store.cart.isBusy()" @click="$store.cart.clear()">(Xóa tất cả)</button>
        </div>
        <button type="button" class="btn-icon" @click="closePanel()" aria-label="Đóng giỏ hàng"><x-storefront.icon name="close"/></button>
    </div>

    <div aria-live="polite" class="space-y-2 text-sm">
        <p x-show="$store.cart.error" x-text="$store.cart.error" role="alert" class="border border-red-300 bg-red-50 px-3 py-2 text-red-700"></p>
        <template x-for="notice in $store.cart.notices" :key="notice"><p x-text="notice" class="border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800"></p></template>
    </div>

    <ul class="overflow-overlay -mr-1 flex flex-1 flex-col gap-5 overflow-x-hidden p-1" aria-label="Sản phẩm trong giỏ">
        <template x-for="item in $store.cart.items" :key="item.id">
            <li class="flex gap-4" x-bind:aria-busy="$store.cart.isBusy('item-' + item.id).toString()">
                <div class="h-24 w-24 shrink-0 bg-zinc-100">
                    <img x-show="item.thumbnail_url" x-bind:src="item.thumbnail_url" alt="" class="h-full w-full object-cover" loading="lazy">
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                    <template x-if="item.product_url"><a x-bind:href="item.product_url" class="truncate font-semibold hover:underline" x-text="item.product_name"></a></template>
                    <template x-if="!item.product_url"><p class="truncate font-semibold" x-text="item.product_name"></p></template>
                    <p class="text-zinc-600"><span x-text="item.color_name"></span> · Size <span x-text="item.size_value"></span></p>
                    <p x-text="$store.cart.price(item.unit_price)"></p>
                    <p x-show="item.notice" x-text="item.notice" class="text-xs text-red-600"></p>
                    <div class="mt-1 flex items-center gap-3">
                        <div class="flex items-center border border-zinc-300">
                            <button type="button" class="px-2 py-1 disabled:opacity-30" aria-label="Giảm số lượng" x-bind:disabled="$store.cart.isBusy() || item.quantity <= 1" @click="$store.cart.update(item, item.quantity - 1)">−</button>
                            <span class="w-8 text-center" x-text="item.quantity"></span>
                            <button type="button" class="px-2 py-1 disabled:opacity-30" aria-label="Tăng số lượng" x-bind:disabled="$store.cart.isBusy() || item.quantity >= item.max_quantity" @click="$store.cart.update(item, item.quantity + 1)">+</button>
                        </div>
                        <button type="button" class="text-xs underline disabled:opacity-30" x-bind:disabled="$store.cart.isBusy()" @click="$store.cart.remove(item)">Xóa</button>
                    </div>
                </div>
                <p class="shrink-0 text-sm font-semibold" x-text="$store.cart.price(item.line_total)"></p>
            </li>
        </template>
        <li x-show="$store.cart.loaded && $store.cart.items.length === 0" class="py-10 text-center text-sm text-zinc-500">
            Giỏ hàng đang trống. <a href="{{ route('shoes.index') }}" class="underline">Xem sản phẩm</a>
        </li>
    </ul>

    <div class="space-y-2 border-t border-zinc-200 pt-4">
        <div class="flex w-full items-center justify-between"><h3 class="text-xl font-semibold">Tạm tính</h3><p class="text-xl font-semibold" x-text="$store.cart.price($store.cart.subtotal)">0 ₫</p></div>
        <p class="text-xs text-zinc-500" x-text="$store.cart.checkoutNote"></p>
        @auth
            <a href="{{ route('checkout.show') }}" class="btn block w-full text-center" x-bind:class="$store.cart.count === 0 && 'pointer-events-none opacity-50'" x-bind:aria-disabled="($store.cart.count === 0).toString()">Đặt hàng</a>
        @else
            <a href="{{ route('checkout.show') }}" class="btn block w-full text-center">Đăng nhập để đặt hàng</a>
        @endauth
    </div>
</section>
@auth
    <section id="account-panel" x-cloak x-show="panel === 'account'" x-transition.opacity class="fixed right-4 top-20 z-40 w-[calc(100%-2rem)] max-w-xs bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="account-title" @keydown.tab="trapFocus($event)">
        <button type="button" class="btn-icon absolute right-3 top-3" @click="closePanel()" aria-label="Đóng"><x-storefront.icon name="close"/></button>
        <h2 id="account-title" class="pr-8 text-lg font-semibold">{{ auth()->user()->full_name }}</h2>
        <p class="truncate text-sm text-zinc-500">{{ auth()->user()->email }}</p>
        <nav class="mt-5 flex flex-col gap-3 text-sm" aria-label="Tài khoản">
            <a href="{{ route('account.index') }}" class="hover:underline">Tài khoản của tôi</a>
            <a href="{{ route('orders.index') }}" class="hover:underline">Đơn hàng của tôi</a>
            <a href="{{ route('account.addresses.index') }}" class="hover:underline">Sổ địa chỉ</a>
            @if (auth()->user()->roles()->whereIn('slug', ['admin', 'staff'])->exists())
                <a href="{{ route('admin.dashboard') }}" class="hover:underline">Trang quản trị</a>
            @endif
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="btn w-full">Đăng xuất</button>
        </form>
    </section>
@endauth
<section id="notice-panel" x-cloak x-show="panel === 'notice'" x-transition.opacity class="fixed left-1/2 top-1/2 z-40 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 bg-white p-8" role="dialog" aria-modal="true" aria-labelledby="notice-title" @keydown.tab="trapFocus($event)">
    <button type="button" class="btn-icon absolute right-3 top-3" @click="closePanel()" aria-label="Close notification"><x-storefront.icon name="close"/></button>
    <h2 id="notice-title" class="mb-4 text-xl font-semibold" x-text="noticeTitle"></h2>
    <p class="text-sm leading-6">Our online shop is getting ready. Please check back soon.</p>
</section>
