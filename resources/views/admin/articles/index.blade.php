@extends('layouts.admin')
@section('title', 'Quản Lý Bài Viết')
@section('eyebrow', 'QUẢN TRỊ NỘI DUNG')
@php($tz = (string) config('store.business_timezone'))
@section('actions')
    <a href="{{ route('news.index') }}" class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Xem trang bài viết</a>
    <a href="{{ route('admin.articles.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Viết bài mới</a>
@endsection
@section('content')
    <div class="space-y-6">
        <div class="bg-surface-card p-4 rounded-2xl border border-border mb-6 flex flex-col md:flex-row items-center justify-between gap-4 text-xs shadow-sm">
            <form method="GET" action="{{ route('admin.articles.index') }}" class="flex flex-wrap items-center gap-3 w-full">
                <label class="flex items-center gap-2 text-xs font-bold text-main">Trạng thái
                    <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="status">
                        <option value="">Tất cả</option>
                        @foreach (App\Enums\ArticleStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-xs font-bold text-main">Sắp xếp
                    <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="sort">
                        <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Mới cập nhật</option>
                        <option value="helpful" @selected(($filters['sort'] ?? '') === 'helpful')>Hữu ích nhất</option>
                        <option value="unhelpful" @selected(($filters['sort'] ?? '') === 'unhelpful')>Không hữu ích nhất</option>
                    </select>
                </label>
                <button type="submit" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm">Lọc</button>
            </form>
        </div>

        <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-muted border-collapse">
                    <thead class="bg-surface text-main font-bold border-b border-border">
                        <tr>
                            <th class="p-4">Bài viết</th>
                            <th class="p-4 text-center">Trạng thái</th>
                            <th class="p-4">Ngày đăng</th>
                            <th class="p-4">Người đọc đánh giá</th>
                            <th class="p-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-medium">
                    @forelse ($articles as $article)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $article->coverUrl() }}" alt="{{ $article->title }}" class="h-12 w-16 shrink-0 rounded-xl object-cover border border-border bg-surface" loading="lazy" decoding="async" width="64" height="48">
                                    <div class="min-w-0">
                                        <strong class="font-bold text-main text-sm block">{{ $article->title }}</strong>
                                        <span class="block text-[11px] text-muted font-mono mt-0.5">/news/{{ $article->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span @class(['badge', 'bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]' => $article->status === App\Enums\ArticleStatus::Published, 'bg-muted/15 text-muted px-2.5 py-0.5 rounded-full font-bold text-[11px]' => $article->status !== App\Enums\ArticleStatus::Published])>{{ $article->status->label() }}</span>
                            </td>
                            <td class="p-4 whitespace-nowrap text-muted text-xs">{{ $article->published_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                            @php($votes = $article->helpful_count + $article->unhelpful_count)
                            <td class="p-4 whitespace-nowrap text-xs" data-article-votes>
                                @if ($votes === 0)
                                    <span class="text-muted/60">Chưa có</span>
                                @else
                                    <span class="font-bold text-main"><span aria-hidden="true">👍</span> {{ $article->helpful_count }} · <span aria-hidden="true">👎</span> {{ $article->unhelpful_count }}</span>
                                    <span class="block text-[11px] text-muted">{{ round($article->helpful_count / $votes * 100) }}% thấy hữu ích</span>
                                @endif
                            </td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold text-xs inline-block transition-colors" href="{{ route('admin.articles.edit', $article) }}">Sửa</a>
                                    @if ($article->status === App\Enums\ArticleStatus::Published)
                                        <a class="px-2.5 py-1 text-muted hover:text-main rounded-lg font-bold text-xs inline-block transition-colors" href="{{ route('news.show', $article->slug) }}" target="_blank">Xem</a>
                                    @endif
                                    @if ($article->status !== App\Enums\ArticleStatus::Hidden)
                                        <form method="POST" action="{{ route('admin.articles.hide', $article) }}" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-2.5 py-1 text-error hover:bg-error-bg rounded-lg font-bold text-xs transition-colors">Ẩn</button>
                                        </form>
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
