@extends('layouts.store')

@section('title', $article->title)

@php
    $tz = (string) config('store.business_timezone');
@endphp

@section('content')
    <article class="mx-auto max-w-4xl px-2 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('news.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-muted transition-colors hover:text-accent">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                <span>Tất cả bài viết</span>
            </a>

            <header class="mt-6 space-y-4">
                <time class="block text-xs font-semibold uppercase tracking-wider text-muted">
                    {{ $article->published_at->setTimezone($tz)->format('d/m/Y') }}
                </time>
                <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-main sm:text-4xl lg:text-5xl break-words">
                    {{ $article->title }}
                </h1>
                @if ($article->summary)
                    <p class="text-base sm:text-lg text-muted leading-relaxed">{{ $article->summary }}</p>
                @endif
            </header>
        </div>

        <div class="mt-8 aspect-[16/9] w-full overflow-hidden rounded-2xl border border-border bg-surface shadow-card">
            <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" class="h-full w-full object-cover" loading="eager" decoding="async">
        </div>

        <div class="article-body mx-auto mt-10 max-w-3xl text-base leading-relaxed text-main break-words" data-article-body>{{ App\Support\ArticleFormatter::toHtml($article->body) }}</div>

        <section id="article-feedback"
                 x-data="articleFeedback({
                     action: @js(route('news.feedback', $article->slug)),
                     initialAnswer: @js($myFeedback),
                     initialHelpfulCount: @js($helpfulCount),
                     initialSaved: @js((bool) session('feedback_saved')),
                 })"
                 class="mx-auto mt-14 max-w-3xl rounded-2xl border border-border bg-surface-card p-6 text-center shadow-card sm:p-8"
                 aria-labelledby="feedback-title"
                 data-article-feedback>
            <h2 id="feedback-title" class="text-lg font-bold text-main">Bài viết có hữu ích không?</h2>

            <form method="POST" action="{{ route('news.feedback', $article->slug) }}" class="mt-4 flex flex-wrap justify-center gap-3">
                @csrf
                @foreach (['yes' => ['👍', 'Có', true], 'no' => ['👎', 'Không', false]] as $value => [$icon, $label, $isHelpful])
                    <button type="submit"
                            name="helpful"
                            value="{{ $value }}"
                            @click.prevent="submit('{{ $value }}')"
                            :disabled="busy"
                            aria-pressed="{{ $myFeedback === $isHelpful ? 'true' : 'false' }}"
                            :aria-pressed="(myFeedback === {{ $isHelpful ? 'true' : 'false' }}).toString()"
                            :class="myFeedback === {{ $isHelpful ? 'true' : 'false' }}
                                ? 'bg-accent text-accent-text shadow-sm border-accent'
                                : 'border-border bg-surface text-main hover:border-accent hover:text-accent shadow-subtle'"
                            @class([
                                'inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-xl border px-5 py-2.5 text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60',
                                'border-accent bg-accent text-accent-text shadow-sm' => $myFeedback === $isHelpful,
                                'border-border bg-surface text-main hover:border-accent hover:text-accent shadow-subtle' => $myFeedback !== $isHelpful,
                            ])>
                        <span x-show="pendingChoice !== '{{ $value }}'" aria-hidden="true">{{ $icon }}</span>
                        <span x-cloak x-show="pendingChoice === '{{ $value }}'" class="inline-flex"><x-ui.spinner :compact="true" /></span>
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </form>

            <div class="mt-4 space-y-1.5 text-xs" aria-live="polite">
                <p x-cloak x-show="errorMessage" role="alert" class="font-medium text-error" x-text="errorMessage"></p>

                <p class="text-muted">
                    <span x-text="statusText">
                        @if (session('feedback_saved'))
                            Cảm ơn bạn đã góp ý!
                        @elseif ($myFeedback !== null)
                            Bạn đã trả lời. Bấm nút còn lại nếu muốn đổi ý.
                        @endif
                    </span>
                    <span x-show="helpfulCount > 0"
                          @if ($helpfulCount <= 0) style="display: none;" @endif
                          class="mt-1 block font-medium text-main"
                          x-text="helpfulCount + ' người thấy bài viết này hữu ích.'">
                        @if ($helpfulCount > 0)
                            {{ $helpfulCount }} người thấy bài viết này hữu ích.
                        @endif
                    </span>
                </p>
            </div>
        </section>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-14 max-w-7xl border-t border-border pt-12" aria-labelledby="related-title" data-related-articles>
            <div class="flex items-baseline justify-between gap-4">
                <h2 id="related-title" class="text-2xl font-bold text-main">Bài viết khác</h2>
                <a href="{{ route('news.index') }}" class="text-sm font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Xem tất cả →</a>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 sm:gap-8">
                @foreach ($related as $other)
                    <article class="group flex flex-col gap-3 rounded-2xl border border-border bg-surface-card p-4 shadow-subtle transition-[box-shadow,border-color] duration-200 hover:border-accent/40 hover:shadow-card">
                        <a href="{{ route('news.show', $other->slug) }}" class="block aspect-[4/3] overflow-hidden rounded-xl border border-border/60 bg-surface">
                            <img src="{{ $other->coverUrl() }}" alt="{{ $other->title }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        </a>
                        <time class="text-xs font-medium text-muted">{{ $other->published_at->setTimezone($tz)->format('d/m/Y') }}</time>
                        <h3 class="text-base font-bold leading-snug text-main line-clamp-2 break-words">
                            <a href="{{ route('news.show', $other->slug) }}" class="hover:text-accent hover:underline transition-colors">{{ $other->title }}</a>
                        </h3>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
