@extends('layouts.store')

@section('title', $article->title)

@php
    $tz = (string) config('store.business_timezone');
    $paragraphs = preg_split('/\R{2,}/', trim($article->body)) ?: [];
@endphp

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-10 sm:px-8">
        <a href="{{ route('news.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Tin tức</a>
        <p class="mt-6 text-sm text-zinc-500">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-5xl">{{ $article->title }}</h1>
        <p class="mt-4 text-lg text-zinc-600">{{ $article->summary }}</p>
        <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" class="mt-8 aspect-[16/9] w-full object-cover">
        <div class="mt-8 space-y-5 text-base leading-relaxed text-zinc-800" data-article-body>
            @foreach ($paragraphs as $paragraph)
                <p class="whitespace-pre-line">{{ $paragraph }}</p>
            @endforeach
        </div>
    </article>
    @if ($related->isNotEmpty())
        <section class="border-t border-zinc-200 px-4 py-12 sm:px-10 xl:px-24 2xl:px-48" aria-labelledby="related-title" data-related-articles>
            <div class="flex items-baseline justify-between gap-4">
                <h2 id="related-title" class="text-2xl font-bold">Bài viết khác</h2>
                <a href="{{ route('news.index') }}" class="text-sm font-semibold text-zinc-600 hover:text-zinc-900">Xem tất cả →</a>
            </div>
            <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $other)
                    <article class="group flex flex-col gap-3">
                        <a href="{{ route('news.show', $other->slug) }}" class="block aspect-[4/3] overflow-hidden bg-zinc-100">
                            <img src="{{ $other->coverUrl() }}" alt="{{ $other->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        </a>
                        <p class="text-xs text-zinc-500">{{ $other->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                        <h3 class="text-lg font-semibold leading-snug"><a href="{{ route('news.show', $other->slug) }}" class="hover:underline">{{ $other->title }}</a></h3>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
