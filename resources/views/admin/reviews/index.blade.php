@extends('layouts.admin')
@section('title', 'Quản Lý Đánh Giá')
@section('eyebrow', 'QUẢN TRỊ ĐÁNH GIÁ')
@php
    $tz = (string) config('store.business_timezone');
    $activeChips = [];
    if (filled($filters['search'] ?? null)) {
        $activeChips[] = [
            'label' => 'Từ khóa: "'.$filters['search'].'"',
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (isset($filters['rating']) && $filters['rating'] !== '') {
        $activeChips[] = [
            'label' => 'Đánh giá: '.$filters['rating'].' sao',
            'url' => request()->fullUrlWithQuery(['rating' => null, 'page' => null]),
        ];
    }
    if (isset($filters['visible']) && $filters['visible'] !== '') {
        $activeChips[] = [
            'label' => 'Hiển thị: '.($filters['visible'] === '1' ? 'Đang hiện' : 'Đã ẩn'),
            'url' => request()->fullUrlWithQuery(['visible' => null, 'page' => null]),
        ];
    }
    if (isset($filters['replied']) && $filters['replied'] !== '') {
        $activeChips[] = [
            'label' => 'Phản hồi: '.($filters['replied'] === '0' ? 'Chưa phản hồi' : 'Đã phản hồi'),
            'url' => request()->fullUrlWithQuery(['replied' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
    <div x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6">
        <x-admin.search-form
            :action="route('admin.reviews.index')"
            id="review-search"
            label="Tìm đánh giá"
            placeholder="Tìm theo tên sản phẩm, người đánh giá, nội dung..."
            :value="$filters['search'] ?? ''"
            :ajax="true"
            :active-chips="$activeChips"
        >
            <x-slot:filters>
                <x-admin.filter-select id="review-rating-filter" name="rating" label="Số sao:" :active="isset($filters['rating']) && $filters['rating'] !== ''">
                    <option value="">Tất cả</option>
                    @for ($stars = 5; $stars >= 1; $stars--)
                        <option value="{{ $stars }}" @selected((string) ($filters['rating'] ?? '') === (string) $stars)>{{ $stars }} sao</option>
                    @endfor
                </x-admin.filter-select>

                <x-admin.filter-select id="review-visible-filter" name="visible" label="Hiển thị:" :active="isset($filters['visible']) && $filters['visible'] !== ''">
                    <option value="">Tất cả</option>
                    <option value="1" @selected(($filters['visible'] ?? '') === '1')>Đang hiện</option>
                    <option value="0" @selected(($filters['visible'] ?? '') === '0')>Đã ẩn</option>
                </x-admin.filter-select>

                <x-admin.filter-select id="review-replied-filter" name="replied" label="Phản hồi:" :active="isset($filters['replied']) && $filters['replied'] !== ''">
                    <option value="">Tất cả</option>
                    <option value="0" @selected(($filters['replied'] ?? '') === '0')>Chưa phản hồi</option>
                    <option value="1" @selected(($filters['replied'] ?? '') === '1')>Đã phản hồi</option>
                </x-admin.filter-select>
            </x-slot:filters>
        </x-admin.search-form>

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none transition-opacity': listLoading }" class="space-y-4">
            <div class="flex items-center justify-between px-1 text-xs text-muted">
                <span>Tổng cộng <strong class="font-bold text-main">{{ number_format($reviews->total()) }}</strong> đánh giá</span>
                @if ($reviews->hasPages())
                    <span>Trang {{ $reviews->currentPage() }} / {{ $reviews->lastPage() }}</span>
                @endif
            </div>

            <div class="space-y-4">
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
                            <form
                                method="POST"
                                action="{{ route('admin.reviews.reply', $review) }}"
                                data-no-guard
                                x-data="{
                                    saving: false,
                                    replyText: @js($review->admin_reply ?? ''),
                                    repliedAt: @js($review->replied_at?->setTimezone($tz)->format('d/m/Y H:i')),
                                    replyError: '',
                                    async submitReply() {
                                        if (this.saving) return;
                                        this.saving = true;
                                        this.replyError = '';
                                        try {
                                            const response = await fetch(this.$el.action, {
                                                method: 'POST',
                                                credentials: 'same-origin',
                                                headers: {
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                },
                                                body: new FormData(this.$el),
                                            });
                                            const data = await response.json().catch(() => null);
                                            if (!response.ok) {
                                                const errs = data?.errors ? Object.values(data.errors).flat() : [];
                                                this.replyError = errs.find((msg) => typeof msg === 'string' && msg !== '') || data?.message || 'Không thể lưu phản hồi. Vui lòng thử lại.';
                                                window.dispatchEvent(new CustomEvent('cena-toast', { detail: { type: 'error', message: this.replyError } }));
                                                return;
                                            }
                                            this.replyText = data?.reply_content ?? '';
                                            this.repliedAt = data?.replied_at ?? null;
                                            window.dispatchEvent(new CustomEvent('cena-toast', { detail: { type: 'success', message: data?.message || 'Đã lưu phản hồi.' } }));
                                        } catch {
                                            this.replyError = 'Không kết nối được máy chủ. Vui lòng thử lại.';
                                            window.dispatchEvent(new CustomEvent('cena-toast', { detail: { type: 'error', message: this.replyError } }));
                                        } finally {
                                            this.saving = false;
                                        }
                                    }
                                }"
                                @submit.prevent="submitReply()"
                                class="flex flex-col gap-2"
                            >
                                @csrf
                                @method('PATCH')
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label for="reply-{{ $review->id }}" class="admin-label text-muted">Phản hồi của cửa hàng</label>
                                    <span x-show="repliedAt" x-cloak class="text-[11px] text-muted">Đã phản hồi lúc <span class="font-medium text-main" x-text="repliedAt"></span></span>
                                </div>
                                <textarea id="reply-{{ $review->id }}" name="admin_reply" x-model="replyText" rows="2" maxlength="2000" class="input w-full border-border bg-surface text-main text-xs leading-relaxed" placeholder="Nhập câu trả lời cho khách..."></textarea>
                                <p x-show="replyError" x-cloak x-text="replyError" class="text-xs text-error" role="alert"></p>
                                <button type="submit" :disabled="saving" class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border w-fit transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-1.5">
                                    <span x-show="!saving">Lưu phản hồi</span>
                                    <span x-cloak x-show="saving" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang lưu...</span>
                                </button>
                            </form>
                            <div class="self-end">
                                <x-admin.status-switch
                                    :active="$review->is_visible"
                                    :action="route('admin.reviews.visibility', $review)"
                                    :toggle-key="'review-'.$review->id"
                                    active-label="Đang hiện"
                                    inactive-label="Đang ẩn"
                                    :action-label="$review->is_visible ? 'Ẩn đánh giá' : 'Hiện lại đánh giá'"
                                >
                                    <input type="hidden" name="visible" value="{{ $review->is_visible ? 0 : 1 }}">
                                </x-admin.status-switch>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="admin-card p-12 text-center text-xs text-muted">Chưa có đánh giá.</div>
                @endforelse
            </div>
            <x-ui.pagination :paginator="$reviews" />
        </div>
    </div>
@endsection
