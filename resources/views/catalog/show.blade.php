@extends('layouts.store')
@section('title', $product['name'])
@section('content')
@php
    $reviewTotal = (int) ($reviewSummary['total'] ?? $product['reviews_count'] ?? 0);
    $reviewAverage = (float) ($reviewSummary['average'] ?? $product['rating_avg'] ?? 0);
    $reviewBreakdown = $reviewSummary['breakdown'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $withReplyCount = (int) ($reviewSummary['with_reply_count'] ?? 0);
    $activeRatingFilter = $reviewFilters['rating'] ?? null;
    $activeWithReply = (bool) ($reviewFilters['with_reply'] ?? false);

    $specItems = collect(is_array($product['specifications'] ?? null) ? $product['specifications'] : [])
        ->filter(fn ($v, $k) => is_string($k) && $k !== '' && is_scalar($v) && trim((string) $v) !== '')
        ->all();

    $pendingUnits = $customerReviewState['pending_units'] ?? [];
    $reviewedUnits = $customerReviewState['reviewed_units'] ?? [];
    $pendingOrder = $customerReviewState['pending_order'] ?? null;
    $reviewStatus = $customerReviewState['status'] ?? 'guest';

    $baseReviewQuery = request()->only(['style_color']);
    $reviewFilterUrl = function (?int $rating = null, bool $withReply = false) use ($product, $baseReviewQuery): string {
        $params = $baseReviewQuery;
        if ($rating !== null) {
            $params['review_rating'] = $rating;
        }
        if ($withReply) {
            $params['with_reply'] = 1;
        }

        return route('shoes.show', array_merge(['slug' => $product['slug']], $params)).'#product-reviews';
    };

    $freeShipThreshold = (int) ($product['free_shipping_threshold'] ?? 0);
    $hasPolicyInfo = ($product['exchange_days'] ?? 0) > 0 || ($product['warranty_months'] ?? 0) > 0 || $freeShipThreshold > 0;
@endphp

<div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
    {{-- Breadcrumb Navigation --}}
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-xs text-muted" aria-label="Đường dẫn">
        <a href="{{ route('home') }}" class="transition-colors hover:text-main">Trang chủ</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('shoes.index') }}" class="font-medium transition-colors hover:text-main">Sản phẩm</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $product['category_url'] }}" class="transition-colors hover:text-main">{{ $product['category'] }}</a>
        <span aria-hidden="true">/</span>
        <span class="truncate font-semibold text-main">{{ $product['name'] }}</span>
    </nav>

    {{-- Top Buying Fold: Gallery + Purchase Controls --}}
    <div class="grid min-w-0 grid-cols-1 items-start gap-8 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)] lg:gap-12">
        <div class="min-w-0 lg:sticky lg:top-24">
            <x-catalog.gallery :albums="$albums" :initial-color="$initialColorId" />
        </div>

        <div class="min-w-0 space-y-6 break-words">
            <header class="space-y-2.5">
                <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                    <a href="{{ $product['category_url'] }}" class="font-semibold text-main hover:text-accent hover:underline">{{ $product['category'] }}</a>
                    @if ($product['brand_line'])
                        <span aria-hidden="true">·</span>
                        <span>{{ $product['brand_line'] }}</span>
                    @endif
                </div>

                <h1 class="text-2xl font-extrabold tracking-tight text-main sm:text-3xl lg:text-4xl leading-tight">{{ $product['name'] }}</h1>

                <div class="flex flex-wrap items-center gap-3 pt-0.5">
                    @if ($reviewTotal > 0)
                        <a href="#product-reviews" class="inline-flex items-center gap-2 rounded-lg text-xs text-muted transition-colors hover:text-main focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <x-reviews.stars :rating="$reviewAverage" />
                            <span class="font-bold text-main">{{ number_format($reviewAverage, 1, ',', '.') }}</span>
                            <span>({{ $reviewTotal }} đánh giá)</span>
                        </a>
                    @else
                        <a href="#product-reviews" class="text-xs font-medium text-muted underline decoration-border underline-offset-4 transition-colors hover:text-main">
                            Chưa có đánh giá
                        </a>
                    @endif
                </div>

                @if (! empty($product['classifications']))
                    <div class="flex flex-wrap gap-1.5 pt-1" aria-label="Phân loại sản phẩm">
                        @foreach ($product['classifications'] as $cls)
                            <a href="{{ $cls['url'] }}"
                                class="inline-flex items-center rounded-full border border-border bg-surface px-2.5 py-1 text-[11px] font-medium text-muted transition hover:border-accent hover:text-main">
                                {{ $cls['name'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </header>

            <x-catalog.variant-picker
                :variants="$variants"
                :colors="$colors"
                :initial-color-id="$initialColorId"
                :fallback-price-label="$product['price_label']" />

            @if ($hasPolicyInfo)
                <section aria-label="Thông tin bảo hành và đổi trả" class="grid grid-cols-1 gap-2.5 rounded-2xl border border-border bg-surface/60 p-4 text-xs text-muted sm:grid-cols-2">
                    @if (($product['exchange_days'] ?? 0) > 0)
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span>Hỗ trợ đổi size trong <strong class="text-main">{{ $product['exchange_days'] }} ngày</strong></span>
                        </div>
                    @endif
                    @if (($product['warranty_months'] ?? 0) > 0)
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span>Bảo hành <strong class="text-main">{{ $product['warranty_months'] }} tháng</strong> theo chính sách cửa hàng</span>
                        </div>
                    @endif
                    @if ($freeShipThreshold > 0)
                        <div class="flex items-center gap-2 sm:col-span-2">
                            <svg class="h-4 w-4 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            <span>Miễn phí vận chuyển cho đơn hàng từ <strong class="text-main">{{ number_format($freeShipThreshold, 0, ',', '.') }} ₫</strong></span>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>

    {{-- Additional Product Information (renders only real non-empty content) --}}
    @if ($product['description'] || ! empty($specItems) || $hasPolicyInfo)
        <section aria-label="Thông tin chi tiết sản phẩm" class="mt-12 grid gap-6 border-t border-border pt-10 lg:grid-cols-3">
            @if ($product['description'])
                <div class="rounded-2xl border border-border bg-surface-card p-6 {{ empty($specItems) ? 'lg:col-span-2' : '' }}">
                    <h2 class="mb-3 text-sm font-bold text-main">Mô tả sản phẩm</h2>
                    <div class="max-w-prose whitespace-pre-wrap text-sm leading-relaxed text-muted">{{ $product['description'] }}</div>
                </div>
            @endif

            @if (! empty($specItems))
                <div class="rounded-2xl border border-border bg-surface-card p-6">
                    <h2 class="mb-3 text-sm font-bold text-main">Thông số &amp; chất liệu</h2>
                    <dl class="divide-y divide-border/60 text-xs sm:text-sm">
                        @foreach ($specItems as $key => $value)
                            <div class="flex items-start justify-between gap-4 py-2.5">
                                <dt class="font-semibold text-main">{{ $key }}</dt>
                                <dd class="text-right text-muted">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if ($hasPolicyInfo)
                <div class="rounded-2xl border border-border bg-surface-card p-6">
                    <h2 class="mb-3 text-sm font-bold text-main">Đổi trả &amp; bảo hành</h2>
                    <ul class="space-y-2.5 text-xs sm:text-sm leading-relaxed text-muted">
                        @if (($product['exchange_days'] ?? 0) > 0)
                            <li>Thời hạn hỗ trợ đổi size: <strong class="text-main">{{ $product['exchange_days'] }} ngày</strong> kể từ khi nhận hàng.</li>
                        @endif
                        @if (($product['warranty_months'] ?? 0) > 0)
                            <li>Thời hạn bảo hành áp dụng: <strong class="text-main">{{ $product['warranty_months'] }} tháng</strong>. Điều kiện cụ thể được kiểm tra khi tiếp nhận yêu cầu.</li>
                        @endif
                        @if ($freeShipThreshold > 0)
                            <li>Đơn từ <strong class="text-main">{{ number_format($freeShipThreshold, 0, ',', '.') }} ₫</strong> được miễn phí vận chuyển; đơn dưới mức này áp dụng phí theo khu vực giao hàng.</li>
                        @endif
                    </ul>
                </div>
            @endif
        </section>
    @endif

    {{-- Customer Reviews Section --}}
    <section id="product-reviews" aria-label="Đánh giá sản phẩm" class="mt-12 scroll-mt-24 border-t border-border pt-10">
        <div class="mb-6 flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-tight text-main sm:text-2xl">
                Đánh giá ({{ $reviewTotal }})
            </h2>
            <a href="{{ route('shoes.index') }}" class="text-xs font-semibold text-muted hover:text-main hover:underline">
                ← Quay lại danh sách sản phẩm
            </a>
        </div>

        @if (session('status'))
            <div role="status" class="mb-6 rounded-xl border border-success bg-success-bg px-4 py-3 text-xs font-medium text-success-text">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('review') || $errors->has('rating') || $errors->has('comment') || $errors->has('unit_id'))
            <div role="alert" class="mb-6 rounded-xl border border-error bg-error-bg px-4 py-3 text-xs font-medium text-error-text">
                {{ $errors->first('review') ?: $errors->first('rating') ?: $errors->first('comment') ?: $errors->first('unit_id') }}
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] items-start">
            {{-- Left Column: Aggregate Summary & Customer Write/Edit State --}}
            <div class="space-y-4">
                <div class="rounded-2xl border border-border bg-surface-card p-5 space-y-4">
                    @if ($reviewTotal > 0)
                        <div class="flex items-center gap-4">
                            <span class="font-mono text-4xl font-extrabold text-main">{{ number_format($reviewAverage, 1, ',', '.') }}</span>
                            <div>
                                <x-reviews.stars :rating="$reviewAverage" />
                                <p class="mt-1 text-xs text-muted">Trên tổng số {{ $reviewTotal }} đánh giá hiển thị</p>
                            </div>
                        </div>

                        <div class="space-y-2 border-t border-border/70 pt-4">
                            @for ($star = 5; $star >= 1; $star--)
                                @php
                                    $count = (int) ($reviewBreakdown[$star] ?? 0);
                                    $pct = $reviewTotal > 0 ? round(($count / $reviewTotal) * 100) : 0;
                                    $isSelectedStar = $activeRatingFilter === $star;
                                @endphp
                                <a href="{{ $isSelectedStar ? $reviewFilterUrl(null, $activeWithReply) : $reviewFilterUrl($star, $activeWithReply) }}"
                                    class="flex items-center gap-2.5 rounded-lg px-1.5 py-1 text-xs transition hover:bg-surface {{ $isSelectedStar ? 'bg-surface font-bold text-main' : 'text-muted' }}">
                                    <span class="w-10 shrink-0 font-semibold text-main">{{ $star }} sao</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface border border-border/50">
                                        <div class="h-full rounded-full bg-warning" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="w-8 shrink-0 text-right tabular-nums">{{ $count }}</span>
                                </a>
                            @endfor
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <p class="text-sm font-bold text-main">Chưa có đánh giá công khai</p>
                            <p class="text-xs leading-relaxed text-muted">Sản phẩm này chưa có đánh giá nào được hiển thị.</p>
                        </div>
                    @endif
                </div>

                {{-- Customer Review Action / Eligibility Status --}}
                <div class="rounded-2xl border border-border bg-surface-card p-5 space-y-3"
                    x-data="{
                        formOpen: {{ $errors->has('rating') || $errors->has('comment') || $errors->has('unit_id') ? 'true' : 'false' }},
                        submitting: false,
                        selectedUnit: @js(old('unit_id') ?? ($pendingUnits[0]['unit_id'] ?? ($reviewedUnits[0]['unit_id'] ?? null))),
                    }">
                    @if ($reviewStatus === 'can_review')
                        <div class="space-y-2">
                            <p class="text-xs font-bold text-main">Bạn đã nhận và thanh toán sản phẩm này</p>
                            <p class="text-xs text-muted">Có {{ count($pendingUnits) }} đôi đang chờ bạn đánh giá.</p>
                            <button type="button"
                                x-on:click="formOpen = !formOpen"
                                :aria-expanded="formOpen.toString()"
                                class="btn w-full py-2.5 text-xs">
                                <span x-text="formOpen ? 'Thu gọn biểu mẫu' : 'Viết đánh giá'">Viết đánh giá</span>
                            </button>
                        </div>
                        <form x-cloak
                            x-show="formOpen"
                            method="POST"
                            action="{{ route('account.reviews.store') }}"
                            x-on:submit="if (submitting) { $event.preventDefault(); return false; } submitting = true"
                            class="space-y-3 border-t border-border pt-3">
                            @csrf
                            <div>
                                <label for="review-unit-select" class="mb-1 block text-xs font-semibold text-main">Đôi giày đã nhận</label>
                                <select id="review-unit-select" name="unit_id" class="input w-full py-2 text-xs">
                                    @foreach ($pendingUnits as $u)
                                        <option value="{{ $u['unit_id'] }}" @selected((int) old('unit_id') === $u['unit_id'])>
                                            Đơn {{ $u['order_code'] }} · {{ $u['color_name'] }} · Size {{ $u['size_value'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="review-rating-select" class="mb-1 block text-xs font-semibold text-main">Mức đánh giá</label>
                                <select id="review-rating-select" name="rating" class="input w-full py-2 text-xs">
                                    @foreach (array_reverse(App\Http\Requests\Account\ReviewRequest::RATINGS) as $val)
                                        <option value="{{ $val }}" @selected(old('rating', '5.0') === $val)>
                                            {{ rtrim(rtrim(str_replace('.', ',', $val), '0'), ',') }} sao
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="review-comment-input" class="mb-1 block text-xs font-semibold text-main">Nhận xét (10–2000 ký tự)</label>
                                <textarea id="review-comment-input"
                                    name="comment"
                                    rows="3"
                                    minlength="10"
                                    maxlength="2000"
                                    required
                                    placeholder="Chia sẻ độ êm, phom dáng, size thực tế khi sử dụng..."
                                    class="input w-full text-xs leading-relaxed">{{ old('comment') }}</textarea>
                            </div>
                            <button type="submit" x-bind:disabled="submitting" class="btn w-full py-2.5 text-xs">
                                <span x-show="!submitting">Gửi đánh giá</span>
                                <span x-cloak x-show="submitting">Đang gửi...</span>
                            </button>
                        </form>
                    @elseif ($reviewStatus === 'already_reviewed')
                        @php($latestReviewed = $reviewedUnits[0])
                        <div class="space-y-2">
                            <p class="text-xs font-bold text-main">Bạn đã gửi đánh giá cho sản phẩm này</p>
                            @if (! $latestReviewed['is_visible'])
                                <p class="text-xs text-muted">Đánh giá của bạn hiện đang tạm ẩn khỏi mục công khai.</p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <button type="button"
                                    x-on:click="formOpen = !formOpen"
                                    :aria-expanded="formOpen.toString()"
                                    class="btn-secondary px-3.5 py-2 text-xs">
                                    <span x-text="formOpen ? 'Đóng' : 'Sửa đánh giá'">Sửa đánh giá</span>
                                </button>
                                <a href="{{ route('orders.show', $latestReviewed['order_code']) }}" class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-semibold text-muted hover:text-main hover:underline">
                                    Xem đơn {{ $latestReviewed['order_code'] }}
                                </a>
                            </div>
                        </div>
                        <form x-cloak
                            x-show="formOpen"
                            method="POST"
                            action="{{ route('account.reviews.store') }}"
                            x-on:submit="if (submitting) { $event.preventDefault(); return false; } submitting = true"
                            class="space-y-3 border-t border-border pt-3">
                            @csrf
                            <input type="hidden" name="unit_id" value="{{ old('unit_id', $latestReviewed['unit_id']) }}">
                            <div>
                                <label for="edit-review-rating" class="mb-1 block text-xs font-semibold text-main">Mức đánh giá</label>
                                <select id="edit-review-rating" name="rating" class="input w-full py-2 text-xs">
                                    @foreach (array_reverse(App\Http\Requests\Account\ReviewRequest::RATINGS) as $val)
                                        <option value="{{ $val }}" @selected(old('rating', $latestReviewed['rating']) === $val)>
                                            {{ rtrim(rtrim(str_replace('.', ',', $val), '0'), ',') }} sao
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="edit-review-comment" class="mb-1 block text-xs font-semibold text-main">Nhận xét</label>
                                <textarea id="edit-review-comment"
                                    name="comment"
                                    rows="3"
                                    minlength="10"
                                    maxlength="2000"
                                    required
                                    class="input w-full text-xs leading-relaxed">{{ old('comment', $latestReviewed['comment']) }}</textarea>
                            </div>
                            <button type="submit" x-bind:disabled="submitting" class="btn w-full py-2.5 text-xs">
                                <span x-show="!submitting">Cập nhật đánh giá</span>
                                <span x-cloak x-show="submitting">Đang lưu...</span>
                            </button>
                        </form>
                    @elseif ($reviewStatus === 'pending_delivery' && $pendingOrder)
                        <p class="text-xs font-bold text-main">Chưa đủ điều kiện viết đánh giá</p>
                        <p class="text-xs leading-relaxed text-muted">Đánh giá chỉ mở sau khi đơn hàng được giao và thanh toán hoàn tất.</p>
                        <a href="{{ $pendingOrder['order_url'] }}" class="btn-secondary inline-flex px-3.5 py-2 text-xs">
                            Theo dõi đơn {{ $pendingOrder['order_code'] }}
                        </a>
                    @elseif ($reviewStatus === 'not_purchased')
                        <p class="text-xs font-bold text-main">Điều kiện viết đánh giá</p>
                        <p class="text-xs leading-relaxed text-muted">Chỉ khách hàng đã mua, nhận hàng và thanh toán thành công sản phẩm này mới có thể gửi đánh giá.</p>
                        <a href="{{ route('orders.index') }}" class="inline-flex text-xs font-semibold text-main underline decoration-border underline-offset-4 hover:text-accent">
                            Xem đơn hàng của tôi
                        </a>
                    @elseif ($reviewStatus === 'admin')
                        <p class="text-xs font-bold text-main">Kiểm duyệt đánh giá</p>
                        <p class="text-xs leading-relaxed text-muted">Tài khoản quản trị không tạo đánh giá mua hàng; xem và phản hồi tại trang quản trị.</p>
                        <a href="{{ route('admin.reviews.index') }}" class="btn-secondary inline-flex px-3.5 py-2 text-xs">Quản lý đánh giá</a>
                    @else
                        <p class="text-xs font-bold text-main">Viết đánh giá sản phẩm</p>
                        <p class="text-xs leading-relaxed text-muted">Đăng nhập tài khoản đã mua và nhận sản phẩm để viết hoặc chỉnh sửa đánh giá.</p>
                        <a href="{{ route('login') }}" class="btn-secondary inline-flex px-4 py-2 text-xs">Đăng nhập</a>
                    @endif
                </div>
            </div>

            {{-- Right Column: Filter Bar + Review List + Pagination --}}
            <div class="space-y-4">
                @if ($reviewTotal > 0)
                    <div class="flex flex-wrap items-center gap-2" aria-label="Lọc đánh giá">
                        <a href="{{ $reviewFilterUrl(null, false) }}"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $activeRatingFilter === null && ! $activeWithReply ? 'border-accent bg-accent text-accent-text' : 'border-border bg-surface-card text-main hover:border-accent' }}">
                            Tất cả ({{ $reviewTotal }})
                        </a>
                        @for ($star = 5; $star >= 1; $star--)
                            @if (($reviewBreakdown[$star] ?? 0) > 0)
                                <a href="{{ $reviewFilterUrl($star, $activeWithReply) }}"
                                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $activeRatingFilter === $star ? 'border-accent bg-accent text-accent-text' : 'border-border bg-surface-card text-main hover:border-accent' }}">
                                    {{ $star }} sao ({{ $reviewBreakdown[$star] }})
                                </a>
                            @endif
                        @endfor
                        @if ($withReplyCount > 0)
                            <a href="{{ $reviewFilterUrl($activeRatingFilter, ! $activeWithReply) }}"
                                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $activeWithReply ? 'border-accent bg-accent text-accent-text' : 'border-border bg-surface-card text-main hover:border-accent' }}">
                                Có phản hồi ({{ $withReplyCount }})
                            </a>
                        @endif
                    </div>
                @endif

                <div class="space-y-3">
                    @forelse ($reviews as $review)
                        <article class="rounded-2xl border border-border bg-surface-card p-5 space-y-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <x-reviews.stars :rating="$review['rating']" />
                                    <span class="text-xs font-bold text-main">{{ $review['author'] }}</span>
                                    <span class="text-[11px] text-muted">· Đã mua: {{ $review['purchase'] }}</span>
                                </div>
                                <time class="text-[11px] text-muted">
                                    {{ \Illuminate\Support\Carbon::parse($review['date'], 'UTC')->setTimezone((string) config('store.business_timezone'))->format('d/m/Y') }}
                                </time>
                            </div>

                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-main">{{ $review['comment'] }}</p>

                            @if ($review['reply'])
                                <div class="rounded-xl border border-border bg-surface p-3.5 text-xs leading-relaxed text-muted">
                                    <span class="font-bold text-main">Phản hồi từ cửa hàng:</span>
                                    <span class="whitespace-pre-wrap">{{ $review['reply'] }}</span>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-border bg-surface-card p-6 text-center space-y-2">
                            <p class="text-sm font-semibold text-main">
                                {{ $activeRatingFilter !== null || $activeWithReply ? 'Không có đánh giá phù hợp bộ lọc này.' : 'Chưa có đánh giá nào.' }}
                            </p>
                            @if ($activeRatingFilter !== null || $activeWithReply)
                                <a href="{{ $reviewFilterUrl(null, false) }}" class="inline-flex text-xs font-semibold text-main underline decoration-border underline-offset-4 hover:text-accent">
                                    Xem tất cả đánh giá
                                </a>
                            @else
                                <p class="text-xs text-muted">Hãy là người đầu tiên chia sẻ trải nghiệm sau khi nhận sản phẩm.</p>
                            @endif
                        </div>
                    @endforelse
                </div>

                @if ($reviewPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $reviewPaginator->hasPages())
                    <div class="overflow-hidden rounded-2xl border border-border">
                        <x-ui.pagination :paginator="$reviewPaginator" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Related Products (supports discovery without competing with buying flow) --}}
    @if (! empty($relatedProducts))
        <section aria-label="Sản phẩm liên quan" class="mt-14 border-t border-border pt-10">
            <div class="mb-6 flex items-baseline justify-between gap-3">
                <h2 class="text-xl font-extrabold tracking-tight text-main sm:text-2xl">Sản phẩm liên quan</h2>
                <a href="{{ $product['category_url'] }}" class="text-xs font-semibold text-main hover:text-accent hover:underline">
                    Xem thêm →
                </a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($relatedProducts as $entry)
                    <x-catalog.product-card
                        :product="$entry['product'] ?? []"
                        :detail-url="$entry['detailUrl'] ?? null"
                        :image-url="$entry['imageUrl'] ?? null" />
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
