@props([
    'state' => 'empty',
    'title' => null,
    'message' => null,
    'requestId' => null,
])
@php
    $state = in_array($state, ['empty', 'error', 'offline'], true) ? $state : 'empty';
    $defaults = [
        'empty' => ['Không có dữ liệu', 'Chưa có mục nào để hiển thị.'],
        'error' => ['Không thể tải dữ liệu', 'Vui lòng thử lại sau.'],
        'offline' => ['Mất kết nối', 'Kiểm tra kết nối mạng rồi thử lại.'],
    ];
@endphp
<section {{ $attributes->class(['flex min-h-40 w-full flex-col items-center justify-center gap-3 border border-zinc-200 bg-white px-4 py-8 text-center text-black']) }} role="status" aria-live="polite">
    <h2 class="text-lg font-semibold">{{ $title ?? $defaults[$state][0] }}</h2>
    <p class="max-w-prose text-sm text-zinc-600">{{ $message ?? $defaults[$state][1] }}</p>
    @if (is_string($requestId) && $requestId !== '')
        <p class="text-xs text-zinc-500">Mã yêu cầu: {{ $requestId }}</p>
    @endif
    @if (isset($retry))<div class="mt-3">{{ $retry }}</div>@endif
</section>
