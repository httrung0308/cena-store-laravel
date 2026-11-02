{{-- Translucent input used on the video-background auth pages. Password fields get a show/hide eye toggle;
     asideUrl/asideLabel add a small link beside the label (e.g. forgotten password).
     Old input is refilled for every type except password. --}}
@props(['name', 'label', 'id' => null, 'type' => 'text', 'placeholder' => null, 'autocomplete' => null, 'required' => false, 'asideUrl' => null, 'asideLabel' => null])
@php
    $inputId = $id ?? $name;
    $errorId = $inputId.'-error';
    $hasError = isset($errors) && $errors->has($name);
    $isPassword = $type === 'password';
    $value = $isPassword ? '' : old($name);
    $value = is_scalar($value) ? (string) $value : '';
@endphp
<div class="flex flex-col" @if ($isPassword) x-data="passwordField()" @endif>
    <div class="flex items-center justify-between">
        <label for="{{ $inputId }}" class="text-lg font-semibold">{{ $label }}</label>
        @if ($asideUrl)<a href="{{ $asideUrl }}" class="text-sm underline">{{ $asideLabel }}</a>@endif
    </div>
    <div class="relative w-full">
        <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" @if ($isPassword) x-bind:type="inputType" autocapitalize="off" spellcheck="false" @else value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($required) required aria-required="true" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if ($hasError) aria-describedby="{{ $errorId }}" @endif
            @class(['input w-full border-none bg-white/25 text-zinc-100 placeholder:text-zinc-400', 'pr-10' => $isPassword, 'ring-2 ring-red-400' => $hasError])>
        @if ($isPassword)
            <button type="button" class="absolute inset-y-0 right-0 flex items-center px-2 text-zinc-100 focus-visible:ring-4" x-on:mousedown.prevent x-on:click="toggle()" x-bind:aria-pressed="shown.toString()" x-bind:aria-label="toggleLabel">
                <svg x-show="!shown" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-cloak x-show="shown" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="m1 1 22 22"/></svg>
            </button>
        @endif
    </div>
    <div class="min-h-4 text-xs italic text-red-300">
        @if ($hasError)<p id="{{ $errorId }}" role="alert">{{ $errors->first($name) }}</p>@endif
    </div>
</div>
