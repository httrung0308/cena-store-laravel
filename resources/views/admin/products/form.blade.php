@extends('layouts.admin')
@section('title', ($isDuplicate ?? false) ? 'Nhân bản sản phẩm' : ($product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm'))
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <div class="flex items-center gap-2">
        @if ($product->exists && !($isDuplicate ?? false))
            <a class="px-4 py-2 bg-surface hover:bg-border/40 text-accent hover:text-accent-hover text-xs font-bold rounded-xl border border-border transition-colors flex items-center gap-1.5"
               href="{{ route('admin.products.duplicate', $product) }}">
                <span>📋</span> <span>Nhân bản sản phẩm này</span>
            </a>
        @endif
        <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.products.index') }}">← Quay lại danh sách</a>
    </div>
@endsection
@section('content')
    <div class="max-w-5xl space-y-6">
        @if ($isDuplicate ?? false)
            <div class="rounded-2xl border border-accent/30 bg-accent/10 p-4 text-xs text-main flex items-start gap-3">
                <span class="text-base">📋</span>
                <div class="space-y-1">
                    <p class="font-bold text-accent">Đang tạo bản sao từ sản phẩm: {{ $originalProduct->name ?? '' }}</p>
                    <p class="text-muted">Thông tin phối màu và size đã được sao chép với mã SKU mới. Tồn kho bắt đầu từ 0. Tệp ảnh sẽ được nhân bản độc lập khi bạn lưu, không ảnh hưởng sản phẩm gốc.</p>
                </div>
            </div>
        @endif

        @php
            $hasNoImages = $product->exists && $product->colors()->whereDoesntHave('images')->exists();
        @endphp
        @if ($hasNoImages)
            <div class="rounded-2xl border border-warning/30 bg-warning-bg p-4 text-xs text-warning-text flex items-start gap-3">
                <span class="text-base">⚠️</span>
                <div class="space-y-1">
                    <p class="font-bold">Sản phẩm chưa có đủ ảnh đại diện</p>
                    <p>Sản phẩm này (hoặc một số phối màu) chưa có ảnh. Vui lòng tải ít nhất 1 ảnh đại diện cho mỗi màu bên dưới trước khi kích hoạt mở bán.</p>
                </div>
            </div>
        @endif

        <x-admin.products.metadata-form :product="$product->toArray()" :categories="$categories" :action="$product->exists ? route('admin.products.update', $product) : route('admin.products.store')" :method="$product->exists ? 'PUT' : 'POST'">
            <section class="admin-card space-y-4">
                <h2 class="text-base font-bold text-main">Trạng thái bán & Hiển thị</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="native-checkbox text-accent focus:ring-accent rounded" @checked(old('is_active', $product->is_active ?? true))>
                        <span>Đang bán sản phẩm này</span>
                    </label>

                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" class="native-checkbox text-accent focus:ring-accent rounded" @checked(old('is_featured', $product->is_featured ?? false))>
                        <span class="flex items-center gap-1.5"><span class="text-amber-500">★</span> <span>Sản phẩm nổi bật (Featured trên trang chủ)</span></span>
                    </label>
                </div>
            </section>
            <x-admin.products.classification-picker :classifications="$classifications ?? []" :selected="old('classification_ids', $selectedClassificationIds ?? [])" />
            <x-admin.products.color-variants :colors="is_array(old('colors', $colors)) ? old('colors', $colors) : []" />
        </x-admin.products.metadata-form>
    </div>
@endsection
