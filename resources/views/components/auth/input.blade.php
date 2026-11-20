{{-- Cena branded input component for auth pages --}}
@props(['name', 'label', 'id' => null, 'type' => 'text', 'placeholder' => null, 'autocomplete' => null, 'required' => false, 'asideUrl' => null, 'asideLabel' => null])
@php
    $inputId = $id ?? $name;
    $errorId = $inputId.'-error';
    $hasError = isset($errors) && $errors->has($name);
    $isPassword = $type === 'password';
    $value = $isPassword ? '' : old($name);
    $value = is_scalar($value) ? (string) $value : '';
@endphp
<div class="flex flex-col gap-1.5" @if ($isPassword) x-data="passwordField()" @endif>
    <div class="flex items-center justify-between">
        <label for="{{ $inputId }}" class="text-xs font-bold uppercase tracking-wider text-muted">{{ $label }}</label>
        @if ($asideUrl)<a href="{{ $asideUrl }}" class="text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">{{ $asideLabel }}</a>@endif
    </div>
    <div class="relative w-full">
        <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" @if ($isPassword) x-bind:type="inputType" autocapitalize="off" spellcheck="false" @else value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($required) required aria-required="true" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if ($hasError) aria-describedby="{{ $errorId }}" @endif
            @class([
                'input w-full rounded-xl px-4 py-2.5 text-sm text-main placeholder:text-muted/50 border bg-surface-card transition-colors duration-150',
                'border-border focus:border-accent focus:ring-2 focus:ring-accent/20' => ! $hasError,
                'border-error focus:border-error focus:ring-2 focus:ring-error/20 ring-1 ring-error' => $hasError,
                'pr-11' => $isPassword,
            ])>
        @if ($isPassword)
            <button type="button" class="btn-icon absolute inset-y-0 right-1 my-auto flex h-8 w-8 items-center justify-center p-0 text-muted hover:text-main" x-on:mousedown.prevent x-on:click="toggle()" x-bind:aria-pressed="shown.toString()" x-bind:aria-label="toggleLabel">
                <svg x-show="!shown" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="10" r="3"/></svg>
                <svg x-cloak x-show="shown" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="m1 1 22 22"/></svg>
            </button>
        @endif
    </div>
    <div class="min-h-5 text-xs">
        @if ($hasError)<p id="{{ $errorId }}" role="alert" class="text-xs font-medium text-error">{{ $errors->first($name) }}</p>@endif
    </div>
</div>
