@extends('layouts.store')

@section('title', 'Bài viết')

@php
    $tz = (string) config('store.business_timezone');
    // The newest article leads the first page as a large feature; the rest fill the grid below it.
    $featured = $articles->onFirstPage() ? $articles->first() : null;
    $others = $featured === null ? $articles->getCollection() : $articles->getCollection()->slice(1);
@endphp

@section('content')
    <section class="mx-auto max-w-7xl py-6 sm:py-10">
        <div class="space-y-3">
            <h1 class="text-3xl font-extrabold text-main sm:text-5xl tracking-tight">Bài viết</h1>
            <p class="text-base text-muted max-w-2xl leading-relaxed">Giày bán chạy, xu hướng, cách chọn size và bảo quản giày từ chuyên gia Cena.</p>
        </div>

        @if ($featured !== null)
            <article class="group mt-10 grid items-center gap-6 rounded-2xl border border-border bg-surface-card p-6 shadow-card transition-shadow duration-200 hover:shadow-card-hover lg:grid-cols-5 lg:gap-12 lg:p-8" data-news-card data-news-featured>
                <a href="{{ route('news.show', $featured->slug) }}" class="block aspect-[16/10] overflow-hidden rounded-xl bg-surface border border-border/70 lg:col-span-3">
                    <img src="{{ $featured->coverUrl() }}" alt="{{ $featured->title }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                </a>
                <div class="flex flex-col gap-4 lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">Mới nhất · {{ $featured->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                    <h2 class="text-2xl font-bold leading-tight text-main sm:text-3xl lg:text-4xl"><a href="{{ route('news.show', $featured->slug) }}" class="hover:text-accent hover:underline transition-colors">{{ $featured->title }}</a></h2>
                    <p class="text-sm sm:text-base text-muted leading-relaxed line-clamp-3">{{ $featured->summary }}</p>
                    <a href="{{ route('news.show', $featured->slug) }}" class="btn self-start">Đọc bài</a>
                </div>
            </article>
        @endif

        <div @class(['grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4', 'mt-14 border-t border-border pt-12' => $featured !== null && $others->isNotEmpty(), 'mt-10' => $featured === null])>
            @foreach ($others as $article)
                <article class="group flex flex-col gap-3 rounded-2xl border border-border bg-surface-card p-4 shadow-subtle transition-shadow duration-200 hover:shadow-card" data-news-card>
                    <a href="{{ route('news.show', $article->slug) }}" class="block aspect-[4/3] overflow-hidden rounded-xl bg-surface border border-border/60">
                        <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                    </a>
                    <p class="text-xs font-medium text-muted">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                    <h2 class="text-lg font-bold leading-snug text-main"><a href="{{ route('news.show', $article->slug) }}" class="hover:text-accent hover:underline transition-colors">{{ $article->title }}</a></h2>
                    <p class="line-clamp-3 text-sm text-muted leading-relaxed">{{ $article->summary }}</p>
                </article>
            @endforeach
        </div>
        @if ($articles->isEmpty())
            <div class="rounded-2xl border-2 border-dashed border-border bg-surface/40 p-16 text-center">
                <p class="text-base text-muted">Chưa có bài viết nào.</p>
            </div>
        @endif
        <div class="mt-12"><x-ui.pagination :paginator="$articles" /></div>
    </section>
@endsection
