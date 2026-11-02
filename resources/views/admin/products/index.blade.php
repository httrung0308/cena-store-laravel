@extends('layouts.admin')
@section('title','Sản phẩm')
@section('content')
<a class="btn inline-block" href="{{ route('admin.products.create') }}">Thêm sản phẩm</a>
<form class="my-4 flex gap-2" method="GET"><label class="sr-only" for="product-search">Tìm sản phẩm</label><input class="input flex-1" id="product-search" name="search" value="{{ request('search') }}"><button class="btn">Tìm</button></form>
<div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th>Tên</th><th>Danh mục</th><th>Giá gốc</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($products as $product)<tr class="border-b"><td class="p-3">{{ $product->name }}</td><td>{{ $product->category?->name }}</td><td>{{ number_format($product->base_price,0,',','.') }} ₫</td><td>{{ $product->is_active ? 'Đang bán':'Đã ẩn' }}</td><td><a class="underline" href="{{ route('admin.products.edit',$product) }}">Sửa</a><form method="POST" action="{{ route('admin.products.deactivate',$product) }}">@csrf @method('PATCH')<button class="underline">Ngừng bán</button></form></td></tr>
@empty<tr><td colspan="5">Chưa có sản phẩm.</td></tr>@endforelse</tbody></table></div>
<x-ui.pagination :paginator="$products" />
@endsection
