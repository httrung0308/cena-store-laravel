@extends('layouts.admin')
@section('title', $import->exists ? 'Sửa phiếu nháp' : 'Tạo phiếu nháp')
@section('eyebrow', 'Quản lý nhập kho')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.stock-imports.index') }}">Quay lại danh sách</a>
@endsection
@section('content')
<div class="max-w-5xl">
<form method="POST" action="{{ $import->exists ? route('admin.stock-imports.update', $import) : route('admin.stock-imports.store') }}" class="space-y-8" x-data="{rows:@js(is_array(old('items', $items)) ? array_values(old('items', $items)) : [])}">
    @csrf @if ($import->exists) @method('PUT') @else<input type="hidden" name="command_key" value="{{ old('command_key', $commandKey) }}">@endif
    <section class="admin-card space-y-4">
        <h2 class="text-xl font-semibold">Thông tin phiếu</h2>
        <div><label for="import-supplier" class="admin-label">Nhà cung cấp <span class="text-red-500">*</span></label><select id="import-supplier" class="input w-full border-zinc-300" name="supplier_id" required><option value="">Chọn nhà cung cấp</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $import->supplier_id) === (string) $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}{{ $supplier->is_active ? '' : ' (đã ngừng)' }}</option>@endforeach</select></div>
        <div><label for="import-note" class="admin-label">Ghi chú / lý do nhập tặng</label><textarea id="import-note" class="input w-full border-zinc-300" name="note" rows="3">{{ old('note', $import->note) }}</textarea></div>
    </section>
    <section class="admin-card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">Các dòng SKU</h2><button type="button" class="btn-pill-dark" x-on:click="rows.push({variant_id:'',quantity:1,unit_cost_amount:''})">+ Thêm dòng SKU</button></div>
        <p x-show="rows.length === 0" class="text-sm text-zinc-500">Chưa có dòng nào. Bấm “Thêm dòng SKU”.</p>
        <template x-for="(row,i) in rows" :key="i"><fieldset class="min-w-0 space-y-3 rounded-xl border border-zinc-200 p-4"><legend class="px-2 font-semibold text-zinc-700" x-text="'Dòng '+(i+1)"></legend>
            <label class="block text-xs font-semibold text-zinc-600">SKU <span class="text-red-500">*</span><select class="input mt-1 w-full border-zinc-300 text-sm font-normal" x-bind:name="'items['+i+'][variant_id]'" x-model="row.variant_id" required><option value="">Chọn SKU</option>@foreach ($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->sku }} — {{ $variant->color?->product?->name }} / {{ $variant->color?->color_name }} / {{ $variant->size_value }}{{ $variant->is_active ? '' : ' (ngừng bán)' }}</option>@endforeach</select></label>
            <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-zinc-600">Số lượng <span class="text-red-500">*</span><input class="input mt-1 w-full border-zinc-300 text-sm font-normal" type="number" min="1" max="2147483647" step="1" x-bind:name="'items['+i+'][quantity]'" x-model="row.quantity" required></label><label class="text-xs font-semibold text-zinc-600">Giá nhập/đôi (₫) <span class="text-red-500">*</span><input class="input mt-1 w-full border-zinc-300 text-sm font-normal" inputmode="numeric" pattern="[0-9]+" x-bind:name="'items['+i+'][unit_cost_amount]'" x-model="row.unit_cost_amount" required></label></div>
            <button type="button" class="text-sm font-semibold text-red-500 hover:text-red-700" x-on:click="rows.splice(i,1)">Bỏ dòng</button>
        </fieldset></template>
        <p class="text-sm text-zinc-500">Tổng do server tính. Phiếu nháp không đổi tồn kho. Giá 0 chỉ được Admin ghi sổ với lý do nhập tặng ít nhất 20 ký tự.</p>
    </section>
    <button class="btn-pill-dark px-8 py-3" type="submit">Lưu phiếu nháp</button>
</form>
</div>
@endsection
