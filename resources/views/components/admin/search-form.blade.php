{{-- GET search box with a magnifier, clear-keyword button, inline or tray filters, active filter chips, and clear-all link --}}
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
    $hasChips = ! empty($activeChips) || isset($chips);
@endphp
<div class="bg-surface-card p-4 rounded-2xl border border-border mb-6 space-y-2.5 text-xs shadow-sm">
    <form id="{{ $id }}-form"
          action="{{ $action }}"
          method="GET"
          @if($ajax) @submit.prevent="submitFilter($event.target)" @endif
          {{ $attributes->class(['space-y-2.5 w-full']) }}>
        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2.5 w-full">
            <label class="sr-only" for="{{ $id }}">{{ $label }}</label>
            <div class="relative flex-1 min-w-0">
                <svg class="w-4 h-4 text-muted absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input class="w-full pl-10 pr-8 py-2 bg-surface border border-border rounded-xl text-xs font-medium text-main placeholder:text-muted/70 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-0"
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

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                {{ $slot }}
                <button type="submit" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm shrink-0">Tìm</button>
                @if ($hasActiveQuery && ! $hasChips)
                    <a href="{{ $action }}"
                       @if($ajax) @click.prevent="resetFilters('{{ $action }}', $el.closest('form'))" @endif
                       class="px-3.5 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors shrink-0">
                        Xóa lọc
                    </a>
                @endif
            </div>
        </div>

        @isset($filters)
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 px-3.5 py-2 rounded-xl bg-surface/80 border border-dashed border-border text-xs">
                {{ $filters }}
            </div>
        @endisset
    </form>

    @if ($hasChips)
        <div id="admin-filter-chips" class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-border/60">
            <span class="text-[11px] font-bold text-muted mr-0.5">Đang lọc:</span>
            @foreach ($activeChips as $chip)
                <a href="{{ $chip['url'] }}"
                   @if($ajax) @click.prevent="visitUrl('{{ $chip['url'] }}')" @endif
                   aria-label="Bỏ điều kiện {{ $chip['label'] }}"
                   class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-0.5 rounded-full bg-accent/10 text-accent border border-accent/25 text-[11px] font-semibold hover:bg-accent/15 transition-colors">
                    <span>{{ $chip['label'] }}</span>
                    <span aria-hidden="true" class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full hover:bg-accent/20 font-bold">×</span>
                </a>
            @endforeach
            {{ $chips ?? '' }}
            <a href="{{ $action }}"
               @if($ajax) @click.prevent="resetFilters('{{ $action }}', document.getElementById('{{ $id }}-form'))" @endif
               class="ml-auto text-[11px] font-bold text-muted hover:text-error underline transition-colors">
                Xóa tất cả
            </a>
        </div>
    @endif
</div>
