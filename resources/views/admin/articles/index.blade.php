@extends('layouts.admin')
@section('title', 'Quản Lý Bài Viết')
@section('eyebrow', 'QUẢN TRỊ NỘI DUNG')
@php
    $tz = (string) config('store.business_timezone');
    $statusFilter = (string) ($filters['status'] ?? '');
    $sortFilter = (string) ($filters['sort'] ?? 'latest');
    $statusLabels = collect(App\Enums\ArticleStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]);
    $sortLabels = [
        'latest' => 'Mới cập nhật',
        'helpful' => 'Hữu ích nhất',
        'unhelpful' => 'Không hữu ích nhất',
    ];
    $hasActiveQuery = filled($statusFilter) || $sortFilter !== 'latest';
    $activeChips = [];
    if (filled($statusFilter)) {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($statusLabels[$statusFilter] ?? $statusFilter),
            'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null]),
        ];
    }
    if ($sortFilter !== 'latest') {
        $activeChips[] = [
            'label' => 'Sắp xếp: '.($sortLabels[$sortFilter] ?? $sortFilter),
            'url' => request()->fullUrlWithQuery(['sort' => null, 'page' => null]),
        ];
    }
@endphp
@section('actions')
    <a href="{{ route('news.index') }}" class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Xem trang bài viết</a>
    <a href="{{ route('admin.articles.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Viết bài mới</a>
