<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Cena — Thể thao là không ngừng bỏ cuộc.">
    <title>Cena</title>
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
<body class="font-sans bg-bg text-main antialiased selection:bg-accent selection:text-white">
    <div id="storefront" x-data="storefrontShell" @cart-open.window="openPanel('cart')" @keydown.escape.window="closePanel()" @mousemove.window="pointerX = $event.clientX; pointerY = $event.clientY">
        <div :inert="panel !== null" :aria-hidden="panel !== null ? 'true' : null">
            {{-- Top announcement banner --}}
            <div class="bg-accent text-accent-text text-xs py-2.5 px-4 sm:px-6 text-center tracking-wider font-medium flex items-center justify-center gap-2 flex-wrap">
                <span class="px-2 py-0.5 rounded-full bg-accent-sub text-accent-sub-text text-[10px] font-bold uppercase tracking-wider">Cena Store</span>
                <span>Sự êm ái thuần khiết trên từng sải chân &nbsp;•&nbsp; Giao hàng toàn quốc &nbsp;•&nbsp; Đổi size an tâm trong 7 ngày</span>
            </div>
            <x-storefront.navigation/>
            <main class="w-full" aria-label="Cena home">
                <!-- Bold Dynamic Hero Section (Midnight Steel Indigo V4) -->
                <section class="relative overflow-hidden border-b border-border bg-gradient-to-b from-surface via-bg to-surface-card px-6 py-20 sm:py-28 lg:px-12">
                    <!-- Blurred Decorative Circles from Preview -->
                    <div class="absolute inset-0 pointer-events-none opacity-30" aria-hidden="true">
                        <svg class="h-full w-full" viewBox="0 0 1200 600" fill="none">
                            <circle cx="980" cy="280" r="260" fill="var(--accent)" fill-opacity="0.25"/>
                            <circle cx="220" cy="180" r="220" fill="var(--accent-sub)" fill-opacity="0.18"/>
                        </svg>
                    </div>

                    <div class="relative z-10 mx-auto max-w-4xl text-center">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent/15 border border-accent/30 text-accent text-xs font-bold uppercase tracking-widest mb-6">
                            <span>CENA — TINH THẦN BỀN BỈ & ÊM ÁI</span>
                        </div>
                        <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-main leading-[1.1] mb-6">
                            Thể thao là <br class="hidden sm:inline">
                            <span class="text-accent underline decoration-accent-sub decoration-4 underline-offset-8">không ngừng bỏ cuộc.</span>
                        </h1>
                        <p class="text-base sm:text-lg text-muted max-w-xl mx-auto leading-relaxed font-normal mb-10">
                            Tìm lại sự thanh thản và tự tin trong từng chuyển động. Tuyển chọn giày thể thao chính hãng với độ nâng đỡ êm ái suốt ngày dài.
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-4 mb-12">
                            <a href="{{ route('shoes.index') }}" class="px-8 py-3.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold tracking-wider rounded-xl transition-transform duration-200 shadow-md transform hover:-translate-y-0.5">
                                KHÁM PHÁ BỘ SƯU TẬP
                            </a>
                            <a href="#collections" class="px-8 py-3.5 border-2 border-border bg-surface-card text-main hover:bg-surface text-xs font-bold tracking-wider rounded-xl transition-colors">
                                XEM PHÂN MỤC
                            </a>
                        </div>
                        <div class="relative mx-auto aspect-[16/9] max-w-4xl overflow-hidden rounded-2xl border border-border shadow-md">
                            <img src="{{ asset('cena-hero-banner.svg') }}" alt="Cena Hero Banner" class="h-full w-full object-cover object-center" fetchpriority="high" decoding="async">
                        </div>
                    </div>
                </section>

                <!-- Category Collections Section (V4 Grid) -->
                <section id="collections" class="mx-auto max-w-6xl px-6 py-20">
                    <div class="flex items-center justify-between mb-12">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-accent font-mono">PHÂN MỤC NỔI BẬT</span>
                            <h2 class="text-2xl sm:text-3xl font-extrabold text-main mt-1">Dành Cho Gia Đình</h2>
                        </div>
                        <a href="{{ route('shoes.index') }}" class="text-xs font-bold text-accent hover:text-accent-hover flex items-center gap-1">
                            Xem tất cả →
                        </a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Men -->
                        <a href="{{ route('shoes.index', ['gender' => 'men']) }}" class="group block bg-surface-card rounded-2xl p-3 border border-border hover:border-accent transition-shadow duration-200 shadow-sm hover:shadow-md">
                            <div class="aspect-[4/5] rounded-xl overflow-hidden bg-surface mb-3 relative">
                                <img src="{{ asset('legacy/img/men.jpg') }}" alt="Giày Nam" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                                <span class="absolute top-2 right-2 px-2 py-0.5 rounded-md bg-accent text-accent-text text-[10px] font-bold">HOT</span>
                            </div>
                            <h3 class="font-bold text-sm text-main group-hover:text-accent transition-colors">Giày Nam</h3>
                            <p class="text-xs text-muted mt-0.5">Vững vàng & Tăng tốc</p>
                        </a>

                        <!-- Women -->
                        <a href="{{ route('shoes.index', ['gender' => 'women']) }}" class="group block bg-surface-card rounded-2xl p-3 border border-border hover:border-accent transition-shadow duration-200 shadow-sm hover:shadow-md">
                            <div class="aspect-[4/5] rounded-xl overflow-hidden bg-surface mb-3 relative">
                                <img src="{{ asset('legacy/img/women.jpg') }}" alt="Giày Nữ" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                                <span class="absolute top-2 right-2 px-2 py-0.5 rounded-md bg-accent-sub text-accent-sub-text text-[10px] font-bold">-25%</span>
                            </div>
                            <h3 class="font-bold text-sm text-main group-hover:text-accent transition-colors">Giày Nữ</h3>
                            <p class="text-xs text-muted mt-0.5">Thanh thoát & Êm dịu <span class="sr-only">Fresh summer collection avaible now</span></p>
                        </a>

                        <!-- Kids -->
                        <a href="{{ route('shoes.index', ['gender' => 'kids']) }}" class="group block bg-surface-card rounded-2xl p-3 border border-border hover:border-accent transition-shadow duration-200 shadow-sm hover:shadow-md">
                            <div class="aspect-[4/5] rounded-xl overflow-hidden bg-surface mb-3 relative">
                                <img src="{{ asset('legacy/img/kid.jpg') }}" alt="Trẻ Em" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            </div>
                            <h3 class="font-bold text-sm text-main group-hover:text-accent transition-colors">Trẻ Em</h3>
                            <p class="text-xs text-muted mt-0.5">Bảo vệ & Nâng niu</p>
                        </a>

                        <!-- Unisex -->
                        <a href="{{ route('shoes.index', ['gender' => 'unisex']) }}" class="group block bg-surface-card rounded-2xl p-3 border border-border hover:border-accent transition-shadow duration-200 shadow-sm hover:shadow-md">
                            <div class="aspect-[4/5] rounded-xl overflow-hidden bg-surface mb-3 relative">
                                <img src="{{ asset('legacy/img/unisex.jpg') }}" alt="Unisex" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            </div>
                            <h3 class="font-bold text-sm text-main group-hover:text-accent transition-colors">Unisex</h3>
                            <p class="text-xs text-muted mt-0.5">Linh hoạt phố thị</p>
                        </a>
                    </div>
                </section>

                <x-storefront.footer/>
            </main>
        </div>
        <x-storefront.panels/>
    </div>
</body>
</html>
