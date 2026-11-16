@props([
    'active',
    'action',
    'toggleKey',
    'activeLabel' => 'Đang bật',
    'inactiveLabel' => 'Đã tắt',
    'actionLabel' => null,
    'showLabel' => true,
    'tone' => 'success',
    'compact' => false,
    'disabled' => false,
    'disabledTitle' => null,
])

@php
    $isActive = (bool) $active;
    $resolvedActionLabel = $actionLabel ?? ($isActive ? $inactiveLabel : $activeLabel);
    $statusText = $isActive ? $activeLabel : $inactiveLabel;
    $resolvedTitle = ($disabled && $disabledTitle) ? $disabledTitle : $resolvedActionLabel;

    $activeTrack = match ($tone) {
        'accent' => 'bg-accent border-accent/80',
        'warning' => 'bg-warning border-warning/80',
        default => 'bg-success border-success/80',
    };
    $activeText = match ($tone) {
        'accent' => 'text-accent',
        'warning' => 'text-warning-text',
        default => 'text-success',
    };

    $trackSize = $compact ? 'h-[18px] w-8' : 'h-5 w-9';
    $thumbSize = $compact ? 'h-3.5 w-3.5' : 'h-4 w-4';
    $thumbOffset = $isActive
        ? ($compact ? 'translate-x-3.5' : 'translate-x-4')
        : 'translate-x-0.5';
@endphp

<form method="POST" action="{{ $action }}" @submit.prevent="submitToggle($event, @js($toggleKey))" class="inline-flex items-center">
    @csrf
    @method('PATCH')
    {{ $slot }}
    <button
        type="submit"
        role="switch"
        aria-checked="{{ $isActive ? 'true' : 'false' }}"
        aria-label="{{ $resolvedActionLabel }}"
        title="{{ $resolvedTitle }}"
        @disabled($disabled)
        :disabled="isBusy() || {{ $disabled ? 'true' : 'false' }}"
        {{ $attributes->merge([
            'class' => 'group inline-flex items-center gap-2 rounded-full py-1 px-1.5 -mx-1.5 cursor-pointer transition-colors hover:bg-surface/80 disabled:opacity-45 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
        ]) }}
    >
        <span
            aria-hidden="true"
            @class([
                'relative inline-flex shrink-0 items-center rounded-full border transition-colors duration-150',
                $trackSize,
                $activeTrack => $isActive,
                'bg-border/90 border-border group-hover:bg-muted/35' => ! $isActive,
            ])
        >
            <span
                @class([
                    'pointer-events-none inline-block rounded-full bg-white shadow-sm transition-transform duration-150',
                    $thumbSize,
                    $thumbOffset,
                ])
            ></span>
        </span>
        @if ($showLabel)
            <span @class([
                'text-xs font-semibold whitespace-nowrap transition-colors',
                $activeText => $isActive,
                'text-muted' => ! $isActive,
            ])>{{ $statusText }}</span>
        @endif
    </button>
</form>
