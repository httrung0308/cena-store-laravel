@props([
    'compact' => false,
    'label' => 'Loading',
])

<svg
    {{ $attributes->except(['role', 'aria-label'])->class([
        'inline-block shrink-0 animate-spin motion-reduce:animate-none',
        'mr-2 h-10 w-10 fill-accent text-border' => ! $compact,
        'h-5 w-5 fill-white text-white' => $compact,
    ]) }}
    viewBox="0 0 24 24"
    fill="currentColor"
    role="img"
    aria-label="{{ $label }}"
    focusable="false"
>
    <path d="M12 22c5.421 0 10-4.579 10-10h-2c0 4.337-3.663 8-8 8s-8-3.663-8-8 3.663-8 8-8V2C6.579 2 2 6.579 2 12s4.579 10 10 10z" />
</svg>
