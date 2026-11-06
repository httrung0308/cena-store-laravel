{{-- GET search box with a magnifier, slot for extra filters, a submit button and clear link --}}
@props(['action', 'id', 'label', 'value' => '', 'placeholder' => 'Tìm kiếm theo tên, mã slug...', 'name' => 'search'])
<div class="bg-surface-card p-4 rounded-2xl border border-border mb-6 flex flex-col md:flex-row items-center justify-between gap-4 text-xs shadow-sm">
    <form action="{{ $action }}" method="GET" {{ $attributes->class(['flex flex-col md:flex-row items-center justify-between gap-3 w-full']) }}>
        <label class="sr-only" for="{{ $id }}">{{ $label }}</label>
        <div class="flex-1 w-full flex items-center gap-2 bg-bg border border-border px-3 py-2.5 rounded-xl">
            <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input class="bg-transparent w-full focus:outline-none text-main font-medium placeholder:text-muted/60 text-xs" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}">
        </div>
        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto shrink-0">
            {{ $slot }}
            <button type="submit" class="px-4 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-all shadow-sm">Tìm</button>
            @if (filled($value) || request()->hasAny(['category', 'category_id', 'status', 'is_active', 'role', 'type']))
                <a href="{{ $action }}" class="px-3.5 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-all">Xóa lọc</a>
            @endif
        </div>
    </form>
</div>
