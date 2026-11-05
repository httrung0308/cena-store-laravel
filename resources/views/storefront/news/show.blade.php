@extends('layouts.store')

@section('title', $article->title)

@php
    $tz = (string) config('store.business_timezone');
@endphp

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-10 sm:px-8">
        <a href="{{ route('news.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Tin tức</a>
        <p class="mt-6 text-sm text-zinc-500">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-5xl">{{ $article->title }}</h1>
        <p class="mt-4 text-lg text-zinc-600">{{ $article->summary }}</p>
        <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" class="mt-8 aspect-[16/9] w-full object-cover">
        <div class="article-body mt-8 text-base leading-relaxed text-zinc-800" data-article-body>{{ App\Support\ArticleFormatter::toHtml($article->body) }}</div>

        <section id="article-feedback" class="mt-12 rounded-3xl border border-zinc-200 p-6 text-center" aria-labelledby="feedback-title" data-article-feedback>
            <h2 id="feedback-title" class="text-lg font-semibold">Bài viết có hữu ích không?</h2>
            <form method="POST" action="{{ route('news.feedback', $article->slug) }}" class="mt-4 flex justify-center gap-3">
                @csrf
                @foreach (['yes' => ['👍', 'Có', true], 'no' => ['👎', 'Không', false]] as $value => [$icon, $label, $isHelpful])
                    <button type="submit" name="helpful" value="{{ $value }}" aria-pressed="{{ $myFeedback === $isHelpful ? 'true' : 'false' }}"
                            @class(['inline-flex items-center gap-2 rounded-full border px-5 py-2 text-sm font-semibold transition', 'border-zinc-900 bg-zinc-900 text-white' => $myFeedback === $isHelpful, 'border-zinc-300 bg-white text-zinc-800 hover:border-zinc-900' => $myFeedback !== $isHelpful])>
                        <span aria-hidden="true">{{ $icon }}</span> {{ $label }}
                    </button>
                @endforeach
            </form>
            <p class="mt-3 text-sm text-zinc-500" aria-live="polite">
                @if (session('feedback_saved'))
                    Cảm ơn bạn đã góp ý!
                @elseif ($myFeedback !== null)
                    Bạn đã trả lời. Bấm nút còn lại nếu muốn đổi ý.
                @endif
                @if ($helpfulCount > 0)
                    <span class="block">{{ $helpfulCount }} người thấy bài viết này hữu ích.</span>
                @endif
            </p>
        </section>
    </article>
    @if ($related->isNotEmpty())
        <section class="border-t border-zinc-200 py-12" aria-labelledby="related-title" data-related-articles>
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
