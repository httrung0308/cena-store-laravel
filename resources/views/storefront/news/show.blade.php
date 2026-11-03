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
@endsection
