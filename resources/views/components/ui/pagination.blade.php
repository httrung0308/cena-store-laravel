@props(['paginator'])
@if ($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->lastPage() > 1)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = array_values(array_unique(array_merge([1, $last], range(max(1, $current - 2), min($last, $current + 2)))));
        sort($pages, SORT_NUMERIC);
        $previousPage = null;
    @endphp
    <nav aria-label="Phân trang" {{ $attributes->class(['flex flex-wrap items-center justify-center gap-2 py-4 text-sm']) }}>
        @if ($paginator->onFirstPage())
            <span class="rounded-full border border-zinc-200 px-4 py-2 font-semibold opacity-30" aria-disabled="true">Trước</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-full border border-zinc-200 px-4 py-2 font-semibold transition hover:border-zinc-900 focus-visible:ring-4">Trước</a>
        @endif
        @foreach ($pages as $page)
            @if ($previousPage !== null && $page > $previousPage + 1)
                <span aria-hidden="true">…</span>
            @endif
            @if ($page === $current)
                <span aria-current="page" class="rounded-full bg-black px-4 py-2 font-semibold text-white">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" aria-label="Trang {{ $page }}" class="rounded-full border border-zinc-200 px-4 py-2 font-semibold transition hover:border-zinc-900 focus-visible:ring-4">{{ $page }}</a>
            @endif
            @php($previousPage = $page)
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-full border border-zinc-200 px-4 py-2 font-semibold transition hover:border-zinc-900 focus-visible:ring-4">Tiếp</a>
        @else
            <span class="rounded-full border border-zinc-200 px-4 py-2 font-semibold opacity-30" aria-disabled="true">Tiếp</span>
        @endif
    </nav>
@endif
