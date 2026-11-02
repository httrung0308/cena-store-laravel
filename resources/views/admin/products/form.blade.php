@extends('layouts.admin')
@section('title', $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')
@section('eyebrow', 'Quản lý sản phẩm')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.products.index') }}">Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-5xl">
        <x-admin.products.metadata-form :product="$product->toArray()" :categories="$categories" :action="$product->exists ? route('admin.products.update', $product) : route('admin.products.store')" :method="$product->exists ? 'PUT' : 'POST'">
            <section class="admin-card space-y-4">
                <h2 class="text-xl font-semibold">Trạng thái bán</h2>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" class="native-checkbox accent-black" @checked(old('is_active', $product->is_active))> Đang bán</label>
            </section>
            <x-admin.products.color-variants :colors="is_array(old('colors', $colors)) ? old('colors', $colors) : []" />
        </x-admin.products.metadata-form>
    </div>
@endsection
