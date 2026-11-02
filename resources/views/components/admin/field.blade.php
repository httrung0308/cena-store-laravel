{{-- Labelled back-office input with old() refill and its validation message. Extra attributes go to the input. --}}
@props(['name', 'label', 'value' => null, 'type' => 'text', 'id' => null, 'required' => false, 'hint' => null])
@php
    $inputId = $id ?? 'admin-'.str_replace(['[', ']', '.'], '-', $name);
    $hasError = isset($errors) && $errors->has($name);
    $inputValue = old($name, $value);
    $inputValue = is_scalar($inputValue) ? (string) $inputValue : '';
@endphp
<div>
    <label for="{{ $inputId }}" class="admin-label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $inputValue }}" @if ($required) required aria-required="true" @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if ($hasError) aria-describedby="{{ $inputId }}-error" @endif
        {{ $attributes->class(['input w-full', 'border-red-500' => $hasError, 'border-zinc-300' => ! $hasError]) }}>
    @if ($hint)<p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>@endif
    @if ($hasError)<p id="{{ $inputId }}-error" role="alert" class="mt-1 text-sm text-red-500">{{ $errors->first($name) }}</p>@endif
</div>
