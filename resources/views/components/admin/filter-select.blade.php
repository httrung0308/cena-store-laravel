@props([
    'name',
    'label',
    'id' => null,
    'active' => false,
    'ajax' => true,
])
@php
    $selectId = $id ?? 'filter-'.str_replace(['[', ']', '.'], '-', $name);
@endphp
<div class="inline-flex items-center gap-2 min-w-0 max-w-full">
    <label for="{{ $selectId }}" class="text-[11px] font-semibold text-muted whitespace-nowrap shrink-0">{{ $label }}</label>
    <select id="{{ $selectId }}"
            name="{{ $name }}"
            @if($ajax) @change="submitFilter($el.form)" @endif
            {{ $attributes->class([
                'px-2.5 py-1 border rounded-lg text-xs max-w-full focus:outline-none focus:ring-2 focus:ring-accent transition-colors',
                'border-accent/60 bg-accent/5 text-accent font-bold' => $active,
                'border-border bg-surface-card text-main font-semibold' => ! $active,
            ]) }}>
        {{ $slot }}
    </select>
</div>
