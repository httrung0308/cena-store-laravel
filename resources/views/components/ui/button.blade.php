@props([
    'type' => 'button',
    'loading' => false,
    'disabled' => false,
    'loadingLabel' => 'Loading',
])

@php
    $isLoading = filter_var($loading, FILTER_VALIDATE_BOOLEAN);
    $isDisabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN) || $isLoading;
@endphp

<button
    type="{{ $type }}"
    @disabled($isDisabled)
    aria-busy="{{ $isLoading ? 'true' : 'false' }}"
    {{ $attributes->except(['type', 'disabled', 'aria-busy'])->class([
        'btn relative inline-flex h-10 items-center justify-center text-center',
        'disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100 disabled:active:scale-100',
    ]) }}
>
    <span @class(['invisible' => $isLoading])>{{ $slot }}</span>

    @if ($isLoading)
        <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
            <x-ui.spinner compact />
        </span>
        <span class="sr-only">{{ $loadingLabel }}</span>
    @endif
</button>
