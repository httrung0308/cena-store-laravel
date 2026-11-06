<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Cena — Thể thao là không ngừng bỏ cuộc.">
    <title>Cena</title>
    <link rel="icon" href="{{ asset('cena-favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('cena-favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('cena-favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans bg-bg text-main antialiased selection:bg-accent selection:text-white">
    <div id="storefront" x-data="storefrontShell" @cart-open.window="openPanel('cart')" @keydown.escape.window="closePanel()" @mousemove.window="pointerX = $event.clientX; pointerY = $event.clientY">
        <div :inert="panel !== null" :aria-hidden="panel !== null ? 'true' : null">
            {{-- Top announcement banner --}}
            <div class="bg-surface/80 border-b border-border text-muted text-xs py-2 px-6 text-center tracking-wider">
                <span class="font-medium">Sự êm ái thuần khiết trên từng sải chân &nbsp;•&nbsp; Giao hàng toàn quốc &nbsp;•&nbsp; Đổi size an tâm trong 7 ngày</span>
            </div>
            <x-storefront.navigation/>
            <main class="w-full" aria-label="Cena home">
                <!-- Hero Section (Japanese Minimalist Zenith) -->
                <section class="relative overflow-hidden border-b border-border bg-gradient-to-b from-surface via-bg to-surface-card px-4 py-16 sm:px-8 sm:py-24 lg:px-12">
                    <div class="mx-auto max-w-5xl text-center">
                        <span class="mb-4 inline-block text-xs font-semibold uppercase tracking-[0.25em] text-muted">
                            CENA — TINH THẦN BỀN BỈ & ÊM ÁI
                        </span>
                        <h1 class="mb-6 text-4xl sm:text-6xl lg:text-7xl font-light tracking-tight text-main leading-[1.15]">
                            Thể thao là <br class="hidden sm:inline">
                            <span class="font-serif italic font-normal text-accent">không ngừng bỏ cuộc.</span>
                        </h1>
                        <p class="mx-auto mb-10 max-w-2xl text-base sm:text-lg font-light leading-relaxed text-muted">
                            Tìm lại sự thanh thản và tự tin trong từng chuyển động. Tuyển chọn giày thể thao chính hãng với độ nâng đỡ êm ái suốt ngày dài.
                        </p>
                        <div class="mb-12 flex flex-wrap items-center justify-center gap-4">
                            <a href="{{ route('shoes.index') }}" class="btn">
                                Khám phá bộ sưu tập
                            </a>
                            <a href="#collections" class="btn-secondary">
                                Phân mục giày
                            </a>
                        </div>
                        <div class="relative mx-auto aspect-[16/9] max-w-4xl overflow-hidden rounded-3xl border border-border shadow-elevated">
                            <img src="{{ asset('cena-hero-banner.svg') }}" alt="Cena Hero Banner" class="h-full w-full object-cover object-center" fetchpriority="high">
                        </div>
                    </div>
                </section>

                <!-- Category Collections Section -->
                <section id="collections" class="mx-auto max-w-7xl px-4 py-20 sm:px-8 lg:px-12">
                    <div class="mb-14 text-center">
                        <span class="text-xs font-serif italic tracking-widest text-accent uppercase">Tuyển chọn</span>
                        <h2 class="mt-1 text-3xl font-light tracking-tight text-main sm:text-4xl">Bộ Sưu Tập Giày</h2>
                        <div class="mx-auto mt-4 h-px w-10 bg-accent/40"></div>
                    </div>

                    <div class="flex w-full flex-col items-center">
                        <div class="w-full space-y-12 lg:w-[40rem] lg:translate-x-12 xl:w-[56rem]">
                            <x-storefront.collection id="men" title="Men" subtitle="See our men collection for summer" image="men.jpg"/>
                            <x-storefront.collection id="women" title="Women" subtitle="Fresh summer collection avaible now" image="women.jpg" :right="true"/>
                        </div>
                        <div class="w-full space-y-12 lg:w-[40rem] lg:-translate-x-12 xl:w-[56rem] mt-12">
                            <x-storefront.collection id="kid" title="Kid" subtitle="See what we offer to kids" image="kid.jpg"/>
                            <x-storefront.collection id="unisex" title="Unisex" subtitle="See what we have in our offer" image="unisex.jpg" :right="true"/>
                        </div>
                    </div>
                </section>

                <x-storefront.footer/>
            </main>
        </div>
        <x-storefront.panels/>
    </div>
</body>
</html>
