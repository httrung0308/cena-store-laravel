{{-- GET search box with a magnifier, clear-keyword button, slot for extra filters, active filter chips, and clear-all link --}}
@props([
    'action',
    'id',
    'label',
    'value' => '',
    'placeholder' => 'Tìm kiếm theo tên, mã slug...',
    'name' => 'search',
    'ajax' => false,
    'activeChips' => [],
])
@php
    $hasActiveQuery = filled($value) || collect(request()->except('page'))->contains(function ($val) {
        if (is_array($val)) {
            return collect($val)->contains(fn ($item) => $item !== null && $item !== '');
        }

        return $val !== null && $val !== '';
    });
@endphp
<div class="bg-surface-card p-4 rounded-2xl border border-border mb-6 space-y-3 text-xs shadow-sm">
    <form id="{{ $id }}-form"
          action="{{ $action }}"
          method="GET"
          @if($ajax) @submit.prevent="submitFilter($event.target)" @endif
          {{ $attributes->class(['flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 w-full']) }}>
        <label class="sr-only" for="{{ $id }}">{{ $label }}</label>
        <div class="relative flex-1 w-full flex items-center gap-2 bg-bg border border-border px-3 py-2 rounded-xl focus-within:ring-2 focus-within:ring-accent focus-within:border-accent">
            <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input class="bg-transparent w-full focus:outline-none text-main font-medium placeholder:text-muted/70 text-xs pr-6"
                   id="{{ $id }}"
                   type="search"
                   name="{{ $name }}"
                   value="{{ $value }}"
                   placeholder="{{ $placeholder }}">
            @if (filled($value))
                <a href="{{ request()->fullUrlWithQuery([$name => null, 'page' => null]) }}"
                   @if($ajax) @click.prevent="clearField('{{ $name }}', $el.closest('form'))" @endif
                   aria-label="Xóa từ khóa tìm kiếm"
                   class="absolute right-2.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-5 h-5 rounded-full text-muted hover:text-main hover:bg-border/60 transition-colors">
                    <span aria-hidden="true">&times;</span>
                </a>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto shrink-0">
            {{ $slot }}
            <button type="submit" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm">Tìm</button>
            @if ($hasActiveQuery)
                <a href="{{ $action }}"
                   @if($ajax) @click.prevent="resetFilters('{{ $action }}', $el.closest('form'))" @endif
                   class="px-3.5 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">
                    Xóa lọc
                </a>
            @endif
        </div>
    </form>

    @if (! empty($activeChips) || isset($chips))
        <div id="admin-filter-chips" class="flex flex-wrap items-center gap-2 pt-2 border-t border-border/60">
            <span class="text-[11px] font-semibold text-muted">Đang lọc:</span>
            @foreach ($activeChips as $chip)
                <a href="{{ $chip['url'] }}"
                   @if($ajax) @click.prevent="visitUrl('{{ $chip['url'] }}')" @endif
                   aria-label="Bỏ điều kiện {{ $chip['label'] }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface border border-border text-[11px] font-semibold text-main hover:border-accent/60 hover:text-accent transition-colors">
                    <span>{{ $chip['label'] }}</span>
                    <span aria-hidden="true" class="text-muted">×</span>
                </a>
            @endforeach
            {{ $chips ?? '' }}
            <a href="{{ $action }}"
               @if($ajax) @click.prevent="resetFilters('{{ $action }}', document.getElementById('{{ $id }}-form'))" @endif
               class="ml-auto text-[11px] font-bold text-accent hover:underline">
                Xóa tất cả
            </a>
        </div>
    @endif
</div>
