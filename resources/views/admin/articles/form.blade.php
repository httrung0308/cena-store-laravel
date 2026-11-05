@extends('layouts.admin')
@section('title', $article->exists ? 'Sửa bài viết' : 'Viết bài mới')
@section('eyebrow', 'Quản lý tin tức')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.articles.index') }}">Quay lại danh sách</a>
@endsection
@php
    $isHidden = $article->status === App\Enums\ArticleStatus::Hidden;
    $status = old('status', $article->exists ? $article->status->value : 'DRAFT');
@endphp
@section('content')
    <form action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}" method="POST" enctype="multipart/form-data" class="admin-card max-w-3xl space-y-5">
        @csrf
        @if ($article->exists) @method('PUT') @endif
        <x-admin.field name="title" label="Tiêu đề" :value="$article->title" maxlength="200" required :hint="$article->exists ? 'Đường dẫn giữ nguyên: /news/'.$article->slug : 'Đường dẫn (slug) được tạo tự động từ tiêu đề.'" />
        <div>
            <label for="article-summary" class="admin-label">Tóm tắt <span class="text-red-500">*</span></label>
            <textarea id="article-summary" name="summary" rows="2" maxlength="500" required class="input w-full border-zinc-300">{{ old('summary', $article->summary) }}</textarea>
            @error('summary')<p role="alert" class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div x-data="articleEditor()" data-article-editor>
            <label for="article-body" class="admin-label">Nội dung <span class="text-red-500">*</span></label>
            <div class="overflow-hidden rounded-md border border-zinc-300 focus-within:border-zinc-900">
                <div class="flex flex-wrap items-center gap-1 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5" role="toolbar" aria-label="Định dạng nội dung" aria-controls="article-body">
                    @foreach ([
                        ['label' => 'B', 'title' => 'In đậm (Ctrl+B)', 'action' => "wrap('**', '**', 'chữ đậm')", 'class' => 'font-bold'],
                        ['label' => 'I', 'title' => 'In nghiêng (Ctrl+I)', 'action' => "wrap('*', '*', 'chữ nghiêng')", 'class' => 'italic'],
                        ['label' => 'H2', 'title' => 'Tiêu đề mục', 'action' => "prefix('## ')", 'class' => 'font-semibold'],
                        ['label' => 'H3', 'title' => 'Tiêu đề nhỏ', 'action' => "prefix('### ')", 'class' => 'font-semibold text-xs'],
                        ['label' => '• Danh sách', 'title' => 'Danh sách gạch đầu dòng', 'action' => "prefix('- ')", 'class' => ''],
                        ['label' => '1. Danh sách', 'title' => 'Danh sách đánh số', 'action' => "prefix('1. ')", 'class' => ''],
                        ['label' => '❝ Trích dẫn', 'title' => 'Trích dẫn', 'action' => "prefix('> ')", 'class' => ''],
                    ] as $tool)
                        <button type="button" title="{{ $tool['title'] }}" aria-label="{{ $tool['title'] }}" x-on:click="{{ $tool['action'] }}" x-bind:disabled="previewing"
                                @class(['rounded px-2.5 py-1 text-sm text-zinc-700 transition hover:bg-zinc-200 disabled:opacity-40', $tool['class']])>{{ $tool['label'] }}</button>
                    @endforeach
                    <button type="button" class="ml-auto rounded-full border border-zinc-300 bg-white px-3 py-1 text-xs font-semibold transition hover:border-zinc-900"
                            x-on:click="togglePreview()" x-bind:aria-pressed="previewing.toString()" x-text="previewing ? 'Tiếp tục viết' : 'Xem trước'">Xem trước</button>
                </div>
                <textarea id="article-body" name="body" rows="16" maxlength="20000" required x-ref="body" x-show="!previewing" x-on:keydown="shortcut($event)"
                          class="block w-full border-0 px-3 py-2 font-[inherit] focus:outline-none focus:ring-0">{{ old('body', $article->body) }}</textarea>
                <div x-cloak x-show="previewing" class="article-body min-h-[20rem] px-4 py-3 text-base leading-relaxed text-zinc-800" x-html="preview" aria-live="polite"></div>
            </div>
            <p class="mt-1 text-xs text-zinc-500">Bôi đen chữ rồi bấm nút để định dạng. Để một dòng trống giữa các đoạn. Không dùng thẻ HTML.</p>
            @error('body')<p role="alert" class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="article-cover" class="admin-label">Ảnh bìa @unless ($article->exists)<span class="text-red-500">*</span>@endunless</label>
            @if ($article->cover_path)
                <img src="{{ $article->coverUrl() }}" alt="Ảnh bìa hiện tại" class="mb-2 h-32 rounded-2xl object-cover">
            @endif
            <input id="article-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" @unless ($article->exists) required @endunless class="block text-sm">
            <p class="mt-1 text-xs text-zinc-500">JPEG, PNG hoặc WebP, tối đa 5 MB.@if ($article->exists) Bỏ trống để giữ ảnh cũ.@endif</p>
            @error('cover')<p role="alert" class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </div>
        <fieldset>
            <legend class="admin-label">Trạng thái</legend>
            <div class="flex flex-wrap gap-4">
                @if ($isHidden)
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="radio" name="status" value="HIDDEN" class="accent-black" @checked($status === 'HIDDEN')> Giữ ẩn</label>
                @endif
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="radio" name="status" value="DRAFT" class="accent-black" @checked($status === 'DRAFT')> Nháp</label>
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="radio" name="status" value="PUBLISHED" class="accent-black" @checked($status === 'PUBLISHED')> {{ $article->published_at ? 'Đăng' : 'Đăng ngay' }}</label>
            </div>
            @if ($isHidden)<p class="mt-1 text-xs text-zinc-500">Bài đang ẩn. Chọn "Đăng" để hiện lại cho khách.</p>@endif
            @error('status')<p role="alert" class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
        </fieldset>
        <button type="submit" class="btn-pill-dark">Lưu bài viết</button>
    </form>
@endsection
