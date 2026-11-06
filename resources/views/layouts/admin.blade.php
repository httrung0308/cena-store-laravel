{{-- Back-office shell inside the storefront frame (navbar, panels, footer), styled like the management pages
     of the original store: eyebrow + large heading, rounded cards and pill buttons.
     Use @extends('layouts.admin') + @section('title') / @section('eyebrow') / @section('actions') / @section('content').
     Module links appear only once their route exists. --}}
@php
    $pageTitle = $title ?? trim($__env->yieldContent('title', 'Quản trị'));
    $sections = [
        ['route' => 'admin.dashboard', 'label' => 'Tổng quan'],
        ['route' => 'admin.reports.index', 'label' => 'Báo cáo'],
        ['route' => 'admin.categories.index', 'label' => 'Danh mục'],
        ['route' => 'admin.suppliers.index', 'label' => 'Nhà cung cấp'],
        ['route' => 'admin.products.index', 'label' => 'Sản phẩm'],
        ['route' => 'admin.stock-imports.index', 'label' => 'Nhập kho'],
        ['route' => 'admin.orders.index', 'label' => 'Đơn hàng'],
        ['route' => 'admin.reviews.index', 'label' => 'Đánh giá'],
    ];
    if (auth()->user()?->hasRole(App\Enums\RoleSlug::Admin)) {
        $sections[] = ['route' => 'admin.users.index', 'label' => 'Người dùng'];
        $sections[] = ['route' => 'admin.articles.index', 'label' => 'Bài viết'];
    }
    $onDashboard = request()->routeIs('admin.dashboard');
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — Quản trị Cena</title>
    <link rel="icon" href="{{ asset('cena-favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('cena-favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('cena-favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-montserrat bg-white text-black">
    <div id="storefront" x-data="storefrontShell" @cart-open.window="openPanel('cart')" @keydown.escape.window="closePanel()" @mousemove.window="pointerX = $event.clientX; pointerY = $event.clientY">
        <div :inert="panel !== null" :aria-hidden="panel !== null ? 'true' : null" class="flex min-h-screen flex-col">
            <x-storefront.navigation :animated="false"/>
            <main class="w-full flex-1 px-4 pb-10 pt-20 sm:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-8">
                    <div class="space-y-4">
                        @unless ($onDashboard)
                            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7"/></svg>
                                Về trang quản trị
                            </a>
                        @endunless
                        <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">@yield('eyebrow', 'Quản trị cửa hàng')</p>
                        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <h1 class="break-words text-4xl font-bold sm:text-5xl">{{ $pageTitle }}</h1>
                            <div class="flex flex-wrap gap-2">@yield('actions')</div>
                        </div>
                    </div>
                    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Menu quản trị">
                        @foreach ($sections as $section)
                            @php($active = request()->routeIs(str_replace('.index', '.*', $section['route'])))
                            @if (Route::has($section['route']))
                                <a href="{{ route($section['route']) }}" @class([
                                    'whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition',
                                    'bg-black text-white' => $active,
                                    'border border-zinc-200 hover:border-zinc-900' => ! $active,
                                ]) @if ($active) aria-current="page" @endif>{{ $section['label'] }}</a>
                            @else
                                <span class="whitespace-nowrap rounded-full border border-zinc-100 px-4 py-2 text-sm text-zinc-400" aria-disabled="true">{{ $section['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                    @if (session('status'))
                        <div role="status" class="rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div role="alert" class="rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-600">
                            <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div class="min-w-0">
                        {{ $slot ?? '' }}
                        @yield('content')
                    </div>
                </div>
            </main>
            <x-storefront.footer/>
        </div>
        <x-storefront.panels/>
    </div>
    @stack('scripts')
</body>
</html>
