{{-- GET search box with a magnifier, a dark submit pill and a clear link when a term is active. --}}
@props(['action', 'id', 'label', 'value' => '', 'placeholder' => 'Tìm kiếm...', 'name' => 'search'])
<form action="{{ $action }}" method="GET" {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}>
    <label class="sr-only" for="{{ $id }}">{{ $label }}</label>
    <div class="relative w-full max-w-md flex-1">
        <input class="input w-full border-zinc-300 py-2 pr-10" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}">
        <svg class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    </div>
    {{ $slot }}
    <button type="submit" class="btn-pill-dark">Tìm</button>
    @if (filled($value))<a href="{{ $action }}" class="btn-pill">Xóa lọc</a>@endif
</form>
