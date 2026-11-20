@props([
    'text',
    'label' => 'Giải thích',
    'placement' => 'top',
    'inline' => false,
])

@php
    $tooltipId = 'tooltip-'.\Illuminate\Support\Str::ulid();
@endphp

<span
    x-data="adminTooltip(@js($placement))"
    @mouseenter="openHover()"
    @mouseleave="closeHover()"
    @focusin="openHover()"
    @focusout="closeFocus($event)"
    @click.outside="close()"
    @keydown.escape.window="close()"
    class="relative inline-flex items-center align-middle {{ $inline ? '' : 'ml-1' }}"
>
    <button
        type="button"
        x-ref="trigger"
        @click.prevent.stop="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        aria-describedby="{{ $tooltipId }}"
        aria-label="{{ $label }}"
        {{ $attributes->merge([
            'class' => 'inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-border bg-surface text-muted hover:border-accent hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent transition-colors cursor-pointer',
        ]) }}
    >
        <svg class="h-2.5 w-2.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <circle cx="8" cy="8" r="6.5" />
            <path stroke-linecap="round" d="M8 7.25v4" />
            <circle cx="8" cy="4.75" r="0.65" fill="currentColor" stroke="none" />
        </svg>
    </button>

    <template x-teleport="body">
        <div
            id="{{ $tooltipId }}"
            x-ref="panel"
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            @mouseenter="openHover()"
            @mouseleave="closeHover()"
            role="tooltip"
            :style="`top: ${posTop}px; left: ${posLeft}px;`"
            class="fixed z-[60] w-max max-w-[min(22rem,calc(100vw-24px))] whitespace-pre-line break-words rounded-xl border border-border bg-surface-card px-3 py-2 text-left text-xs font-normal leading-relaxed text-main shadow-lg pointer-events-auto"
        >
            {{ $text }}
        </div>
    </template>
</span>
