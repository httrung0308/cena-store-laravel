{{-- Full-screen sign-in / sign-up shell: Cena branded background, no navbar or footer, Cena logo back to home.
     Sections: title, heading, subheading (optional), switch (link to the other form), content (the form). --}}
@php($pageTitle = $title ?? trim($__env->yieldContent('title', 'Cena')))
<!doctype html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — Cena</title>
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
</head>
<body class="font-sans bg-bg text-main antialiased selection:bg-accent selection:text-white min-h-screen relative flex flex-col justify-between" x-data="storefrontShell">
    <x-auth.background />
    
    {{-- Top header bar with Cena logo and Theme Switcher --}}
    <header class="relative z-10 flex w-full items-center justify-between px-6 py-6 sm:px-10">
        <a href="{{ route('home') }}" class="flex items-center gap-2 focus-visible:ring-4 rounded-lg" aria-label="Về trang chủ">
            <img src="{{ asset('cena-logo.svg') }}" alt="Cena" class="theme-logo-light h-9 w-auto">
            <img src="{{ asset('cena-logo-white.svg') }}" alt="Cena" class="theme-logo-dark h-9 w-auto">
        </a>
        <div x-data="{ themeOpen: false }" class="relative">
            <button type="button" class="btn-icon" aria-label="Chế độ giao diện" :aria-expanded="themeOpen.toString()" @click="themeOpen = !themeOpen" @click.outside="themeOpen = false">
                <span x-show="theme === 'light'" class="inline-flex items-center justify-center">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                </span>
                <span x-show="theme === 'dark'" class="inline-flex items-center justify-center">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </span>
                <span x-show="theme === 'system'" class="inline-flex items-center justify-center">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                </span>
            </button>
            <div x-cloak x-show="themeOpen" x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="absolute right-0 top-full mt-2 w-44 rounded-xl border border-border bg-surface-card p-1.5 shadow-elevated z-50 text-xs">
                <button type="button" @click="setTheme('light'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'light' ? 'text-accent font-semibold' : 'text-main'">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        <span>Sáng</span>
                    </span>
                    <span x-show="theme === 'light'" class="text-accent font-bold">✓</span>
                </button>
                <button type="button" @click="setTheme('dark'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'dark' ? 'text-accent font-semibold' : 'text-main'">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                        <span>Tối</span>
                    </span>
                    <span x-show="theme === 'dark'" class="text-accent font-bold">✓</span>
                </button>
                <button type="button" @click="setTheme('system'); themeOpen = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left font-medium transition hover:bg-surface" :class="theme === 'system' ? 'text-accent font-semibold' : 'text-main'">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                        <span>Theo hệ thống</span>
                    </span>
                    <span x-show="theme === 'system'" class="text-accent font-bold">✓</span>
                </button>
            </div>
        </div>
    </header>

    <main class="relative z-10 flex flex-1 items-center justify-center px-4 py-8 sm:px-6">
        <div class="w-full max-w-xl rounded-3xl border border-border bg-surface-card p-6 shadow-elevated sm:p-10">
            <h1 class="text-center text-3xl font-bold font-serif leading-tight text-main sm:text-4xl tracking-tight">@yield('heading', $pageTitle)</h1>
            @hasSection('subheading')
                <p class="mt-2 text-center text-sm text-muted sm:text-base leading-relaxed">@yield('subheading')</p>
            @endif
            <div class="mt-3 text-center text-xs sm:text-sm">@yield('switch')</div>
            @if (session('status'))
                <p role="status" class="mt-5 rounded-xl border border-success/30 bg-success-bg px-4 py-3 text-center text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</p>
            @endif
            <div class="mt-8">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="relative z-10 py-6 text-center text-xs text-muted">
        <span>© {{ date('Y') }} CENA Store. Thể thao là không ngừng bỏ cuộc.</span>
    </footer>
</body>
</html>
