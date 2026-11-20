@props([
    'name',
    'label',
    'options' => [],
    'mode' => 'checkbox',
    'id' => null,
    'value' => [],
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'hint' => null,
])
@php
    if (! in_array($mode, ['checkbox', 'radio'], true)) {
        throw new \InvalidArgumentException('Choice mode must be checkbox or radio.');
    }
    $fieldName = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
    $groupId = $id ?? 'choice-'.\Illuminate\Support\Str::random(12);
    $selected = old($fieldName, $value);
    $selected = is_array($selected) ? $selected : [$selected];
    $selected = array_values(array_filter($selected, 'is_string'));
    $hasError = isset($errors) && $errors->has($fieldName);
    $hintId = $groupId.'-hint';
    $errorId = $groupId.'-error';
    $readonlyChoices = (bool) $readonly;
@endphp
<fieldset id="{{ $groupId }}" {{ $attributes->class(['flex flex-col gap-2']) }}
    @if ($required) aria-required="true" @endif
    @if ($hasError) aria-invalid="true" @endif
    @if ($hasError || $hint) aria-describedby="{{ collect([$hint ? $hintId : null, $hasError ? $errorId : null])->filter()->implode(' ') }}" @endif
>
    <legend class="text-lg font-semibold">{{ $label }}</legend>
    @if ($hint)<p id="{{ $hintId }}" class="text-xs text-muted">{{ $hint }}</p>@endif
    @foreach ($options as $option)
        @if (is_array($option) && isset($option['value']) && is_string($option['value']) && isset($option['label']))
            @php
                $optionId = $groupId.'-'.(string) $loop->index;
                $isChecked = in_array($option['value'], $selected, true);
            @endphp
            <label for="{{ $optionId }}" @class(['flex cursor-pointer items-center gap-2 text-sm', 'cursor-not-allowed text-muted' => $disabled || $readonlyChoices || ($option['disabled'] ?? false)])>
                <input id="{{ $optionId }}" type="{{ $mode }}"
                    name="{{ $mode === 'checkbox' ? $fieldName.'[]' : $fieldName }}"
                    value="{{ $option['value'] }}" @checked($isChecked)
                    @disabled($disabled || $readonlyChoices || ($option['disabled'] ?? false))
                    @if ($readonlyChoices) aria-readonly="true" @endif
                    class="peer sr-only">
                <span aria-hidden="true" @class([
                    'inline-flex h-5 w-5 shrink-0 items-center justify-center border border-border bg-surface-card text-sm font-bold text-transparent',
                    'rounded-full' => $mode === 'radio',
                    'rounded-sm' => $mode === 'checkbox',
                    'peer-checked:border-accent peer-checked:bg-accent peer-checked:text-accent-text',
                    'peer-focus-visible:ring-4 peer-focus-visible:ring-accent/40 peer-focus-visible:ring-offset-2',
                    'peer-disabled:border-border peer-disabled:bg-surface peer-disabled:opacity-50',
                ])>{{ $mode === 'checkbox' ? '✓' : '●' }}</span>
                <span>{{ (string) $option['label'] }}</span>
            </label>
        @endif
    @endforeach
    <x-forms.field-error :name="$fieldName" :id="$errorId" />
</fieldset>
