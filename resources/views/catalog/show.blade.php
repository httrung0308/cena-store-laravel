@extends('layouts.store')
@section('title', $product['name'])
@section('content')
<div class="mx-auto mt-8 w-full max-w-7xl px-4">
    <a class="text-sm underline" href="{{ route('shoes.index') }}">← Shoes</a>
    <div class="mt-6 grid min-w-0 gap-10 lg:grid-cols-2">
        <x-catalog.gallery :albums="$albums" :initial-color="$initialColorId" />
        <div class="min-w-0 space-y-6 break-words">
            <div>
                <h1 class="text-4xl font-semibold leading-tight">{{ $product['name'] }}</h1>
                <p class="mt-2 text-lg text-zinc-600">{{ $product['category'] }}</p>
                @if ($product['reviews_count'] > 0)
                    <a href="#product-reviews" class="mt-1 inline-flex items-center gap-2 text-sm"><x-reviews.stars :rating="$product['rating_avg']" /> <span>{{ number_format($product['rating_avg'], 1, ',', '.') }} · {{ $product['reviews_count'] }} đánh giá</span></a>
                @endif
                @if ($product['brand_line'])
                    <p class="text-sm text-zinc-500">{{ $product['brand_line'] }}</p>
                @endif
            </div>
            <p class="text-xl">Từ {{ $product['price_label'] }}
                <span class="block text-sm text-zinc-500">Chọn màu và size để xem giá chính xác.</span>
            </p>
            <x-catalog.variant-picker :variants="$variants" :colors="$colors" :initial-color-id="$initialColorId" />
            <x-ui.dialog id="shoe-size-guide" title="Size guide">
                <x-slot:trigger><span class="text-sm underline">Hướng dẫn chọn size</span></x-slot:trigger>
                <p>Chọn size EU phù hợp bạn thường mang. Các size có đuôi .5 là nửa size; chỉ những size đang hiển thị và còn hàng mới có thể thêm vào giỏ.</p>
                <p class="mt-3">Nếu chưa chắc, hãy liên hệ cửa hàng để được tư vấn theo mẫu giày trước khi đặt.</p>
            </x-ui.dialog>
            @if ($product['description'])
                <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ $product['description'] }}</div>
            @endif
            @if ($product['specifications'])
                <details class="border-t py-4">
                    <summary class="cursor-pointer font-semibold">Chi tiết sản phẩm</summary>
                    <dl class="mt-3 space-y-2">
                        @foreach ($product['specifications'] as $key => $value)
                            @if (is_scalar($value))
                                <div><dt class="font-semibold">{{ $key }}</dt><dd>{{ $value }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                </details>
            @endif
            <details class="border-t py-4">
                <summary class="cursor-pointer font-semibold">Shipping &amp; Returns</summary>
                <p class="mt-3 text-sm">Thời hạn đổi size: {{ $product['exchange_days'] }} ngày. Bảo hành: {{ $product['warranty_months'] }} tháng. Điều kiện cụ thể được cửa hàng kiểm tra khi xử lý yêu cầu.</p>
            </details>
            <details id="product-reviews" class="border-t py-4" @if ($product['reviews_count'] > 0) open @endif>
                <summary class="cursor-pointer font-semibold">Đánh giá ({{ $product['reviews_count'] }})</summary>
                @if ($product['reviews_count'] > 0)
                    <div class="mt-3 flex items-center gap-3"><span class="text-3xl font-bold">{{ number_format($product['rating_avg'], 1, ',', '.') }}</span><x-reviews.stars :rating="$product['rating_avg']" /></div>
                @endif
                <div class="mt-4 space-y-4">
                    @forelse ($reviews as $review)
                        <article class="border-b pb-4 last:border-b-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="flex items-center gap-2"><x-reviews.stars :rating="$review['rating']" /><span class="text-sm font-semibold">{{ $review['author'] }}</span></span>
                                <span class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($review['date'], 'UTC')->setTimezone((string) config('store.business_timezone'))->format('d/m/Y') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">Đã mua: {{ $review['purchase'] }}</p>
                            <p class="mt-2 whitespace-pre-wrap text-sm">{{ $review['comment'] }}</p>
                            @if ($review['reply'])<p class="mt-2 whitespace-pre-wrap rounded-lg bg-zinc-50 p-3 text-sm"><span class="font-semibold">Phản hồi từ cửa hàng:</span> {{ $review['reply'] }}</p>@endif
                        </article>
                    @empty
                        <p class="text-sm text-zinc-500">Chưa có đánh giá. Khách đã mua và nhận hàng có thể đánh giá từ trang đơn hàng.</p>
                    @endforelse
                </div>
            </details>
        </div>
    </div>
</div>
@endsection
