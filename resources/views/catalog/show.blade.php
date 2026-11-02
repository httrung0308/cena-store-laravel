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
            <details class="border-t py-4">
                <summary class="cursor-pointer font-semibold">Đánh giá</summary>
                <p class="mt-3 text-sm text-zinc-500">Chức năng đánh giá sẽ được mở trong đợt tiếp theo.</p>
            </details>
        </div>
    </div>
</div>
@endsection
