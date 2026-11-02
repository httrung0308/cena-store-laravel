@extends('layouts.admin')
@section('title',$import->exists ? 'Sửa phiếu nháp':'Tạo phiếu nháp')
@section('content')
<div class="mx-auto max-w-5xl">
@if($errors->any())<div role="alert" class="border border-red-400 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ $import->exists ? route('admin.stock-imports.update',$import):route('admin.stock-imports.store') }}" class="space-y-5" x-data="{rows:@js(is_array(old('items',$items)) ? array_values(old('items',$items)) : [])}">
@csrf @if($import->exists) @method('PUT') @else<input type="hidden" name="command_key" value="{{ old('command_key',$commandKey) }}">@endif
<label class="block">Nhà cung cấp <select class="input w-full" name="supplier_id" required><option value="">Chọn nhà cung cấp</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id',$import->supplier_id)===(string)$supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}{{ $supplier->is_active ? '':' (đã ngừng)' }}</option>@endforeach</select></label>
<label class="block">Ghi chú / lý do nhập tặng <textarea class="input w-full" name="note" rows="3">{{ old('note',$import->note) }}</textarea></label>
<template x-for="(row,i) in rows" :key="i"><fieldset class="min-w-0 space-y-3 border p-4"><legend x-text="'Dòng '+(i+1)"></legend>
<label class="block">SKU <select class="input w-full" x-bind:name="'items['+i+'][variant_id]'" x-model="row.variant_id" required><option value="">Chọn SKU</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->sku }} — {{ $variant->color?->product?->name }} / {{ $variant->color?->color_name }} / {{ $variant->size_value }}{{ $variant->is_active ? '':' (ngừng bán)' }}</option>@endforeach</select></label>
<div class="grid gap-3 sm:grid-cols-2"><label>Số lượng <input class="input w-full" type="number" min="1" max="2147483647" step="1" x-bind:name="'items['+i+'][quantity]'" x-model="row.quantity" required></label><label>Giá nhập/đôi (VND) <input class="input w-full" inputmode="numeric" pattern="[0-9]+" x-bind:name="'items['+i+'][unit_cost_amount]'" x-model="row.unit_cost_amount" required></label></div>
<button type="button" class="underline" x-on:click="rows.splice(i,1)">Bỏ dòng</button></fieldset></template>
<button type="button" class="underline" x-on:click="rows.push({variant_id:'',quantity:1,unit_cost_amount:''})">Thêm dòng SKU</button>
<p class="text-sm">Tổng do server tính. Phiếu nháp không đổi tồn kho. Giá0 chỉ được Admin ghi sổ với lý do nhập tặng ít nhất20 ký tự.</p>
<button class="btn" type="submit">Lưu phiếu nháp</button>
</form></div>
@endsection
