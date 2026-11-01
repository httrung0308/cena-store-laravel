@php($pageTitle = $title ?? trim($__env->yieldContent('title', 'Cena Store')))
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — Cena</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-montserrat bg-white text-black">
    <div class="relative flex min-h-screen flex-col lg:flex-row">
        <aside class="relative min-h-52 overflow-hidden text-white lg:w-1/2" aria-label="Cena Store">
            <x-auth.background />
            <div class="relative z-10 flex h-full flex-col justify-between p-6 lg:p-12">
                <a href="{{ route('home') }}" class="text-3xl font-bold focus-visible:ring-4">Cena</a>
                <p class="mt-10 max-w-md text-3xl font-semibold">Sport, the smart choice.</p>
            </div>
        </aside>
        <main class="flex w-full flex-1 items-center justify-center px-5 py-10 sm:px-10 lg:w-1/2 lg:p-16">
            <div class="w-full max-w-lg">
                <h1 class="mb-6 text-3xl font-semibold">{{ $pageTitle }}</h1>
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
