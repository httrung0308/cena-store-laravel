@extends('layouts.store')

@section('title', 'Tin tức')

@php($tz = (string) config('store.business_timezone'))

@section('content')
    <section class="px-4 py-10 sm:px-10 xl:px-24 2xl:px-48">
        <div class="space-y-2">
            <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Nike Store</p>
            <h1 class="text-4xl font-bold sm:text-5xl">Tin tức</h1>
            <p class="text-zinc-500">Giày bán chạy, xu hướng, cách chọn size và bảo quản giày.</p>
        </div>
        <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($articles as $article)
                <article class="group flex flex-col gap-3" data-news-card>
                    <a href="{{ route('news.show', $article->slug) }}" class="block aspect-[4/3] overflow-hidden bg-zinc-100">
                        <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                    </a>
                    <p class="text-xs text-zinc-500">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                    <h2 class="text-xl font-semibold leading-snug"><a href="{{ route('news.show', $article->slug) }}" class="hover:underline">{{ $article->title }}</a></h2>
                    <p class="line-clamp-3 text-sm text-zinc-600">{{ $article->summary }}</p>
                </article>
            @empty
                <p class="col-span-full py-20 text-center text-zinc-500">Chưa có bài viết nào.</p>
            @endforelse
        </div>
        <div class="mt-10"><x-ui.pagination :paginator="$articles" /></div>
    </section>
@endsection
