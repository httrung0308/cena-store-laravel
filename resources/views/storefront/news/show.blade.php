@extends('layouts.store')

@section('title', $article->title)

@php
    $tz = (string) config('store.business_timezone');
@endphp

@section('content')
    <article class="mx-auto max-w-4xl px-4 py-8 sm:px-8 sm:py-12">
        <a href="{{ route('news.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-muted transition-colors hover:text-accent">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            <span>Bài viết</span>
        </a>
        <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-muted">{{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
        <h1 class="mt-3 text-3xl font-extrabold leading-tight text-main sm:text-5xl tracking-tight">{{ $article->title }}</h1>
        <p class="mt-5 text-lg text-muted leading-relaxed">{{ $article->summary }}</p>
        <div class="mt-8 aspect-[16/9] w-full overflow-hidden rounded-2xl border border-border bg-surface shadow-card">
            <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
        </div>
        <div class="article-body mt-10 text-base leading-relaxed text-main" data-article-body>{{ App\Support\ArticleFormatter::toHtml($article->body) }}</div>

        <section id="article-feedback" class="mt-14 rounded-2xl border border-border bg-surface-card p-6 text-center shadow-card sm:p-8" aria-labelledby="feedback-title" data-article-feedback>
            <h2 id="feedback-title" class="text-lg font-bold text-main">Bài viết có hữu ích không?</h2>
            <form method="POST" action="{{ route('news.feedback', $article->slug) }}" class="mt-4 flex justify-center gap-3">
                @csrf
                @foreach (['yes' => ['👍', 'Có', true], 'no' => ['👎', 'Không', false]] as $value => [$icon, $label, $isHelpful])
                    <button type="submit" name="helpful" value="{{ $value }}" aria-pressed="{{ $myFeedback === $isHelpful ? 'true' : 'false' }}"
                            @class([
                                'inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition-colors duration-150',
                                'bg-accent text-accent-text shadow-sm' => $myFeedback === $isHelpful,
                                'border border-border bg-surface text-main hover:border-accent hover:text-accent shadow-subtle' => $myFeedback !== $isHelpful,
                            ])>
                        <span aria-hidden="true">{{ $icon }}</span> {{ $label }}
                    </button>
                @endforeach
            </form>
            <p class="mt-4 text-xs text-muted" aria-live="polite">
                @if (session('feedback_saved'))
                    Cảm ơn bạn đã góp ý!
                @elseif ($myFeedback !== null)
                    Bạn đã trả lời. Bấm nút còn lại nếu muốn đổi ý.
                @endif
                @if ($helpfulCount > 0)
                    <span class="block mt-1 font-medium text-main">{{ $helpfulCount }} người thấy bài viết này hữu ích.</span>
                @endif
            </p>
        </section>
    </article>
    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-7xl border-t border-border mt-16 pt-12" aria-labelledby="related-title" data-related-articles>
            <div class="flex items-baseline justify-between gap-4">
                <h2 id="related-title" class="text-2xl font-bold text-main">Bài viết khác</h2>
                <a href="{{ route('news.index') }}" class="text-sm font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Xem tất cả →</a>
            </div>
            <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $other)
                    <article class="group flex flex-col gap-3 rounded-2xl border border-border bg-surface-card p-4 shadow-subtle transition-shadow duration-200 hover:shadow-card">
                        <a href="{{ route('news.show', $other->slug) }}" class="block aspect-[4/3] overflow-hidden rounded-xl bg-surface border border-border/60">
                            <img src="{{ $other->coverUrl() }}" alt="{{ $other->title }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        </a>
                        <p class="text-xs font-medium text-muted">{{ $other->published_at->setTimezone($tz)->format('d/m/Y') }}</p>
                        <h3 class="text-base font-bold leading-snug text-main"><a href="{{ route('news.show', $other->slug) }}" class="hover:text-accent hover:underline transition-colors">{{ $other->title }}</a></h3>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
