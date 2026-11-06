{{-- Storefront page shell: Cena navbar, cart/menu panels and footer around the page.
     Use @extends('layouts.store') with @section('title') and @section('content'). --}}
@php($pageTitle = $title ?? trim($__env->yieldContent('title', 'Cena')))
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — Cena</title>
    <link rel="icon" href="{{ asset('cena-favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('cena-favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('cena-favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-sans bg-bg text-main antialiased selection:bg-accent selection:text-white">
    <div id="storefront" x-data="storefrontShell" @cart-open.window="openPanel('cart')" @keydown.escape.window="closePanel()" @mousemove.window.throttle.30ms="if (hoverImage) { pointerX = $event.clientX; pointerY = $event.clientY }">
        <div :inert="panel !== null" :aria-hidden="panel !== null ? 'true' : null" class="flex min-h-screen flex-col">
            {{-- Top announcement banner --}}
            <div class="bg-surface/80 border-b border-border text-muted text-xs py-2 px-6 text-center tracking-wider">
                <span class="font-medium">Sự êm ái thuần khiết trên từng sải chân &nbsp;•&nbsp; Giao hàng toàn quốc &nbsp;•&nbsp; Đổi size an tâm trong 7 ngày</span>
            </div>
            <x-storefront.navigation :animated="false"/>
            <main class="w-full flex-1 px-4 py-6 sm:px-8 lg:px-12">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
            <x-storefront.footer/>
        </div>
        <x-storefront.panels/>
    </div>
    @stack('scripts')
</body>
</html>
