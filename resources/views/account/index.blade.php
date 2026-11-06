@extends('layouts.store')

@section('title', 'Tài khoản của tôi')

@section('content')
    <section class="py-6 sm:py-10">
        <div class="mx-auto flex max-w-5xl flex-col gap-10">
            <div class="space-y-4">
                <h1 class="break-words text-3xl font-bold font-serif text-main sm:text-5xl tracking-tight">Xin chào {{ $user->full_name }}!</h1>
                <p class="text-base text-muted">{{ $isAdminAccount ? 'Quản lý thông tin tài khoản quản trị.' : 'Quản lý thông tin cá nhân, địa chỉ và theo dõi đơn hàng.' }}</p>
                @if (session('status'))
                    <p role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn px-6 py-2.5 text-sm">Đăng xuất</button>
                    </form>
                    @if ($isAdminAccount)
                        <a href="{{ route('admin.dashboard') }}" class="btn-secondary px-6 py-2.5 text-sm">Về trang quản trị</a>
                    @else
                        <a href="{{ route('shoes.index') }}" class="btn-secondary px-6 py-2.5 text-sm">Xem danh sách các sản phẩm</a>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <article class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-7 flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-bold font-serif text-main">Trang cá nhân</h2>
                        <div class="mt-4 space-y-2 text-sm text-muted">
                            <p>Tên: <span class="font-medium text-main">{{ $user->full_name }}</span></p>
                            <p class="break-all">Email: <span class="font-medium text-main">{{ $user->email }}</span></p>
                            @if ($user->phone)
                                <p>Điện thoại: <span class="font-medium text-main">{{ $user->phone }}</span></p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-border/60">
                        <a href="{{ route('account.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Sửa thông tin / Đổi mật khẩu →</a>
                    </div>
                </article>

                @if ($isAdminAccount)
                    <article class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-7 flex flex-col justify-between">
                        <div>
                            <h2 class="text-lg font-bold font-serif text-main">Quản trị cửa hàng</h2>
                            <p class="mt-4 text-sm text-muted leading-relaxed">Tài khoản này dùng để quản lý cửa hàng. Khi cần mua sắm, hãy đăng nhập bằng tài khoản khách hàng.</p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-border/60">
                            <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Quản lý đơn hàng của khách →</a>
                        </div>
                    </article>
                @else
                    <article class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-7 flex flex-col justify-between">
                        <div>
                            <h2 class="text-lg font-bold font-serif text-main">Địa chỉ đã lưu</h2>
                            <div class="mt-4 min-h-[80px] text-sm text-muted leading-relaxed">
                                @if ($defaultAddress)
                                    <p class="font-medium text-main">{{ $defaultAddress->recipient_name }} · {{ $defaultAddress->phone }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ $defaultAddress->street_address }}, {{ $defaultAddress->ward_name_snapshot }}, {{ $defaultAddress->province_name_snapshot }}</p>
                                    <span class="mt-2 inline-block rounded-full bg-success-bg border border-success/30 px-2.5 py-0.5 text-xs font-semibold text-success-text">(Mặc định)</span>
                                    @if ($addressCount > 1)<p class="mt-2 text-xs text-muted">và {{ $addressCount - 1 }} địa chỉ khác</p>@endif
                                @else
                                    <p class="text-muted">Chưa có địa chỉ nào</p>
                                @endif
                            </div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-border/60">
                            <a href="{{ route('account.addresses.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Quản lý địa chỉ →</a>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-7 md:col-span-2">
                        <div class="flex items-center justify-between pb-3 border-b border-border/60">
                            <h2 class="text-lg font-bold font-serif text-main">Các đơn hàng gần đây</h2>
                            <a href="{{ route('orders.index') }}" class="text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Xem tất cả đơn hàng →</a>
                        </div>
                        <div class="mt-5 space-y-3">
                            @forelse ($recentOrders as $order)
                                <a href="{{ route('orders.show', $order->order_code) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border/70 bg-surface/30 p-4 transition-colors hover:border-accent/40 hover:bg-surface/60">
                                    <div class="min-w-0">
                                        <p class="break-all font-semibold text-main text-sm">{{ $order->order_code }}</p>
                                        <p class="mt-0.5 text-xs text-muted">{{ $order->created_at->setTimezone((string) config('store.business_timezone'))->format('d/m/Y') }} · {{ $order->items->sum('quantity') }} đôi</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <x-orders.status-badge :status="$order->order_status" />
                                        <span class="font-bold text-accent text-sm sm:text-base">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</span>
                                    </div>
                                </a>
                            @empty
                                <div class="py-8 text-center text-sm text-muted">Chưa có đơn hàng nào</div>
                            @endforelse
                        </div>
                    </article>
                @endif
            </div>
        </div>
    </section>
@endsection
