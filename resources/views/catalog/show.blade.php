@extends('layouts.store')
@section('title', $product['name'])
@section('content')
<div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
    <nav class="mb-6 flex items-center gap-2 text-xs text-muted" aria-label="Breadcrumbs">
        <a href="{{ route('shoes.index') }}" class="inline-flex items-center gap-1 font-medium text-muted transition-colors hover:text-main">← Shoes</a>
        <span>/</span>
        <span class="truncate text-main font-medium">{{ $product['name'] }}</span>
    </nav>

    <div class="grid min-w-0 grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-14 items-start">
        <x-catalog.gallery :albums="$albums" :initial-color="$initialColorId" />

        <div class="min-w-0 space-y-6 break-words">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-widest text-accent">Cena · Tuyển chọn chính hãng</span>
                <h1 class="mt-1 font-serif text-3xl font-light tracking-tight text-main sm:text-4xl">{{ $product['name'] }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $product['category'] }}</p>
                @if ($product['reviews_count'] > 0)
                    <a href="#product-reviews" class="mt-2 inline-flex items-center gap-2 text-xs text-muted transition-colors hover:text-accent">
                        <x-reviews.stars :rating="$product['rating_avg']" />
                        <span class="font-medium text-main">{{ number_format($product['rating_avg'], 1, ',', '.') }}</span>
                        <span>· {{ $product['reviews_count'] }} đánh giá</span>
                    </a>
                @endif
                @if ($product['brand_line'])
                    <p class="mt-1 text-xs text-muted">{{ $product['brand_line'] }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-border bg-surface-card p-4 sm:p-5">
                <p class="text-xl sm:text-2xl font-semibold text-main">Từ {{ $product['price_label'] }}
                    <span class="block mt-1 text-xs font-normal text-muted">Chọn màu và size để xem giá chính xác.</span>
                </p>
            </div>

            <x-catalog.variant-picker :variants="$variants" :colors="$colors" :initial-color-id="$initialColorId" />

            <div class="flex items-center justify-between border-t border-border pt-4">
                <x-ui.dialog id="shoe-size-guide" title="Size guide">
                    <x-slot:trigger>
                        <span class="cursor-pointer text-xs font-medium text-accent underline underline-offset-4 hover:opacity-80">Hướng dẫn chọn size</span>
                    </x-slot:trigger>
                    <div class="space-y-3 text-sm text-muted">
                        <p>Chọn size EU phù hợp bạn thường mang. Các size có đuôi .5 là nửa size; chỉ những size đang hiển thị và còn hàng mới có thể thêm vào giỏ.</p>
                        <p>Nếu chưa chắc, hãy liên hệ cửa hàng để được tư vấn theo mẫu giày trước khi đặt.</p>
                    </div>
                </x-ui.dialog>
            </div>

            <div class="grid grid-cols-2 gap-3 rounded-2xl border border-border bg-surface-card p-4 text-xs text-muted">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-accent">✓</span>
                    <span>Đổi size trong {{ $product['exchange_days'] }} ngày</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-accent">✓</span>
                    <span>Bảo hành chính hãng {{ $product['warranty_months'] }} tháng</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-accent">✓</span>
                    <span>Miễn phí giao hàng toàn quốc</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-accent">✓</span>
                    <span>Kiểm tra trước khi nhận hàng</span>
                </div>
            </div>

            @if ($product['description'])
                <div class="rounded-2xl border border-border bg-surface-card p-5">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-main">Mô tả sản phẩm</h2>
                    <div class="whitespace-pre-wrap text-xs leading-relaxed text-muted">{{ $product['description'] }}</div>
                </div>
            @endif

            @if ($product['specifications'])
                <details class="group rounded-2xl border border-border bg-surface-card p-5">
                    <summary class="cursor-pointer list-none flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-main">
                        <span>Chi tiết sản phẩm</span>
                        <svg class="h-4 w-4 text-muted transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <dl class="mt-4 divide-y divide-border/60 text-xs">
                        @foreach ($product['specifications'] as $key => $value)
                            @if (is_scalar($value))
                                <div class="flex justify-between py-2 text-muted">
                                    <dt class="font-medium text-main">{{ $key }}</dt>
                                    <dd class="text-right">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </details>
            @endif

            <details class="group rounded-2xl border border-border bg-surface-card p-5">
                <summary class="cursor-pointer list-none flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-main">
                    <span>Shipping &amp; Returns</span>
                    <svg class="h-4 w-4 text-muted transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <p class="mt-3 text-xs leading-relaxed text-muted">Thời hạn đổi size: {{ $product['exchange_days'] }} ngày. Bảo hành: {{ $product['warranty_months'] }} tháng. Điều kiện cụ thể được cửa hàng kiểm tra khi xử lý yêu cầu.</p>
            </details>

            <details id="product-reviews" class="group rounded-2xl border border-border bg-surface-card p-5" @if ($product['reviews_count'] > 0) open @endif>
                <summary class="cursor-pointer list-none flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-main">
                    <span>Đánh giá ({{ $product['reviews_count'] }})</span>
                    <svg class="h-4 w-4 text-muted transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                @if ($product['reviews_count'] > 0)
                    <div class="mt-4 flex items-center gap-3 border-b border-border/60 pb-4">
                        <span class="font-serif text-3xl font-light text-main">{{ number_format($product['rating_avg'], 1, ',', '.') }}</span>
                        <div>
                            <x-reviews.stars :rating="$product['rating_avg']" />
                            <p class="mt-0.5 text-[11px] text-muted">{{ $product['reviews_count'] }} lượt đánh giá khách quan</p>
                        </div>
                    </div>
                @endif
                <div class="mt-4 space-y-4">
                    @forelse ($reviews as $review)
                        <article class="border-b border-border/50 pb-4 last:border-b-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="flex items-center gap-2">
                                    <x-reviews.stars :rating="$review['rating']" />
                                    <span class="text-xs font-semibold text-main">{{ $review['author'] }}</span>
                                </span>
                                <span class="text-[11px] text-muted">{{ \Illuminate\Support\Carbon::parse($review['date'], 'UTC')->setTimezone((string) config('store.business_timezone'))->format('d/m/Y') }}</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">Đã mua: {{ $review['purchase'] }}</p>
                            <p class="mt-2 whitespace-pre-wrap text-xs leading-relaxed text-main">{{ $review['comment'] }}</p>
                            @if ($review['reply'])
                                <div class="mt-2 whitespace-pre-wrap rounded-xl border border-border bg-surface p-3 text-xs text-muted">
                                    <span class="font-semibold text-accent">Phản hồi từ cửa hàng:</span> {{ $review['reply'] }}
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="text-xs text-muted">Chưa có đánh giá. Khách đã mua và nhận hàng có thể đánh giá từ trang đơn hàng.</p>
                    @endforelse
                </div>
            </details>
        </div>
    </div>
</div>
@endsection
