@extends('layouts.admin')
@section('title', $article->exists ? 'Sửa bài viết' : 'Viết bài mới')
@section('eyebrow', 'Quản lý bài viết')
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
            <label for="article-body" class="admin-label" id="article-body-label">Nội dung <span class="text-red-500">*</span></label>
            <div class="overflow-hidden rounded-md border border-zinc-300 bg-white focus-within:border-zinc-900">
                <div x-cloak x-show="ready" class="flex flex-wrap items-center gap-1 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5" role="toolbar" aria-label="Định dạng nội dung">
                    @foreach ([
                        ['tool' => 'bold', 'label' => 'B', 'title' => 'In đậm (Ctrl+B)', 'class' => 'font-bold'],
                        ['tool' => 'italic', 'label' => 'I', 'title' => 'In nghiêng (Ctrl+I)', 'class' => 'italic font-serif'],
                        ['tool' => 'h2', 'label' => 'H2', 'title' => 'Tiêu đề mục', 'class' => 'font-semibold'],
                        ['tool' => 'h3', 'label' => 'H3', 'title' => 'Tiêu đề nhỏ', 'class' => 'font-semibold text-xs'],
                        ['tool' => 'bulletList', 'label' => '• Danh sách', 'title' => 'Danh sách gạch đầu dòng', 'class' => ''],
                        ['tool' => 'orderedList', 'label' => '1. Danh sách', 'title' => 'Danh sách đánh số', 'class' => ''],
                        ['tool' => 'blockquote', 'label' => '❝ Trích dẫn', 'title' => 'Trích dẫn', 'class' => ''],
                    ] as $tool)
                        <button type="button" title="{{ $tool['title'] }}" aria-label="{{ $tool['title'] }}" x-on:mousedown.prevent x-on:click="run('{{ $tool['tool'] }}')"
                                x-bind:aria-pressed="active.{{ $tool['tool'] }}.toString()" x-bind:class="active.{{ $tool['tool'] }} ? 'bg-zinc-900 text-white hover:bg-zinc-800' : 'text-zinc-700 hover:bg-zinc-200'"
                                @class(['rounded px-2.5 py-1 text-sm transition', $tool['class']])>{{ $tool['label'] }}</button>
                    @endforeach
                    <span class="mx-1 h-5 w-px bg-zinc-300" aria-hidden="true"></span>
                    <button type="button" title="Hoàn tác (Ctrl+Z)" aria-label="Hoàn tác (Ctrl+Z)" x-on:mousedown.prevent x-on:click="run('undo')" x-bind:disabled="!canUndo" class="rounded px-2.5 py-1 text-sm text-zinc-700 transition hover:bg-zinc-200 disabled:opacity-30">↶</button>
                    <button type="button" title="Làm lại (Ctrl+Y)" aria-label="Làm lại (Ctrl+Y)" x-on:mousedown.prevent x-on:click="run('redo')" x-bind:disabled="!canRedo" class="rounded px-2.5 py-1 text-sm text-zinc-700 transition hover:bg-zinc-200 disabled:opacity-30">↷</button>
                </div>
                <div x-ref="editor" x-cloak x-show="ready" aria-labelledby="article-body-label"></div>
                {{-- Without JavaScript the plain field still works; with it, the editor writes its markup here. --}}
                <textarea id="article-body" name="body" rows="16" maxlength="20000" required x-ref="body" x-show="!ready"
                          class="block w-full border-0 px-3 py-2 font-[inherit] focus:outline-none focus:ring-0">{{ old('body', $article->body) }}</textarea>
            </div>
            <p class="mt-1 text-xs text-zinc-500">Định dạng bằng thanh công cụ hoặc gõ nhanh: "## " cho tiêu đề, "- " cho danh sách, "> " cho trích dẫn. Dán từ Word vẫn giữ đậm, nghiêng, tiêu đề, danh sách.</p>
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
