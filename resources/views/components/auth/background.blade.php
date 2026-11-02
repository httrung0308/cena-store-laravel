{{-- Looping muted video behind the auth forms; the poster shows while it loads or when motion is reduced. --}}
<div aria-hidden="true">
    <div class="pointer-events-none fixed inset-0 -z-10 bg-black/60"></div>
    <video src="{{ asset('legacy/video/register.mp4') }}" poster="{{ asset('legacy/img/videoPoster.png') }}" autoplay muted loop playsinline class="pointer-events-none fixed inset-0 -z-20 h-screen w-screen object-cover motion-reduce:hidden"></video>
    <img src="{{ asset('legacy/img/videoPoster.png') }}" alt="" class="pointer-events-none fixed inset-0 -z-20 hidden h-screen w-screen object-cover motion-reduce:block">
</div>
