@extends('layouts.store')

@section('title', 'Tài khoản của tôi')

@section('content')
    <section class="py-10 sm:px-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-10">
            <div class="space-y-4">
                <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Tài khoản</p>
                <h1 class="break-words text-4xl font-bold sm:text-5xl">Xin chào {{ $user->full_name }}!</h1>
                <p class="text-base text-zinc-500">Quản lý thông tin cá nhân, địa chỉ và theo dõi đơn hàng.</p>
                @if (session('status'))
                    <p role="status" class="rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</p>
                @endif
                <div class="flex flex-wrap gap-4">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn rounded-full px-6 py-3">Đăng xuất</button>
                    </form>
                    <a href="{{ route('shoes.index') }}" class="rounded-full border border-zinc-900 px-6 py-3 font-semibold transition hover:bg-zinc-900 hover:text-white">Xem danh sách các sản phẩm</a>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <article class="rounded-3xl border border-zinc-200 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Trang cá nhân</h2>
                    <p class="mt-3 text-sm text-zinc-500">Tên: <span class="text-zinc-900">{{ $user->full_name }}</span></p>
                    <p class="break-all text-sm text-zinc-500">Email: <span class="text-zinc-900">{{ $user->email }}</span></p>
                    @if ($user->phone)
                        <p class="text-sm text-zinc-500">Điện thoại: <span class="text-zinc-900">{{ $user->phone }}</span></p>
                    @endif
                    <a href="{{ route('account.settings') }}" class="mt-4 inline-block rounded-full border border-zinc-200 px-4 py-2 text-sm font-semibold transition hover:border-zinc-900 hover:bg-zinc-900 hover:text-white">Sửa thông tin / Đổi mật khẩu</a>
                </article>

                <article class="rounded-3xl border border-zinc-200 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Địa chỉ đã lưu</h2>
                    <div class="mt-3 min-h-[80px] text-sm text-zinc-500">
                        @if ($defaultAddress)
                            <p>{{ $defaultAddress->recipient_name }}, {{ $defaultAddress->phone }}</p>
                            <p>{{ $defaultAddress->street_address }}, {{ $defaultAddress->ward_name_snapshot }}, {{ $defaultAddress->province_name_snapshot }}</p>
                            <span class="mt-1 inline-block text-xs font-semibold text-green-600">(Mặc định)</span>
                            @if ($addressCount > 1)<p class="mt-1 text-xs">và {{ $addressCount - 1 }} địa chỉ khác</p>@endif
                        @else
                            <p>Chưa có địa chỉ nào</p>
                        @endif
                    </div>
                    <a href="{{ route('account.addresses.index') }}" class="mt-4 inline-block rounded-full border border-zinc-200 px-4 py-2 text-sm font-semibold transition hover:border-zinc-900 hover:bg-zinc-900 hover:text-white">Quản lý địa chỉ</a>
                </article>

                <article class="rounded-3xl border border-zinc-200 p-6 shadow-sm md:col-span-2">
                    <h2 class="text-lg font-semibold">Các đơn hàng gần đây</h2>
                    <div class="mt-5 space-y-3">
                        @forelse ($recentOrders as $order)
                            <a href="{{ route('orders.show', $order->order_code) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 p-4 transition hover:border-zinc-900">
                                <span class="min-w-0"><span class="block break-all font-semibold">{{ $order->order_code }}</span><span class="text-sm text-zinc-500">{{ $order->created_at->setTimezone((string) config('store.business_timezone'))->format('d/m/Y') }} · {{ $order->items->sum('quantity') }} đôi</span></span>
                                <span class="flex items-center gap-3"><x-orders.status-badge :status="$order->order_status" /><span class="font-semibold">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</span></span>
                            </a>
                        @empty
                            <p class="text-sm text-zinc-500">Chưa có đơn hàng nào</p>
                        @endforelse
                    </div>
                    <a href="{{ route('orders.index') }}" class="mt-4 inline-block rounded-full border border-zinc-200 px-4 py-2 text-sm font-semibold transition hover:border-zinc-900 hover:bg-zinc-900 hover:text-white">Xem tất cả đơn hàng</a>
                </article>
            </div>
        </div>
    </section>
@endsection
