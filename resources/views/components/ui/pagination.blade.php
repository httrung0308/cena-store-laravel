@props(['paginator'])
@if ($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = array_values(array_unique(array_merge([1, $last], range(max(1, $current - 2), min($last, $current + 2)))));
        sort($pages, SORT_NUMERIC);
        $previousPage = null;
    @endphp
    <div {{ $attributes->class(['p-4 bg-surface/60 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted font-semibold']) }}>
        <span>Hiển thị {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} trong tổng số {{ $paginator->total() }} mục</span>
        @if ($last > 1)
            <nav aria-label="Phân trang" class="flex items-center gap-1.5">
                @if ($paginator->onFirstPage())
                    <span class="px-3 py-1 bg-surface-card border border-border rounded-lg text-muted/40 cursor-not-allowed text-xs font-semibold" aria-disabled="true">Trước</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-3 py-1 bg-surface-card border border-border rounded-lg text-muted hover:bg-surface text-xs font-semibold transition">Trước</a>
                @endif
                @foreach ($pages as $page)
                    @if ($previousPage !== null && $page > $previousPage + 1)
                        <span class="px-1 text-muted" aria-hidden="true">…</span>
                    @endif
                    @if ($page === $current)
                        <span aria-current="page" class="px-3 py-1 bg-accent text-accent-text rounded-lg font-bold text-xs">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" aria-label="Trang {{ $page }}" class="px-3 py-1 bg-surface-card border border-border rounded-lg text-muted hover:bg-surface text-xs font-semibold transition">{{ $page }}</a>
                    @endif
                    @php($previousPage = $page)
                @endforeach
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-3 py-1 bg-surface-card border border-border rounded-lg text-muted hover:bg-surface text-xs font-semibold transition">Sau</a>
                @else
                    <span class="px-3 py-1 bg-surface-card border border-border rounded-lg text-muted/40 cursor-not-allowed text-xs font-semibold" aria-disabled="true">Sau</span>
                @endif
            </nav>
        @endif
    </div>
@endif
