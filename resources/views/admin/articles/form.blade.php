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
        <div>
            <label for="article-body" class="admin-label">Nội dung <span class="text-red-500">*</span></label>
            <textarea id="article-body" name="body" rows="12" maxlength="20000" required class="input w-full border-zinc-300">{{ old('body', $article->body) }}</textarea>
            <p class="mt-1 text-xs text-zinc-500">Chỉ chữ thường, không dùng thẻ HTML. Để một dòng trống giữa các đoạn.</p>
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
