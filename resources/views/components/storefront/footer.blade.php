{{-- Storefront footer: 4-column compact layout with verified store links and policies. --}}
@php
    $exploreLinks = [
        ['href' => route('shoes.index'), 'label' => 'Tất cả sản phẩm'],
        ['href' => route('categories.index'), 'label' => 'Danh mục & Phân loại'],
        ['href' => route('news.index'), 'label' => 'Bài viết'],
    ];

    $accountUser = auth()->user();
    $isAdminAccount = $accountUser?->hasRole(App\Enums\RoleSlug::Admin) ?? false;
    $isBackOffice = $accountUser !== null && $accountUser->roles()->whereIn('slug', ['admin', 'staff'])->exists();

    if ($accountUser !== null) {
        $accountLinks = array_values(array_filter([
            ['href' => route('account.index'), 'label' => 'Tài khoản của tôi'],
            $isAdminAccount ? null : ['href' => route('orders.index'), 'label' => 'Đơn hàng của tôi'],
            $isBackOffice ? ['href' => route('admin.dashboard'), 'label' => 'Trang quản trị'] : null,
        ]));
    } else {
        $accountLinks = [
            ['href' => route('login'), 'label' => 'Đăng nhập'],
            ['href' => route('register'), 'label' => 'Đăng ký'],
        ];
    }

    $freeShipThreshold = number_format((int) config('store.shipping.free_threshold'), 0, ',', '.').' ₫';
    $policyItems = [
        'Thanh toán khi nhận hàng (COD)',
        'Miễn phí vận chuyển đơn từ '.$freeShipThreshold,
        'Đổi size và bảo hành theo chính sách từng sản phẩm',
    ];
@endphp
<footer class="mt-14 w-full border-t-4 border-accent bg-[var(--footer-bg)] text-[var(--footer-text)] text-xs">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 py-9 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:py-10">
        <div>
            <a href="{{ route('home') }}" class="inline-block" aria-label="Cena — trang chủ">
                <img src="{{ asset('cena-logo-white.svg') }}" alt="Cena" class="h-9 w-auto">
            </a>
            <p class="mt-3 max-w-xs text-xs leading-relaxed text-[var(--footer-text)]/80 sm:text-sm">
                Thể thao là không ngừng bỏ cuộc. Giày chạy bộ, thời trang và thể thao cho cả gia đình.
            </p>
        </div>

        <nav aria-label="Khám phá">
            <h3 class="text-xs font-bold uppercase tracking-wider text-white">Khám phá</h3>
            <ul class="mt-3.5 space-y-2 text-xs text-[var(--footer-text)]/85 sm:text-sm">
                @foreach ($exploreLinks as $link)
                    <li>
                        <a href="{{ $link['href'] }}" class="transition-colors hover:text-white hover:underline">{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <nav aria-label="Tài khoản">
            <h3 class="text-xs font-bold uppercase tracking-wider text-white">Tài khoản</h3>
            <ul class="mt-3.5 space-y-2 text-xs text-[var(--footer-text)]/85 sm:text-sm">
                @foreach ($accountLinks as $link)
                    <li>
                        <a href="{{ $link['href'] }}" class="transition-colors hover:text-white hover:underline">{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-white">Chính sách &amp; Hỗ trợ</h3>
            <ul class="mt-3.5 space-y-2 text-xs leading-relaxed text-[var(--footer-text)]/85 sm:text-sm">
                @foreach ($policyItems as $policy)
                    <li>{{ $policy }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="border-t border-[var(--footer-border)]">
        <div class="mx-auto flex max-w-7xl flex-col gap-1.5 px-5 py-4 text-xs text-[var(--footer-text)]/75 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <p>© {{ now()->year }} Cena — Cena là cửa hàng độc lập, không phải website chính thức của Nike.</p>
            <p>Hình ảnh sản phẩm là ảnh minh họa.</p>
        </div>
    </div>
</footer>
