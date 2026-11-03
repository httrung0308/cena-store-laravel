@extends('layouts.store')

@section('title', 'Tin tức')

@php
    $tz = (string) config('store.business_timezone');
    // The newest article leads the first page as a large feature; the rest fill the grid below it.
    $featured = $articles->onFirstPage() ? $articles->first() : null;
    $others = $featured === null ? $articles->getCollection() : $articles->getCollection()->slice(1);
@endphp

@section('content')
    <section class="py-10">
        <div class="space-y-2">
            <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Nike Store</p>
            <h1 class="text-4xl font-bold sm:text-5xl">Tin tức</h1>
            <p class="text-zinc-500">Giày bán chạy, xu hướng, cách chọn size và bảo quản giày.</p>
        </div>

        @if ($featured !== null)
            <article class="group mt-10 grid items-center gap-6 lg:grid-cols-5 lg:gap-12" data-news-card data-news-featured>
                <a href="{{ route('news.show', $featured->slug) }}" class="block aspect-[16/10] overflow-hidden bg-zinc-100 lg:col-span-3">
                    <img src="{{ $featured->coverUrl() }}" alt="{{ $featured->title }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                </a>
                <div class="flex flex-col gap-4 lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Mới nhất · {{ $featured->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                    <h2 class="text-3xl font-bold leading-tight sm:text-4xl"><a href="{{ route('news.show', $featured->slug) }}" class="hover:underline">{{ $featured->title }}</a></h2>
                    <p class="text-base text-zinc-600">{{ $featured->summary }}</p>
                    <a href="{{ route('news.show', $featured->slug) }}" class="btn-pill-dark self-start">Đọc bài</a>
                </div>
            </article>
        @endif

        <div @class(['grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4', 'mt-16 border-t border-zinc-200 pt-12' => $featured !== null && $others->isNotEmpty(), 'mt-10' => $featured === null])>
            @foreach ($others as $article)
                <article class="group flex flex-col gap-3" data-news-card>
                    <a href="{{ route('news.show', $article->slug) }}" class="block aspect-[4/3] overflow-hidden bg-zinc-100">
                        <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                    </a>
                    <p class="text-xs text-zinc-500">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                    <h2 class="text-xl font-semibold leading-snug"><a href="{{ route('news.show', $article->slug) }}" class="hover:underline">{{ $article->title }}</a></h2>
                    <p class="line-clamp-3 text-sm text-zinc-600">{{ $article->summary }}</p>
                </article>
            @endforeach
        </div>
        @if ($articles->isEmpty())
            <p class="py-20 text-center text-zinc-500">Chưa có bài viết nào.</p>
        @endif
        <div class="mt-12"><x-ui.pagination :paginator="$articles" /></div>
    </section>
@endsection
