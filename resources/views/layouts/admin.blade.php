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
<html lang="vi" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — Quản trị Cena</title>
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('cena-theme') || 'light';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="icon" href="{{ asset('cena-favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('cena-favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('cena-favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-sans bg-bg text-main antialiased selection:bg-accent selection:text-white">
    <div id="storefront" x-data="storefrontShell" @cart-open.window="openPanel('cart')" @keydown.escape.window="closePanel()" @mousemove.window.throttle.30ms="if (hoverImage) { pointerX = $event.clientX; pointerY = $event.clientY }">
        <div :inert="panel !== null" :aria-hidden="panel !== null ? 'true' : null" class="flex min-h-screen flex-col">
            <x-storefront.navigation :animated="false"/>
            <main class="w-full flex-1 px-4 pb-12 pt-8 sm:px-8 lg:px-12">
                <div class="mx-auto flex max-w-7xl flex-col gap-6">
                    <div class="space-y-3">
                        @unless ($onDashboard)
                            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7"/></svg>
                                <span>Về trang quản trị</span>
                            </a>
                        @endunless
                        <p class="text-xs font-bold uppercase tracking-wider text-muted">@yield('eyebrow', 'Quản trị cửa hàng')</p>
                        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <h1 class="break-words text-3xl font-bold font-serif text-main sm:text-4xl lg:text-5xl tracking-tight">{{ $pageTitle }}</h1>
                            <div class="flex flex-wrap gap-2">@yield('actions')</div>
                        </div>
                    </div>
                    <nav class="flex gap-2 overflow-x-auto pb-2 scrollbar-none" aria-label="Menu quản trị">
                        @foreach ($sections as $section)
                            @php($active = request()->routeIs(str_replace('.index', '.*', $section['route'])))
                            @if (Route::has($section['route']))
                                <a href="{{ route($section['route']) }}" @class([
                                    'whitespace-nowrap rounded-full px-4 py-2 text-xs font-semibold transition-colors duration-150',
                                    'bg-accent text-accent-text shadow-sm' => $active,
                                    'border border-border bg-surface-card text-muted hover:border-accent hover:text-main hover:bg-surface' => ! $active,
                                ]) @if ($active) aria-current="page" @endif>{{ $section['label'] }}</a>
                            @else
                                <span class="whitespace-nowrap rounded-full border border-border/50 bg-surface/40 px-4 py-2 text-xs text-muted/50" aria-disabled="true">{{ $section['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                    @if (session('status'))
                        <div role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-sm font-medium text-error-text shadow-subtle">
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
