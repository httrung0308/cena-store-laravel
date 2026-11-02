{{-- Full-screen sign-in / sign-up shell: looping video background, no navbar or footer, Nike logo back to home.
     Sections: title, heading, subheading (optional), switch (link to the other form), content (the form). --}}
@php($pageTitle = $title ?? trim($__env->yieldContent('title', 'Nike Store')))
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — Nike</title>
    <link rel="icon" href="{{ asset('legacy/favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-montserrat bg-black text-white">
    <x-auth.background />
    <a href="{{ route('home') }}" class="absolute left-6 top-6 z-10 text-2xl font-bold text-white focus-visible:ring-4 sm:left-10" aria-label="Về trang chủ">Nike</a>
    <main class="relative flex min-h-screen flex-col items-center justify-center pb-12 pt-24">
        <div class="w-full p-4 sm:p-7">
            <h1 class="text-center text-4xl font-semibold leading-tight xl:text-6xl">@yield('heading', $pageTitle)</h1>
            @hasSection('subheading')
                <p class="hidden text-center text-xl sm:block xl:text-2xl">@yield('subheading')</p>
            @endif
            <div class="w-full text-center">@yield('switch')</div>
            @if (session('status'))
                <p role="status" class="mx-auto mt-6 max-w-[40rem] rounded-md bg-white/20 px-4 py-3 text-sm">{{ session('status') }}</p>
            @endif
            <div class="mt-10 flex w-full justify-center">
                <div class="w-full max-w-[40rem]">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </div>
        </div>
    </main>
</body>
</html>
