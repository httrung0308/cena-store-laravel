<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cena — Sport, the smart choice.">
    <title>Shoes e-commerce</title>
    <link rel="icon" href="{{ asset('legacy/favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="storefront" x-data="storefrontShell" @keydown.escape.window="closePanel()" @mousemove.window="pointerX = $event.clientX; pointerY = $event.clientY">
        <div :inert="panel !== null" :aria-hidden="panel !== null ? 'true' : null">
            <x-storefront.navigation/>
            <main class="flex h-screen w-full items-center justify-center" aria-label="Cena home">
                <div class="legacy-home-image relative h-64 w-64 sm:h-96 sm:w-96" style="width: 100%; height: 80%; transform: translateY(40%); padding: 1rem;">
                    <div class="legacy-headline absolute bottom-full mb-0 ml-5 md:ml-10 lg:-mb-5 xl:ml-24 2xl:mb-5 2xl:ml-48">
                        <h1 class="-mt-5 text-5xl font-extrabold md:-mt-8 md:text-6xl xl:-mt-12 xl:text-extra">Sport,</h1>
                        <p class="-mt-5 hidden text-5xl font-extrabold sm:block md:-mt-8 md:text-6xl xl:-mt-12 xl:text-extra">the smart choice.</p>
                        <p class="-mt-5 block text-5xl font-extrabold sm:hidden">the smart</p>
                        <p class="-mt-5 block text-5xl font-extrabold sm:hidden">choice.</p>
                    </div>
                    <img src="{{ asset('legacy/img/main.jpg') }}" alt="" class="h-full w-full object-cover object-center" fetchpriority="high">
                    <div class="legacy-collections">
                        <div class="py-28"><div class="flex w-full flex-col items-center md:px-16 lg:px-0">
                            <div class="w-full lg:w-[40rem] lg:translate-x-20 xl:w-[60rem]">
                                <x-storefront.collection id="men" title="Men" subtitle="See our men collection for summer" image="men.jpg"/>
                                <x-storefront.collection id="women" title="Women" subtitle="Fresh summer collection avaible now" image="women.jpg" :right="true"/>
                            </div>
                            <div class="w-full lg:w-[40rem] lg:-translate-x-20 xl:w-[60rem]">
                                <x-storefront.collection id="kid" title="Kid" subtitle="See what we offer to kids" image="kid.jpg"/>
                                <x-storefront.collection id="unisex" title="Unisex" subtitle="See what we have in our offer" image="unisex.jpg" :right="true"/>
                            </div>
                        </div></div>
                        <x-storefront.footer/>
                    </div>
                </div>
            </main>
        </div>
        <x-storefront.panels/>
    </div>
</body>
</html>
