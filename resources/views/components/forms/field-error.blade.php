@props([
    'name',
    'id' => null,
])

@if (isset($errors) && $errors->has($name))
    <p id="{{ $id ?? $name.'-error' }}" role="alert" class="text-xs text-error font-medium">{{ (string) $errors->first($name) }}</p>
@endif