@endsection
@section('content')
    <div x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6">
        <div class="bg-surface-card p-4 rounded-2xl border border-border mb-6 space-y-2.5 text-xs shadow-sm">
            <form id="article-filter-form" method="GET" action="{{ route('admin.articles.index') }}" @submit.prevent="submitFilter($event.target)" class="flex flex-wrap items-center justify-between gap-3 px-3.5 py-2 rounded-xl bg-surface/80 border border-dashed border-border w-full">
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <x-admin.filter-select id="article-status-filter" name="status" label="Trạng thái:" :active="filled($statusFilter)">
                        <option value="">Tất cả</option>
                        @foreach (App\Enums\ArticleStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-admin.filter-select>

                    <x-admin.filter-select id="article-sort-filter" name="sort" label="Sắp xếp:" :active="$sortFilter !== 'latest'">
                        <option value="latest" @selected($sortFilter === 'latest')>Mới cập nhật</option>
                        <option value="helpful" @selected($sortFilter === 'helpful')>Hữu ích nhất</option>
                        <option value="unhelpful" @selected($sortFilter === 'unhelpful')>Không hữu ích nhất</option>
                    </x-admin.filter-select>
                </div>

                <button type="submit" class="px-4 py-1.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-lg transition-colors shadow-sm shrink-0">Lọc</button>
            </form>

            @if (! empty($activeChips))
                <div id="admin-filter-chips" class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-border/60">
                    <span class="text-[11px] font-bold text-muted mr-0.5">Đang lọc:</span>
                    @foreach ($activeChips as $chip)
                        <a href="{{ $chip['url'] }}"
                           @click.prevent="visitUrl('{{ $chip['url'] }}')"
                           aria-label="Bỏ điều kiện {{ $chip['label'] }}"
                           class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-0.5 rounded-full bg-accent/10 text-accent border border-accent/25 text-[11px] font-semibold hover:bg-accent/15 transition-colors">
                            <span>{{ $chip['label'] }}</span>
                            <span aria-hidden="true" class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full hover:bg-accent/20 font-bold">×</span>
                        </a>
                    @endforeach
                    <a href="{{ route('admin.articles.index') }}"
                       @click.prevent="resetFilters('{{ route('admin.articles.index') }}', document.getElementById('article-filter-form'))"
                       class="ml-auto text-[11px] font-bold text-muted hover:text-error underline transition-colors">
                        Xóa tất cả
                    </a>
                </div>
            @endif
        </div>

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none': listLoading }" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm transition-opacity">
            <div class="px-4 py-3 border-b border-border/70 bg-surface/40 flex items-center justify-between text-xs text-muted">
                <span>Tìm thấy <strong class="text-main font-bold">{{ number_format($articles->total()) }}</strong> mục</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-muted border-collapse">
                    <thead class="bg-surface text-main font-bold border-b border-border">
                        <tr>
                            <th class="p-4">Bài viết</th>
                            <th class="p-4 text-center whitespace-nowrap">Trạng thái</th>
                            <th class="p-4 whitespace-nowrap">Ngày đăng</th>
                            <th class="p-4 whitespace-nowrap">Người đọc đánh giá</th>
                            <th class="p-4 text-right whitespace-nowrap">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-medium">
                    @forelse ($articles as $article)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4 max-w-[20rem]">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" class="h-12 w-16 shrink-0 rounded-xl object-cover border border-border bg-surface" loading="lazy" decoding="async" width="64" height="48">
                                    <div class="min-w-0">
                                        <strong class="font-bold text-main text-sm block break-words">{{ $article->title }}</strong>
                                        <span class="block text-[11px] text-muted font-mono mt-0.5 truncate">/news/{{ $article->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center whitespace-nowrap">
                                <div class="inline-flex flex-col items-center gap-1.5">
                                    <span @class([
                                        'inline-flex items-center gap-1 whitespace-nowrap px-2.5 py-0.5 rounded-full font-bold text-[11px]',
                                        'bg-success-bg text-success border border-success/30' => $article->status === App\Enums\ArticleStatus::Published,
                                        'bg-muted/15 text-muted border border-border' => $article->status !== App\Enums\ArticleStatus::Published,
                                    ])>
                                        <span @class(['w-1.5 h-1.5 rounded-full', 'bg-success' => $article->status === App\Enums\ArticleStatus::Published, 'bg-muted' => $article->status !== App\Enums\ArticleStatus::Published]) aria-hidden="true"></span>
                                        {{ $article->status->label() }}
                                    </span>

                                    @if ($article->status === App\Enums\ArticleStatus::Published)
                                        <form method="POST" action="{{ route('admin.articles.hide', $article) }}" @submit.prevent="submitToggle($event, 'article-{{ $article->id }}')" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                </svg>
                                                <span>Ẩn bài</span>
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.articles.publish', $article) }}" @submit.prevent="submitToggle($event, 'article-{{ $article->id }}')" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>Hiện bài</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap text-muted text-xs">{{ $article->published_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                            @php($votes = $article->helpful_count + $article->unhelpful_count)
                            <td class="p-4 whitespace-nowrap text-xs" data-article-votes>
                                @if ($votes === 0)
                                    <span class="text-muted/60">Chưa có</span>
                                @else
                                    <span class="inline-flex items-center gap-2 font-bold text-main">
                                        <span class="inline-flex items-center gap-1 text-success">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.5c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 012.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 00.322-1.672V3a.75.75 0 01.75-.75A2.25 2.25 0 0116.5 4.5c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 01-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 00-1.423-.23H5.904M14.25 9h2.25M5.904 18.75c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 01-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 10.203 4.167 9.75 5 9.75h1.053c.472 0 .745.556.5.96a8.958 8.958 0 00-1.302 4.665c0 1.194.232 2.333.654 3.375z" />
                                            </svg>
                                            <span>{{ $article->helpful_count }}</span>
                                        </span>
                                        <span class="text-muted">·</span>
                                        <span class="inline-flex items-center gap-1 text-error">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15h2.25m8.024-9.75c.011.05.028.1.052.148.591 1.2.924 2.55.924 3.977a8.96 8.96 0 01-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368.339 1.11.521 2.287.521 3.507 0 1.553-.295 3.036-.831 4.398-.306.774-1.086 1.227-1.918 1.227h-1.053c-.472 0-.745-.556-.5-.96a8.95 8.95 0 00.303-.54m.023-8.25H16.48a4.5 4.5 0 01-1.423-.23l-3.114-1.04a4.5 4.5 0 00-1.423-.23H6.504c-.618 0-1.217.247-1.605.729A11.95 11.95 0 002.25 12c0 .434.023.863.068 1.285C2.427 14.306 3.346 15 4.372 15h3.126c.618 0 .991.724.725 1.282A7.471 7.471 0 007.5 19.5a2.25 2.25 0 002.25 2.25.75.75 0 00.75-.75v-.633c0-.573.11-1.14.322-1.672.304-.76.93-1.33 1.653-1.715a9.04 9.04 0 002.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384" />
                                            </svg>
                                            <span>{{ $article->unhelpful_count }}</span>
                                        </span>
                                    </span>
                                    <span class="block text-[11px] text-muted mt-0.5">{{ round($article->helpful_count / $votes * 100) }}% thấy hữu ích</span>
                                @endif
                            </td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ route('admin.articles.edit', $article) }}">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                        <span>Sửa</span>
                                    </a>
                                    @if ($article->status === App\Enums\ArticleStatus::Published)
                                        <a class="px-2.5 py-1.5 text-muted hover:text-main hover:bg-surface rounded-lg font-bold text-xs inline-block transition-colors" href="{{ route('news.show', $article->slug) }}" target="_blank" rel="noopener noreferrer">Xem</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-12 text-center text-xs text-muted">Chưa có bài viết.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$articles" />
        </div>
    </div>
@endsection
