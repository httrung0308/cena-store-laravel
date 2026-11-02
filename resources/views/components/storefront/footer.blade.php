{{-- Storefront footer: brand + contact, shop links, account links, store policies, demo disclaimer. --}}
@php
    $shopLinks = [
        ['href' => route('shoes.index', ['gender' => 'men']), 'label' => 'Giày nam'],
        ['href' => route('shoes.index', ['gender' => 'women']), 'label' => 'Giày nữ'],
        ['href' => route('shoes.index', ['gender' => 'kids']), 'label' => 'Giày trẻ em'],
        ['href' => route('shoes.index', ['gender' => 'unisex']), 'label' => 'Unisex'],
        ['href' => route('shoes.index'), 'label' => 'Tất cả sản phẩm'],
    ];
    $accountLinks = auth()->check()
        ? [['href' => route('account.index'), 'label' => 'Tài khoản của tôi'], ['href' => route('account.addresses.index'), 'label' => 'Sổ địa chỉ']]
        : [['href' => route('login'), 'label' => 'Đăng nhập'], ['href' => route('register'), 'label' => 'Đăng ký']];
    $policies = ['Thanh toán khi nhận hàng (COD)', 'Miễn phí vận chuyển mọi đơn', 'Đổi size trong 7 ngày', 'Bảo hành keo, đế 6 tháng'];
    $icon = 'h-4 w-4 shrink-0';
@endphp
<footer class="mt-20 w-full bg-black text-zinc-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:grid-cols-2 sm:px-10 lg:grid-cols-4">
        <div>
            <a href="{{ route('home') }}" class="text-2xl font-bold text-white">Nike</a>
            <p class="mt-3 text-sm leading-relaxed">Sport, the smart choice. Giày chạy bộ, thời trang và thể thao cho cả gia đình.</p>
            <ul class="mt-5 space-y-2 text-sm">
                <li class="flex items-center gap-2"><svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> 12 Nguyễn Văn Bảo, Gò Vấp, TP. Hồ Chí Minh</li>
                <li class="flex items-center gap-2"><svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg> 1900 0000</li>
                <li class="flex items-center gap-2"><svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="m22 6-10 7L2 6"/></svg> hotro@nikestore.test</li>
            </ul>
        </div>
        <nav aria-label="Mua sắm">
            <h3 class="font-semibold uppercase tracking-wide text-white">Mua sắm</h3>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($shopLinks as $link)
                    <li><a href="{{ $link['href'] }}" class="transition hover:text-white hover:underline">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <nav aria-label="Tài khoản">
            <h3 class="font-semibold uppercase tracking-wide text-white">Tài khoản</h3>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($accountLinks as $link)
                    <li><a href="{{ $link['href'] }}" class="transition hover:text-white hover:underline">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div>
            <h3 class="font-semibold uppercase tracking-wide text-white">Chính sách</h3>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($policies as $policy)
                    <li class="flex items-center gap-2"><svg class="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.27 6.96 8.73 5.05 8.73-5.05M12 22.08V12"/></svg> {{ $policy }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="border-t border-zinc-800">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-5 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-10">
            <p>© {{ now()->year }} Nike Store — bản demo đồ án, không phải website chính thức của Nike.</p>
            <p>Hình ảnh sản phẩm là ảnh minh họa.</p>
        </div>
    </div>
</footer>
