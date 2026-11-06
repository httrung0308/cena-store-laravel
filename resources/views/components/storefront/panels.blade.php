@php
    $isAdminAccount = auth()->user()?->hasRole(App\Enums\RoleSlug::Admin) ?? false;
@endphp
{{-- Click-catcher; the account dropdown keeps the page undimmed like a menu, other panels dim it. --}}
<div x-cloak x-show="panel" class="fixed inset-0 z-30" x-bind:class="panel === 'account' ? 'bg-transparent' : 'bg-black/50'" @click="closePanel()" x-transition.opacity></div>
<section id="mobile-menu" x-cloak x-show="panel === 'menu'" x-transition.opacity role="dialog" aria-modal="true" aria-label="Menu" class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-5 bg-white" @keydown.tab="trapFocus($event)">
    <button type="button" class="btn-icon absolute right-10 top-5" @click="closePanel()" aria-label="Close menu"><x-storefront.icon name="close"/></button>
    @foreach (['Men' => 'men', 'Women' => 'women', 'Kids' => 'kids', 'Unisex' => 'unisex'] as $label => $gender)
        <a href="{{ route('shoes.index', ['gender' => $gender]) }}" class="text-2xl font-semibold" @click="closePanel()">{{ $label }}</a>
    @endforeach
    <a href="{{ route('news.index') }}" class="text-2xl font-semibold" @click="closePanel()">News</a>
</section>
@unless ($isAdminAccount)
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
@else
<section id="cart-panel" x-cloak x-show="panel === 'cart'" class="fixed right-0 top-0 z-40 flex h-full w-full flex-col gap-5 bg-white p-5 sm:w-[32rem] sm:p-10 lg:w-[36rem]" role="dialog" aria-modal="true" aria-label="Tài khoản quản trị" @keydown.tab="trapFocus($event)"
    x-init="$store.cart.configure(@js(['show' => route('cart.show'), 'add' => route('cart.items.store'), 'item' => route('cart.items.update', ['item' => '__ID__']), 'clear' => route('cart.clear')]))">
    <button type="button" class="btn-icon self-end" @click="closePanel()" aria-label="Đóng thông báo"><x-storefront.icon name="close"/></button>
    <h2 class="text-xl font-semibold">Tài khoản quản trị</h2>
    <p>Tài khoản quản trị không dùng để mua sắm. Vui lòng đăng nhập bằng tài khoản khách hàng để đặt hàng.</p>
    <a href="{{ route('admin.dashboard') }}" class="btn text-center">Về trang quản trị</a>
</section>
@endunless
@auth
    @php
        $accountUser = auth()->user();
        $isBackOffice = $accountUser->roles()->whereIn('slug', ['admin', 'staff'])->exists();
        $menuItems = array_values(array_filter([
            $isAdminAccount ? null : ['href' => route('orders.index'), 'label' => 'Đơn hàng của tôi', 'icon' => 'M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0'],
            $isAdminAccount ? null : ['href' => route('account.addresses.index'), 'label' => 'Sổ địa chỉ', 'icon' => 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
            ['href' => route('account.settings'), 'label' => 'Cài đặt tài khoản', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'],
            $isBackOffice ? ['href' => route('admin.dashboard'), 'label' => 'Trang quản trị', 'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z'] : null,
        ]));
        $initial = mb_strtoupper(mb_substr(trim((string) $accountUser->full_name), 0, 1)) ?: 'N';
    @endphp
    {{-- Account dropdown anchored under the user icon (top right), like a social-network profile menu. --}}
    <section id="account-panel" x-cloak x-show="panel === 'account'"
        x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="fixed right-3 top-16 z-40 w-[calc(100%-1.5rem)] max-w-sm origin-top-right rounded-2xl bg-white p-3 text-black shadow-[0_12px_28px_rgba(0,0,0,0.2),0_2px_4px_rgba(0,0,0,0.1)] sm:right-10 xl:right-24 2xl:right-48"
        role="dialog" aria-modal="true" aria-labelledby="account-title" @keydown.tab="trapFocus($event)">
        <div class="rounded-xl p-2 shadow-[0_2px_12px_rgba(0,0,0,0.12)]">
            <a href="{{ route('account.index') }}" data-autofocus class="flex items-center gap-3 rounded-lg p-2 transition hover:bg-zinc-100 focus-visible:ring-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-black text-lg font-semibold text-white" aria-hidden="true">{{ $initial }}</span>
                <span class="min-w-0">
                    <span id="account-title" class="block truncate text-base font-semibold">{{ $accountUser->full_name }}</span>
                    <span class="block truncate text-xs text-zinc-500">{{ $accountUser->email }}</span>
                </span>
            </a>
            <div class="mx-2 my-2 h-px bg-zinc-200"></div>
            <a href="{{ route('account.index') }}" class="flex items-center justify-center gap-2 rounded-lg bg-zinc-100 px-3 py-2 text-sm font-semibold transition hover:bg-zinc-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                Tài khoản của tôi
            </a>
        </div>
        <nav class="mt-2 flex flex-col" aria-label="Tài khoản">
            @foreach ($menuItems as $item)
                <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-lg p-2 text-sm font-semibold transition hover:bg-zinc-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-200" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg></span>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    <svg class="h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg p-2 text-left text-sm font-semibold transition hover:bg-zinc-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-200" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></span>
                    Đăng xuất
                </button>
            </form>
        </nav>
        <p class="px-2 pt-2 text-[11px] text-zinc-400">Cena · bản demo đồ án</p>
    </section>
@endauth
<section id="notice-panel" x-cloak x-show="panel === 'notice'" x-transition.opacity class="fixed left-1/2 top-1/2 z-40 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 bg-white p-8" role="dialog" aria-modal="true" aria-labelledby="notice-title" @keydown.tab="trapFocus($event)">
    <button type="button" class="btn-icon absolute right-3 top-3" @click="closePanel()" aria-label="Close notification"><x-storefront.icon name="close"/></button>
    <h2 id="notice-title" class="mb-4 text-xl font-semibold" x-text="noticeTitle"></h2>
    <p class="text-sm leading-6">Our online shop is getting ready. Please check back soon.</p>
</section>
