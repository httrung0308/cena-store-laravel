@props([
    'name',
    'id' => null,
    'label',
    'autocomplete' => 'current-password',
])

@php
    $fieldId = $id ?? $name;
@endphp

<div class="flex flex-col" x-data="passwordField()">
    <label for="{{ $fieldId }}" class="text-lg font-semibold">{{ $label }}</label>

    <div class="relative w-full">
        <input
            id="{{ $fieldId }}"
            name="{{ $name }}"
            type="password"
            x-bind:type="inputType"
            autocomplete="{{ $autocomplete }}"
            autocapitalize="off"
            spellcheck="false"
            class="input w-full pr-36"
        >

        <button
            type="button"
            class="absolute inset-y-0 right-0 px-2 text-sm font-semibold underline focus-visible:ring-4"
            x-on:mousedown.prevent
            x-on:click="toggle()"
            x-bind:aria-pressed="shown.toString()"
            x-bind:aria-label="toggleLabel"
            x-text="toggleLabel"
        >Hiện mật khẩu</button>
    </div>
</div>
