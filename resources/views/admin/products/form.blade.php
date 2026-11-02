@extends('layouts.admin')
@section('title',$product->exists ? 'Sửa sản phẩm':'Thêm sản phẩm')
@section('content')
<div class="mx-auto max-w-5xl">
@if($errors->any())<div role="alert" class="mb-5 border border-red-400 p-4"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<x-admin.products.metadata-form :product="$product->toArray()" :categories="$categories" :action="$product->exists ? route('admin.products.update',$product) : route('admin.products.store')" :method="$product->exists ? 'PUT':'POST'">
<input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$product->is_active))> Đang bán</label>
<x-admin.products.color-variants :colors="is_array(old('colors',$colors)) ? old('colors',$colors):[]" />
</x-admin.products.metadata-form>
</div>
@endsection
