@props([
    'name',
    'label',
    'id' => null,
    'value' => null,
    'type' => 'text',
    'hint' => null,
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
])

@php
    $autocompleteTokens = is_string($autocomplete) ? preg_split('/\s+/', strtolower(trim($autocomplete))) : [];

    if (! in_array($type, ['text', 'email', 'tel', 'url', 'search', 'number'], true) || in_array('one-time-code', $autocompleteTokens, true)) {
        throw new \InvalidArgumentException('Use a dedicated component for password or one-time-code inputs.');
    }

    $inputId = $id ?? $name;
    $hintId = $inputId.'-hint';
    $errorId = $inputId.'-error';
    $hasError = isset($errors) && $errors->has($name);
    $hasHint = is_string($hint) && $hint !== '';
    $describedBy = trim(implode(' ', array_filter([
        $attributes->get('aria-describedby'),
        $hasHint ? $hintId : null,
        $hasError ? $errorId : null,
    ], static fn ($part) => is_string($part) && $part !== '')));
    $inputValue = old($name, $value);
    $inputValue = is_scalar($inputValue) ? (string) $inputValue : '';
@endphp

<div class="flex w-full flex-col">
    <label for="{{ $inputId }}" class="text-lg font-semibold">{{ (string) $label }}</label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $inputValue }}"
        @if ($placeholder !== null) placeholder="{{ (string) $placeholder }}" @endif
        @if ($autocomplete !== null) autocomplete="{{ (string) $autocomplete }}" @endif
        @if ($required) required aria-required="true" @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['aria-describedby', 'aria-invalid', 'aria-required', 'required'])->class([
            'input w-full',
            'border-red-500' => $hasError,
            'border-zinc-300' => ! $hasError,
        ]) }}
    >
    @if ($hasHint)
        <p id="{{ $hintId }}" class="mt-1 text-xs text-zinc-500">{{ (string) $hint }}</p>
    @endif
    <div class="min-h-4">
        <x-forms.field-error :name="$name" :id="$errorId" />
    </div>
</div>
