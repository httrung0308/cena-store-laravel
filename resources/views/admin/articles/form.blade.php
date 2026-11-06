@extends('layouts.admin')
@section('title', $article->exists ? 'Sửa Bài Viết' : 'Viết Bài Mới')
@section('eyebrow', 'QUẢN TRỊ NỘI DUNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-all" href="{{ route('admin.articles.index') }}">← Quay lại danh sách</a>
@endsection
@use('App\Enums\ArticleStatus')
@php
    $current = $article->exists ? $article->status : null;
    $tz = (string) config('store.business_timezone');
    // The first submit button keeps the current state, so pressing Enter in a field never publishes by accident.
    $actions = match ($current) {
        ArticleStatus::Published => [['PUBLISHED', 'Cập nhật bài', 'primary'], ['DRAFT', 'Chuyển về nháp', 'secondary']],
        ArticleStatus::Hidden => [['HIDDEN', 'Lưu, vẫn ẩn', 'secondary'], ['PUBLISHED', 'Đăng lại', 'primary'], ['DRAFT', 'Chuyển về nháp', 'link']],
        default => [['DRAFT', 'Lưu nháp', 'secondary'], ['PUBLISHED', 'Đăng bài', 'primary']],
    };
    $badge = match ($current) {
        ArticleStatus::Published => ['Đã đăng', 'bg-success-bg text-success-text border border-success/30'],
        ArticleStatus::Hidden => ['Đã ẩn', 'bg-surface-alt text-muted border border-border'],
        ArticleStatus::Draft => ['Nháp', 'bg-warning-bg text-warning-text border border-warning/30'],
        default => ['Bài mới', 'bg-surface-alt text-muted border border-border'],
    };
@endphp
@section('content')
    <form action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}" method="POST" enctype="multipart/form-data"
          x-data="articleForm(@js(['title' => old('title', $article->title ?? ''), 'summary' => old('summary', $article->summary ?? ''), 'slug' => $article->exists ? $article->slug : null, 'cover' => $article->cover_path ? $article->coverUrl() : null]))"
          x-on:input="dirty = true" x-on:change="dirty = true" x-on:submit="submitting = true"
          class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start" data-article-form>
        @csrf
        @if ($article->exists) @method('PUT') @endif

        <section class="admin-card space-y-6" aria-label="Nội dung bài viết">
            <div>
                <label for="article-title" class="admin-label text-muted">Tiêu đề <span class="text-error">*</span></label>
                {{-- A one-line title that wraps and grows instead of cutting long titles; Enter does not add a line. --}}
                <textarea id="article-title" name="title" rows="1" maxlength="200" required x-model="title" placeholder="Tiêu đề bài viết" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}"
                          x-init="$nextTick(() => { $el.style.height = $el.scrollHeight + 'px' })" x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'" x-on:keydown.enter.prevent
                          class="block w-full resize-none overflow-hidden border-0 border-b border-border bg-transparent px-0 pb-2 text-3xl font-bold leading-tight text-main placeholder:text-muted/40 focus:border-accent focus:outline-none focus:ring-0 sm:text-4xl">{{ old('title', $article->title) }}</textarea>
                <p class="mt-2 flex flex-wrap items-center gap-1 text-xs text-muted">
                    <span aria-hidden="true">🔗</span> Đường dẫn: <span class="font-mono text-main">/news/<span x-text="slug">{{ $article->slug }}</span></span>
                    <span>{{ $article->exists ? '(giữ nguyên khi sửa tiêu đề)' : '(tự tạo từ tiêu đề; nếu trùng sẽ thêm số)' }}</span>
                </p>
                @error('title')<p role="alert" class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="article-summary" class="admin-label text-muted">Tóm tắt <span class="text-error">*</span></label>
                    <span class="text-xs tabular-nums" x-bind:class="summary.length > 450 ? 'text-error' : 'text-muted'" x-text="summary.length + '/500'">{{ mb_strlen((string) old('summary', $article->summary)) }}/500</span>
                </div>
                <textarea id="article-summary" name="summary" rows="2" maxlength="500" required x-model="summary" placeholder="Một hai câu giới thiệu bài, hiện ở danh sách bài viết"
                          class="input w-full border-border bg-surface text-main">{{ old('summary', $article->summary) }}</textarea>
                @error('summary')<p role="alert" class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div x-data="articleEditor()" data-article-editor>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="article-body" class="admin-label text-muted" id="article-body-label">Nội dung <span class="text-error">*</span></label>
                    <span x-cloak x-show="ready" class="text-xs tabular-nums text-muted" x-text="words + ' từ · khoảng ' + minutes + ' phút đọc'"></span>
                </div>
                <div class="rounded-md border border-border bg-surface focus-within:border-accent">
                    <div x-cloak x-show="ready" class="sticky top-0 z-10 flex flex-wrap items-center gap-1 rounded-t-md border-b border-border bg-surface-alt px-2 py-1.5" role="toolbar" aria-label="Định dạng nội dung">
                        @foreach ([
                            ['tool' => 'bold', 'label' => 'B', 'title' => 'In đậm (Ctrl+B)', 'class' => 'font-bold'],
                            ['tool' => 'italic', 'label' => 'I', 'title' => 'In nghiêng (Ctrl+I)', 'class' => 'italic font-bold'],
                            ['tool' => 'h2', 'label' => 'H2', 'title' => 'Tiêu đề mục', 'class' => 'font-semibold'],
                            ['tool' => 'h3', 'label' => 'H3', 'title' => 'Tiêu đề nhỏ', 'class' => 'font-semibold text-xs'],
                            ['tool' => 'bulletList', 'label' => '• Danh sách', 'title' => 'Danh sách gạch đầu dòng', 'class' => ''],
                            ['tool' => 'orderedList', 'label' => '1. Danh sách', 'title' => 'Danh sách đánh số', 'class' => ''],
                            ['tool' => 'blockquote', 'label' => '❝ Trích dẫn', 'title' => 'Trích dẫn', 'class' => ''],
                        ] as $tool)
                            <button type="button" title="{{ $tool['title'] }}" aria-label="{{ $tool['title'] }}" x-on:mousedown.prevent x-on:click="run('{{ $tool['tool'] }}')"
                                    x-bind:aria-pressed="active.{{ $tool['tool'] }}.toString()" x-bind:class="active.{{ $tool['tool'] }} ? 'bg-accent text-accent-text' : 'text-main hover:bg-surface'"
                                    @class(['rounded px-2.5 py-1 text-sm transition', $tool['class']])>{{ $tool['label'] }}</button>
                        @endforeach
                        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
                        <button type="button" title="Hoàn tác (Ctrl+Z)" aria-label="Hoàn tác (Ctrl+Z)" x-on:mousedown.prevent x-on:click="run('undo')" x-bind:disabled="!canUndo" class="rounded px-2.5 py-1 text-sm text-main transition hover:bg-surface disabled:opacity-30">↶</button>
                        <button type="button" title="Làm lại (Ctrl+Y)" aria-label="Làm lại (Ctrl+Y)" x-on:mousedown.prevent x-on:click="run('redo')" x-bind:disabled="!canRedo" class="rounded px-2.5 py-1 text-sm text-main transition hover:bg-surface disabled:opacity-30">↷</button>
                    </div>
                    <div x-ref="editor" x-cloak x-show="ready" aria-labelledby="article-body-label" class="[&_.ProseMirror]:min-h-[28rem] px-3 py-2 text-main"></div>
                    {{-- Without JavaScript the plain field still works; with it, the editor writes its markup here. --}}
                    <textarea id="article-body" name="body" rows="18" maxlength="20000" required x-ref="body" x-show="!ready"
                              class="block w-full rounded-md border-0 bg-surface px-3 py-2 font-[inherit] text-main focus:outline-none focus:ring-0">{{ old('body', $article->body) }}</textarea>
                </div>
                <p class="mt-1 text-xs text-muted">Gõ nhanh: "## " cho tiêu đề, "- " cho danh sách, "> " cho trích dẫn. Dán từ Word vẫn giữ đậm, nghiêng, tiêu đề, danh sách.</p>
                @error('body')<p role="alert" class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <aside class="space-y-6 lg:sticky lg:top-6" aria-label="Đăng bài và ảnh bìa">
            <section class="admin-card space-y-4" aria-labelledby="publish-title" data-publish-box>
                <div class="flex items-center justify-between gap-3">
                    <h2 id="publish-title" class="text-lg font-semibold text-main">Đăng bài</h2>
                    <span class="badge {{ $badge[1] }}" data-article-status>{{ $badge[0] }}</span>
                </div>
                @if ($article->exists)
                    <dl class="space-y-1 text-xs text-muted">
                        @if ($article->published_at)<div class="flex justify-between gap-2"><dt>Ngày đăng</dt><dd class="text-main">{{ $article->published_at->setTimezone($tz)->format('d/m/Y H:i') }}</dd></div>@endif
                        <div class="flex justify-between gap-2"><dt>Sửa lần cuối</dt><dd class="text-main">{{ $article->updated_at->setTimezone($tz)->format('d/m/Y H:i') }}</dd></div>
                    </dl>
                @endif
                <p x-cloak x-show="dirty" class="rounded-xl bg-warning-bg border border-warning/30 px-3 py-2 text-xs text-warning-text" role="status">Có thay đổi chưa lưu.</p>
                <div class="flex flex-col gap-2">
                    @foreach ($actions as [$value, $label, $style])
                        <button type="submit" name="status" value="{{ $value }}" data-status-action="{{ $value }}" @class([
                            'btn-pill-dark w-full py-2.5' => $style === 'primary',
                            'btn-pill w-full py-2.5' => $style === 'secondary',
                            'text-sm font-semibold text-muted underline-offset-4 hover:text-main hover:underline' => $style === 'link',
                        ])>{{ $label }}</button>
                    @endforeach
                </div>
                @error('status')<p role="alert" class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                @if ($current === ArticleStatus::Published)
                    <a href="{{ route('news.show', $article->slug) }}" target="_blank" rel="noopener" class="block text-center text-sm font-semibold text-muted hover:text-main">Xem bài ngoài trang ↗</a>
                @elseif ($current === ArticleStatus::Hidden)
                    <p class="text-xs text-muted">Bài đang ẩn với khách. Bấm "Đăng lại" để hiện lại.</p>
                @endif
            </section>

            <section class="admin-card space-y-3" aria-labelledby="cover-title" data-cover-box>
                <h2 id="cover-title" class="text-lg font-semibold text-main">Ảnh bìa @unless ($article->exists)<span class="text-error">*</span>@endunless</h2>
                <label for="article-cover"
                       x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="dropCover($event)"
                       x-bind:class="dragging ? 'border-accent bg-surface-alt' : 'border-border'"
                       class="group relative block aspect-[16/10] cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed transition hover:border-accent">
                    <img x-cloak x-show="coverUrl" x-bind:src="coverUrl" alt="Ảnh bìa đã chọn" class="h-full w-full object-cover" @if ($article->cover_path) src="{{ $article->coverUrl() }}" @endif>
                    <span x-show="!coverUrl" class="absolute inset-0 flex flex-col items-center justify-center gap-1 p-4 text-center text-sm text-muted">
                        <span class="font-semibold text-main">Kéo thả ảnh vào đây</span>
                        <span>hoặc bấm để chọn</span>
                    </span>
                    <span x-cloak x-show="coverUrl" class="absolute bottom-2 right-2 rounded-full bg-surface/90 border border-border px-3 py-1 text-xs font-semibold text-main shadow-sm">Đổi ảnh</span>
                </label>
                <input id="article-cover" x-ref="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" @unless ($article->exists) required @endunless
                       x-on:change="pickCover($event.target.files[0])" class="sr-only">
                <p x-cloak x-show="coverName" class="truncate text-xs text-muted" x-text="'Đã chọn: ' + coverName"></p>
                <p x-cloak x-show="coverError" class="text-sm text-error" role="alert" x-text="coverError"></p>
                <p class="text-xs text-muted">JPEG, PNG hoặc WebP, tối đa 5 MB. Hiển thị theo khung 16:10 như ngoài trang bài viết.@if ($article->exists) Không chọn ảnh mới thì giữ ảnh cũ.@endif</p>
                @error('cover')<p role="alert" class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </section>
        </aside>
    </form>
@endsection
