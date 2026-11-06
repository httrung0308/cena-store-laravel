@props(['rating' => 0])
@php($rating = max(0, min(5, (float) $rating)))
<span {{ $attributes->class(['inline-flex items-center gap-0.5 text-warning']) }} role="img" aria-label="{{ rtrim(rtrim(number_format($rating, 1, ',', ''), '0'), ',') }} trên 5 sao">
    @for ($star = 1; $star <= 5; $star++)
        @php($fill = $rating >= $star ? 'full' : ($rating >= $star - 0.5 ? 'half' : 'empty'))
        <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
            @if ($fill === 'half')
                <defs><linearGradient id="half-{{ $star }}-{{ str_replace('.', '', (string) $rating) }}"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="rgb(var(--border-rgb, 203 213 225))"/></linearGradient></defs>
            @endif
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                fill="{{ $fill === 'full' ? 'currentColor' : ($fill === 'half' ? 'url(#half-'.$star.'-'.str_replace('.', '', (string) $rating).')' : 'rgb(var(--border-rgb, 203 213 225))') }}"/>
        </svg>
    @endfor
</span>
