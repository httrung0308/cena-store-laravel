{{-- Back-office shell. Use @extends('layouts.admin') + @section('title') / @section('content').
     Sidebar links appear only once their route exists, so Codex's PRs light them up without editing this file. --}}
@php
    $pageTitle = $title ?? trim($__env->yieldContent('title', 'Quản trị'));
    $sections = [
        ['route' => 'admin.dashboard', 'label' => 'Tổng quan'],
        ['route' => 'admin.categories.index', 'label' => 'Danh mục'],
        ['route' => 'admin.suppliers.index', 'label' => 'Nhà cung cấp'],
        ['route' => 'admin.products.index', 'label' => 'Sản phẩm'],
        ['route' => 'admin.stock-imports.index', 'label' => 'Nhập kho'],
    ];
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $pageTitle }} — Quản trị Cena</title>
    <link rel="icon" href="{{ asset('legacy/favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-montserrat bg-zinc-50 text-black">
    <div class="flex min-h-screen flex-col md:flex-row">
        <aside class="w-full shrink-0 bg-black text-white md:w-60" aria-label="Menu quản trị">
            <div class="flex items-center justify-between px-6 py-5">
                <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold">Cena Admin</a>
                <a href="{{ route('home') }}" class="text-xs text-zinc-400 hover:text-white hover:underline">Xem cửa hàng</a>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-3 pb-4 md:flex-col md:overflow-visible">
                @foreach ($sections as $section)
                    @if (Route::has($section['route']))
                        <a href="{{ route($section['route']) }}"
                           @class([
                               'whitespace-nowrap rounded px-3 py-2 text-sm transition',
                               'bg-white font-semibold text-black' => request()->routeIs(str_replace('.index', '.*', $section['route'])),
                               'text-zinc-300 hover:bg-zinc-800 hover:text-white' => ! request()->routeIs(str_replace('.index', '.*', $section['route'])),
                           ])
                           @if (request()->routeIs(str_replace('.index', '.*', $section['route']))) aria-current="page" @endif>{{ $section['label'] }}</a>
                    @else
                        <span class="whitespace-nowrap px-3 py-2 text-sm text-zinc-600" aria-disabled="true">{{ $section['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-zinc-200 bg-white px-6 py-4">
                <h1 class="text-xl font-semibold">{{ $pageTitle }}</h1>
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-zinc-600">{{ auth()->user()?->full_name }}</span>
                    @if (Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="font-semibold hover:underline">Đăng xuất</button>
                        </form>
                    @endif
                </div>
            </header>
            <main class="flex-1 p-6">
                @if (session('status'))
                    <div role="status" class="mb-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
                @endif
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
