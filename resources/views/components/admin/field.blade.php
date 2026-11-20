{{-- Labelled back-office input with old() refill and its validation message. Extra attributes go to the input. --}}
@props(['name', 'label', 'value' => null, 'type' => 'text', 'id' => null, 'required' => false, 'hint' => null])
@php
    $inputId = $id ?? 'admin-'.str_replace(['[', ']', '.'], '-', $name);
    $hasError = isset($errors) && $errors->has($name);
    $inputValue = old($name, $value);
    $inputValue = is_scalar($inputValue) ? (string) $inputValue : '';
@endphp
<div>
    <label for="{{ $inputId }}" class="admin-label">{{ $label }} @if ($required)<span class="text-error">*</span>@endif</label>
    <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $inputValue }}" @if ($required) required aria-required="true" @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if ($hasError) aria-describedby="{{ $inputId }}-error" @endif
        {{ $attributes->class(['input w-full', 'border-error focus:border-error focus:ring-2 focus:ring-error/20 ring-1 ring-error' => $hasError, 'border-border focus:border-accent' => ! $hasError]) }}>
    @if ($hint)<p class="mt-1 text-xs text-muted">{{ $hint }}</p>@endif
    @if ($hasError)<p id="{{ $inputId }}-error" role="alert" class="mt-1 text-xs font-medium text-error">{{ $errors->first($name) }}</p>@endif
</div>
