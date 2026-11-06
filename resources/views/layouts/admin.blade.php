{{-- Admin layout matching .ai/preview/mau-v4-cham-thep-dem/admin-san-pham.html --}}
@php
    $pageTitle = $title ?? trim($__env->yieldContent('title', 'Quản trị'));
    $sections = [
        ['route' => 'admin.dashboard', 'label' => 'Tổng quan', 'icon' => 'home'],
        ['route' => 'admin.products.index', 'label' => 'Sản phẩm', 'icon' => 'box'],
        ['route' => 'admin.categories.index', 'label' => 'Danh mục', 'icon' => 'tag'],
        ['route' => 'admin.suppliers.index', 'label' => 'Nhà cung cấp', 'icon' => 'truck'],
        ['route' => 'admin.stock-imports.index', 'label' => 'Nhập kho', 'icon' => 'archive'],
        ['route' => 'admin.orders.index', 'label' => 'Đơn hàng', 'icon' => 'cart'],
        ['route' => 'admin.reviews.index', 'label' => 'Đánh giá', 'icon' => 'star'],
        ['route' => 'admin.reports.index', 'label' => 'Báo cáo', 'icon' => 'chart'],
    ];
    if (auth()->user()?->hasRole(App\Enums\RoleSlug::Admin)) {
        $sections[] = ['route' => 'admin.users.index', 'label' => 'Người dùng', 'icon' => 'users'];
        $sections[] = ['route' => 'admin.articles.index', 'label' => 'Bài viết', 'icon' => 'document'];
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
<body class="selection:bg-accent selection:text-white min-h-screen flex flex-col font-sans bg-bg text-main antialiased" x-data="{ ...storefrontShell(), sidebarOpen: false }">

    <!-- Top Admin Bar -->
    <header class="bg-accent text-accent-text text-xs py-2 px-4 sm:px-6 flex items-center justify-between font-medium shrink-0 z-30">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-accent-sub shrink-0" aria-hidden="true"></span>
            <strong>Quản trị CENA</strong>
            <span class="hidden sm:inline text-white/80">· Hệ thống quản trị cửa hàng</span>
        </div>
        <div class="flex items-center gap-3 sm:gap-4 text-xs font-semibold">
            @if (auth()->check())
                <span class="text-white/70 hidden lg:inline">{{ auth()->user()->full_name }} ({{ auth()->user()->role?->label() ?? 'Nhân viên' }})</span>
            @endif
            {{-- Theme toggle --}}
            <div x-data="{ themeOpen: false }" class="relative">
                <button type="button" class="inline-flex items-center justify-center p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-colors" aria-label="Chế độ giao diện" :aria-expanded="themeOpen.toString()" @click="themeOpen = !themeOpen" @click.outside="themeOpen = false">
                    <span x-show="theme === 'light'" class="inline-flex items-center justify-center">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                    </span>
                    <span x-show="theme === 'dark'" class="inline-flex items-center justify-center">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </span>
                    <span x-show="theme === 'system'" class="inline-flex items-center justify-center">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                    </span>
                </button>
                <div x-cloak x-show="themeOpen" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="absolute right-0 top-full mt-2 w-44 rounded-xl border border-border bg-surface-card p-1.5 shadow-elevated z-50 text-xs text-main">
                    <button type="button" @click="setTheme('light'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'light' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                            <span>Sáng</span>
                        </span>
                        <span x-show="theme === 'light'" class="text-accent font-bold">✓</span>
                    </button>
                    <button type="button" @click="setTheme('dark'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'dark' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                            <span>Tối</span>
                        </span>
                        <span x-show="theme === 'dark'" class="text-accent font-bold">✓</span>
                    </button>
                    <button type="button" @click="setTheme('system'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'system' ? 'text-accent font-semibold' : 'text-main'">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                            <span>Theo hệ thống</span>
                        </span>
                        <span x-show="theme === 'system'" class="text-accent font-bold">✓</span>
                    </button>
                </div>
            </div>
            <a href="{{ route('home') }}" class="underline decoration-white/60 hover:decoration-white transition-opacity">← Xem Storefront</a>
        </div>
    </header>

    <!-- Mobile Top Navigation Bar (Hamburger) -->
    <div class="md:hidden bg-[#0f172a] text-slate-300 px-4 py-3 flex items-center justify-between border-b border-slate-800 shrink-0">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <img src="{{ asset('cena-logo-white.svg') }}" alt="Cena" class="h-5 w-auto">
            <span class="text-[10px] font-bold uppercase bg-accent text-accent-text px-2 py-0.5 rounded">Admin</span>
        </a>
        <button type="button" @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg bg-slate-800 text-slate-200 hover:text-white focus:outline-none" aria-label="Mở menu quản trị">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path x-show="!sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                <path x-show="sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Admin Wrapper -->
    <div class="flex-1 flex flex-col md:flex-row min-h-0">
        <!-- Backdrop for mobile drawer -->
        <div x-cloak x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/50 md:hidden" aria-hidden="true"></div>

        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'" class="fixed inset-y-0 left-0 z-50 md:static md:z-auto w-64 bg-[#0f172a] text-slate-300 p-6 flex flex-col justify-between shrink-0 border-r border-slate-800 transition-transform duration-200 ease-in-out md:transition-none">
            <div>
                <!-- Brand in Admin Sidebar (desktop) -->
                <div class="hidden md:flex items-center justify-between mb-8 pb-4 border-b border-slate-800">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                        <img src="{{ asset('cena-logo-white.svg') }}" alt="Cena" class="h-6 w-auto">
                        <span class="text-[10px] font-bold uppercase bg-accent text-accent-text px-2 py-0.5 rounded">Admin</span>
                    </a>
                </div>

                <!-- Admin Navigation Menu -->
                <nav class="space-y-1.5 text-xs font-semibold" aria-label="Main navigation">
                    @foreach ($sections as $section)
                        @php($active = request()->routeIs(str_replace('.index', '.*', $section['route'])))
                        @if (Route::has($section['route']))
                            <a href="{{ route($section['route']) }}" @class([
                                'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors',
                                'bg-accent text-accent-text font-bold shadow-md' => $active,
                                'hover:bg-slate-800 text-slate-400 hover:text-white' => ! $active,
                            ]) @if ($active) aria-current="page" @endif>
                                @if ($section['icon'] === 'home')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                @elseif ($section['icon'] === 'box')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @elseif ($section['icon'] === 'tag')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                @elseif ($section['icon'] === 'truck')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                @elseif ($section['icon'] === 'archive')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                @elseif ($section['icon'] === 'cart')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                @elseif ($section['icon'] === 'star')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                @elseif ($section['icon'] === 'chart')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                @elseif ($section['icon'] === 'users')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                @elseif ($section['icon'] === 'document')
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                @endif
                                <span>{{ $section['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>

            <div class="pt-6 border-t border-slate-800">
                <a href="{{ route('home') }}" class="text-xs text-slate-400 hover:text-white flex items-center gap-2 transition-colors">
                    <span>← Xem cửa hàng khách</span>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 p-4 sm:p-6 lg:p-10 min-w-0 flex flex-col justify-between">
            <div class="flex-1">
                <!-- Top bar / Heading -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-4 border-b border-border">
                    <div>
                        <span class="text-xs text-accent font-bold uppercase tracking-wider font-mono">@yield('eyebrow', 'QUẢN TRỊ CỬA HÀNG')</span>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-main mt-0.5 break-words">{{ $pageTitle }}</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @yield('actions')
                    </div>
                </div>

                <!-- Status / Error Flash Messages -->
                @if (session('status'))
                    <div role="status" class="mb-6 rounded-2xl border border-success/30 bg-success-bg p-4 text-xs font-semibold text-success shadow-sm">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="mb-6 rounded-2xl border border-error/30 bg-error-bg p-4 text-xs font-semibold text-error shadow-sm">
                        <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <!-- Content Slot -->
                <div class="min-w-0">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </div>

            <!-- Admin Simple Footer -->
            <footer class="mt-12 bg-surface-card border border-border rounded-2xl p-4 text-center text-xs text-muted font-medium shadow-sm">
                © 2026 CENA Store Admin — Cena là cửa hàng độc lập, không phải website chính thức của Nike.
            </footer>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
