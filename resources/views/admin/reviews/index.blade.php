@extends('layouts.admin')
@section('title', 'Quản Lý Đánh Giá')
@section('eyebrow', 'QUẢN TRỊ ĐÁNH GIÁ')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-6">
        <div class="bg-surface-card p-4 rounded-2xl border border-border mb-6 flex flex-col md:flex-row items-center justify-between gap-4 text-xs shadow-sm">
            <form method="GET" action="{{ route('admin.reviews.index') }}" class="flex flex-wrap items-center gap-3 w-full">
                <label class="flex items-center gap-2 text-xs font-bold text-main">Hiển thị
                    <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="visible">
                        <option value="">Tất cả</option>
                        <option value="1" @selected(($filters['visible'] ?? '') === '1')>Đang hiện</option>
                        <option value="0" @selected(($filters['visible'] ?? '') === '0')>Đã ẩn</option>
                    </select>
                </label>
                <button type="submit" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm">Lọc</button>
            </form>
        </div>

        @forelse ($reviews as $review)
            <article class="admin-card space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <strong class="font-bold text-main text-sm block">{{ $review->unit?->item?->product_name_snapshot }} <span class="text-xs font-normal text-muted">· {{ $review->unit?->item?->color_name_snapshot }} · Size {{ $review->unit?->item?->size_value_snapshot }}</span></strong>
                        <p class="text-xs text-muted mt-0.5">{{ $review->user?->full_name }} · {{ $review->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-reviews.stars :rating="$review->rating" />
                        @if ($review->is_visible)
                            <span class="bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang hiện</span>
                        @else
                            <span class="bg-muted/15 text-muted px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã ẩn</span>
                        @endif
                    </div>
                </div>
                <p class="whitespace-pre-wrap text-xs text-main leading-relaxed bg-surface/30 p-3 rounded-xl border border-border/50">{{ $review->comment }}</p>
                <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <form method="POST" action="{{ route('admin.reviews.reply', $review) }}" class="flex flex-col gap-2">
                        @csrf
                        @method('PATCH')
                        <label for="reply-{{ $review->id }}" class="admin-label text-muted">Phản hồi của cửa hàng</label>
                        <textarea id="reply-{{ $review->id }}" name="admin_reply" rows="2" maxlength="2000" class="input w-full border-border bg-surface text-main text-xs leading-relaxed" placeholder="Nhập câu trả lời cho khách...">{{ $review->admin_reply }}</textarea>
                        <button type="submit" class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border w-fit transition-colors">Lưu phản hồi</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reviews.visibility', $review) }}" class="self-end">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="visible" value="{{ $review->is_visible ? 0 : 1 }}">
                        <button type="submit" @class([
                            'px-4 py-2 text-xs font-bold rounded-xl border transition-colors',
                            'border-error/30 text-error hover:bg-error-bg' => $review->is_visible,
                            'border-border bg-surface text-main hover:bg-border/40' => ! $review->is_visible,
                        ])>{{ $review->is_visible ? 'Ẩn đánh giá' : 'Hiện lại' }}</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="admin-card p-12 text-center text-xs text-muted">Chưa có đánh giá.</div>
        @endforelse
        <x-ui.pagination :paginator="$reviews" />
    </div>
@endsection
