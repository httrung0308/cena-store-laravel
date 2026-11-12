@extends('layouts.store')

@section('title', 'Bài viết')

@php
    $tz = (string) config('store.business_timezone');
    $search = (string) ($search ?? '');
    $isSearching = $search !== '';
    $showFeaturedMarker = ! $isSearching && $articles->onFirstPage();
@endphp

@section('content')
    <section id="news-listing"
             x-ref="root"
             x-data="newsListing({ baseUrl: @js(route('news.index')), initialSearch: @js($search) })"
             class="mx-auto max-w-7xl py-6 sm:py-10">
        <div class="flex flex-col gap-6 border-b border-border/80 pb-6 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2.5 max-w-2xl">
                <h1 class="text-3xl font-extrabold text-main sm:text-5xl tracking-tight">Bài viết</h1>
                <p class="text-sm sm:text-base text-muted leading-relaxed">Giày bán chạy, xu hướng, cách chọn size và bảo quản giày từ chuyên gia Cena.</p>
            </div>

            <form method="GET"
                  action="{{ route('news.index') }}"
                  role="search"
                  aria-label="Tìm kiếm bài viết theo tiêu đề"
                  @submit.prevent="submitSearch()"
                  class="w-full md:w-96 shrink-0">
                <label for="news-search-input" class="sr-only">Tìm bài viết theo tiêu đề</label>
                <div class="relative flex items-center">
                    <svg class="pointer-events-none absolute left-3.5 h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <path stroke-linecap="round" d="m21 21-4.35-4.35" />
                    </svg>
                    <input id="news-search-input"
                           x-ref="searchInput"
                           type="search"
                           name="search"
                           value="{{ $search }}"
                           x-model="search"
                           @input="onSearchInput()"
                           @keydown.enter.prevent="submitSearch()"
                           maxlength="150"
                           placeholder="Tìm theo tiêu đề bài viết..."
                           class="input w-full py-2.5 pl-10 pr-20 text-sm shadow-subtle">
                    <div class="absolute right-1.5 flex items-center gap-1">
                        <button type="button"
                                x-cloak
                                x-show="search.trim().length > 0"
                                @click="clearSearch()"
                                aria-label="Xóa từ khóa tìm kiếm"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface hover:text-main">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <button type="submit"
                                class="inline-flex h-8 items-center rounded-lg bg-accent px-3 text-xs font-semibold text-accent-text transition-colors hover:bg-accent-hover">
                            Tìm
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div x-cloak x-show="errorMessage" role="alert" class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-error/30 bg-error-bg px-4 py-3 text-xs font-medium text-error-text">
            <span x-text="errorMessage"></span>
            <button type="button" @click="retryLastRequest()" class="rounded-lg border border-error/30 bg-surface-card px-3 py-1 font-semibold text-main transition-colors hover:bg-surface">Thử lại</button>
        </div>

        <div id="news-results-region"
             x-ref="resultsRegion"
             @click="handleResultsClick($event)"
             :aria-busy="loading.toString()"
             :class="loading && 'opacity-60 transition-opacity duration-150'">

            @if ($isSearching)
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface/60 px-4 py-3 text-xs sm:text-sm">
                    <p class="text-muted">
                        @if ($articles->total() > 0)
                            Tìm thấy <strong class="font-bold text-main">{{ $articles->total() }}</strong> bài viết có tiêu đề chứa
                            <strong class="font-bold text-main">“{{ $search }}”</strong>
                        @else
                            Không có bài viết nào có tiêu đề chứa <strong class="font-bold text-main">“{{ $search }}”</strong>
                        @endif
                    </p>
                    <a href="{{ route('news.index') }}" data-news-reset class="font-semibold text-accent hover:text-accent-hover hover:underline">
                        Xóa tìm kiếm
                    </a>
                </div>
            @endif

            @if ($articles->isNotEmpty())
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 sm:gap-6">
                    @foreach ($articles as $article)
                        @php($isLead = $showFeaturedMarker && $loop->first)
                        <article @if ($isLead) data-news-featured @endif class="group flex h-full flex-col rounded-2xl border border-border bg-surface-card p-3.5 shadow-subtle transition-[box-shadow,border-color] duration-200 hover:border-accent/40 hover:shadow-card sm:p-4" data-news-card>
                            <a href="{{ route('news.show', $article->slug) }}" class="block aspect-[16/10] overflow-hidden rounded-xl border border-border/60 bg-surface">
                                <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}" decoding="async" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            </a>
                            <div class="mt-3.5 flex min-w-0 flex-1 flex-col justify-between gap-3">
                                <div class="space-y-1.5">
                                    <time class="block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</time>
                                    <h2 class="text-base font-bold leading-snug text-main line-clamp-2 break-words sm:text-lg">
                                        <a href="{{ route('news.show', $article->slug) }}" class="hover:text-accent hover:underline transition-colors">{{ $article->title }}</a>
                                    </h2>
                                    <p class="line-clamp-2 text-xs text-muted leading-relaxed sm:text-sm">{{ $article->summary }}</p>
                                </div>
                                <a href="{{ route('news.show', $article->slug) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-accent hover:text-accent-hover hover:underline">
                                    <span>Đọc bài</span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($articles->isEmpty())
                <div class="mt-8 rounded-2xl border-2 border-dashed border-border bg-surface/40 p-12 text-center sm:p-16">
                    @if ($isSearching)
                        <p class="text-base font-bold text-main">Không tìm thấy bài viết phù hợp</p>
                        <p class="mt-2 text-sm text-muted">Thử từ khóa ngắn hơn hoặc xóa bộ lọc để xem toàn bộ bài viết đang đăng.</p>
                        <a href="{{ route('news.index') }}" data-news-reset class="btn mt-5 inline-flex">Xem tất cả bài viết</a>
                    @else
                        <p class="text-base font-semibold text-muted">Chưa có bài viết nào.</p>
                    @endif
                </div>
            @endif

            @if ($articles->hasPages())
                <div class="mt-10 overflow-hidden rounded-2xl border border-border">
                    <x-ui.pagination :paginator="$articles" />
                </div>
            @endif
        </div>
    </section>
@endsection
