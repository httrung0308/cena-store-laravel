@extends('layouts.admin')
@section('title', 'Đánh giá')
@section('eyebrow', 'Quản lý đánh giá')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-6">
        <form method="GET" action="{{ route('admin.reviews.index') }}" class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-muted">Hiển thị
                <select class="input border-border bg-surface text-main py-2 font-normal" name="visible"><option value="">Tất cả</option><option value="1" @selected(($filters['visible'] ?? '') === '1')>Đang hiện</option><option value="0" @selected(($filters['visible'] ?? '') === '0')>Đã ẩn</option></select>
            </label>
            <button type="submit" class="btn-pill-dark">Lọc</button>
        </form>
        @forelse ($reviews as $review)
            <article class="admin-card space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-main">{{ $review->unit?->item?->product_name_snapshot }} <span class="text-sm font-normal text-muted">· {{ $review->unit?->item?->color_name_snapshot }} · Size {{ $review->unit?->item?->size_value_snapshot }}</span></p>
                        <p class="text-sm text-muted">{{ $review->user?->full_name }} · {{ $review->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-reviews.stars :rating="$review->rating" />
                        @if ($review->is_visible)<span class="badge bg-success-bg text-success-text border border-success/30">Đang hiện</span>@else<span class="badge bg-surface-alt text-muted border border-border">Đã ẩn</span>@endif
                    </div>
                </div>
                <p class="whitespace-pre-wrap text-sm text-main">{{ $review->comment }}</p>
                <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <form method="POST" action="{{ route('admin.reviews.reply', $review) }}" class="flex flex-col gap-2">
                        @csrf
                        @method('PATCH')
                        <label for="reply-{{ $review->id }}" class="admin-label text-muted">Phản hồi của cửa hàng</label>
                        <textarea id="reply-{{ $review->id }}" name="admin_reply" rows="2" maxlength="2000" class="input w-full border-border bg-surface text-main">{{ $review->admin_reply }}</textarea>
                        <button type="submit" class="btn-pill w-fit">Lưu phản hồi</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reviews.visibility', $review) }}" class="self-end">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="visible" value="{{ $review->is_visible ? 0 : 1 }}">
                        <button type="submit" @class(['btn-pill', 'text-error hover:bg-error-bg' => $review->is_visible])>{{ $review->is_visible ? 'Ẩn đánh giá' : 'Hiện lại' }}</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="admin-card py-16 text-center text-muted">Chưa có đánh giá.</div>
        @endforelse
        <x-ui.pagination :paginator="$reviews" />
    </div>
@endsection
