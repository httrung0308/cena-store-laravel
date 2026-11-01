@props([
    'name',
    'id' => null,
])

@if (isset($errors) && $errors->has($name))
    <p id="{{ $id ?? $name.'-error' }}" role="alert" class="text-xs italic text-red-500">{{ (string) $errors->first($name) }}</p>
@endif
