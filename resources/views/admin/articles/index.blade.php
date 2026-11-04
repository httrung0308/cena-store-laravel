@extends('layouts.admin')
@section('title', 'Tin tức')
@section('eyebrow', 'Quản lý tin tức')
@php($tz = (string) config('store.business_timezone'))
@section('actions')
    <a href="{{ route('news.index') }}" class="btn-pill">Xem trang tin tức</a>
    <a href="{{ route('admin.articles.create') }}" class="btn-pill-dark">+ Viết bài mới</a>
@endsection
@section('content')
    <div class="space-y-6">
        <form method="GET" action="{{ route('admin.articles.index') }}" class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">Trạng thái
                <select class="input border-zinc-300 py-2 font-normal" name="status">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\ArticleStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">Sắp xếp
                <select class="input border-zinc-300 py-2 font-normal" name="sort">
                    <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Mới cập nhật</option>
                    <option value="helpful" @selected(($filters['sort'] ?? '') === 'helpful')>Hữu ích nhất</option>
                    <option value="unhelpful" @selected(($filters['sort'] ?? '') === 'unhelpful')>Không hữu ích nhất</option>
                </select>
            </label>
            <button type="submit" class="btn-pill-dark">Lọc</button>
        </form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Bài viết</th><th>Trạng thái</th><th>Ngày đăng</th><th>Người đọc đánh giá</th><th>Thao tác</th></tr></thead><tbody>
            @forelse ($articles as $article)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <img src="{{ $article->coverUrl() }}" alt="" class="h-12 w-16 shrink-0 rounded-lg object-cover">
                            <div class="min-w-0"><span class="block font-semibold text-zinc-900">{{ $article->title }}</span><span class="block text-xs text-zinc-500">/news/{{ $article->slug }}</span></div>
                        </div>
                    </td>
                    <td><span @class(['badge', 'bg-green-100 text-green-600' => $article->status === App\Enums\ArticleStatus::Published, 'bg-zinc-100 text-zinc-600' => $article->status !== App\Enums\ArticleStatus::Published])>{{ $article->status->label() }}</span></td>
                    <td class="whitespace-nowrap">{{ $article->published_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                    @php($votes = $article->helpful_count + $article->unhelpful_count)
                    <td class="whitespace-nowrap" data-article-votes>
                        @if ($votes === 0)
                            <span class="text-zinc-400">Chưa có</span>
                        @else
                            <span class="font-semibold text-zinc-900"><span aria-hidden="true">👍</span> {{ $article->helpful_count }} · <span aria-hidden="true">👎</span> {{ $article->unhelpful_count }}</span>
                            <span class="block text-xs text-zinc-500">{{ round($article->helpful_count / $votes * 100) }}% thấy hữu ích</span>
                        @endif
                    </td>
                    <td><div class="flex flex-wrap gap-2">
                        <a class="btn-pill" href="{{ route('admin.articles.edit', $article) }}">Sửa</a>
                        @if ($article->status === App\Enums\ArticleStatus::Published)
                            <a class="btn-pill" href="{{ route('news.show', $article->slug) }}">Xem</a>
                        @endif
                        @if ($article->status !== App\Enums\ArticleStatus::Hidden)
                            <form action="{{ route('admin.articles.hide', $article) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="btn-pill">Ẩn bài</button></form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td class="py-20 text-center text-zinc-500" colspan="5">Chưa có bài viết.</td></tr>
            @endforelse
        </tbody></table></div>
        <x-ui.pagination :paginator="$articles" />
    </div>
@endsection
