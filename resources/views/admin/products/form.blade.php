@extends('layouts.admin')
@section('title', $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')
@section('eyebrow', 'Quản lý sản phẩm')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.products.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-5xl">
        <x-admin.products.metadata-form :product="$product->toArray()" :categories="$categories" :action="$product->exists ? route('admin.products.update', $product) : route('admin.products.store')" :method="$product->exists ? 'PUT' : 'POST'">
            <section class="admin-card space-y-4">
                <h2 class="text-lg font-bold text-main">Trạng thái bán</h2>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-main cursor-pointer select-none">
                    <input type="checkbox" name="is_active" value="1" class="native-checkbox text-accent focus:ring-accent rounded" @checked(old('is_active', $product->is_active))>
                    <span>Đang bán sản phẩm này</span>
                </label>
            </section>
            <x-admin.products.color-variants :colors="is_array(old('colors', $colors)) ? old('colors', $colors) : []" />
        </x-admin.products.metadata-form>
    </div>
@endsection
