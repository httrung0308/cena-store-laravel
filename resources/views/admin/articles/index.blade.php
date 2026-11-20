@extends('layouts.admin')
@section('title', 'Quản Lý Bài Viết')
@section('eyebrow', 'QUẢN TRỊ NỘI DUNG')
@php
    $tz = (string) config('store.business_timezone');
    $search = (string) ($search ?? ($filters['search'] ?? ''));
    $statusFilter = (string) ($filters['status'] ?? '');
    $sortFilter = (string) ($filters['sort'] ?? 'latest');
    $statusLabels = collect(App\Enums\ArticleStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]);
    $sortLabels = [
        'latest' => 'Mới cập nhật',
        'helpful' => 'Hữu ích nhất',
        'unhelpful' => 'Không hữu ích nhất',
    ];
    $hasActiveQuery = filled($search) || filled($statusFilter) || $sortFilter !== 'latest';
    $activeChips = [];
    if (filled($search)) {
        $activeChips[] = [
            'label' => 'Từ khóa: "'.$search.'"',
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
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
        <x-admin.search-form
            :action="route('admin.articles.index')"
            :ajax="true"
            id="article-search-input"
            label="Tìm bài viết"
            placeholder="Tìm theo tiêu đề bài viết..."
            :value="$search"
            :active-chips="$activeChips"
        >
            <x-slot:filters>
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
            </x-slot:filters>
        </x-admin.search-form>

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
                            <th class="p-4 text-left whitespace-nowrap">Trạng thái</th>
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
                            <td class="p-4 text-left whitespace-nowrap">
                                @php($isPublished = $article->status === App\Enums\ArticleStatus::Published)
                                <x-admin.status-switch
                                    :active="$isPublished"
                                    :action="$isPublished ? route('admin.articles.hide', $article) : route('admin.articles.publish', $article)"
                                    :toggle-key="'article-'.$article->id"
                                    active-label="Hiện bài"
                                    inactive-label="Ẩn bài"
                                    :action-label="($isPublished ? 'Ẩn bài ' : 'Hiện bài ').$article->title"
                                />
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
                        <tr>
                            <td colspan="5" class="p-12 text-center text-xs text-muted">
                                @if ($hasActiveQuery)
                                    Không tìm thấy bài viết nào phù hợp với từ khóa hoặc bộ lọc hiện tại.
                                @else
                                    Chưa có bài viết.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$articles" />
        </div>
    </div>
@endsection
