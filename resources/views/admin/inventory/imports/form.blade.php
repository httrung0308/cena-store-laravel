@extends('layouts.admin')
@section('title', $import->exists ? 'Sửa phiếu nháp' : 'Tạo phiếu nháp')
@section('eyebrow', 'Quản lý nhập kho')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.stock-imports.index') }}">Quay lại danh sách</a>
@endsection
@section('content')
<div class="max-w-5xl">
@unless ($import->exists)
    <section class="admin-card mb-8 space-y-4" aria-labelledby="spreadsheet-title" data-spreadsheet-import>
        <div>
            <h2 id="spreadsheet-title" class="text-xl font-semibold">Nhập nhanh từ file Excel</h2>
            <p class="mt-1 text-sm text-zinc-500">File cần có dòng tiêu đề với 3 cột <strong>SKU</strong>, <strong>Số lượng</strong> và <strong>Giá nhập</strong> (thứ tự cột tùy ý). Các dòng sẽ được điền vào phiếu bên dưới để bạn kiểm tra trước khi lưu.</p>
        </div>
        <form method="POST" action="{{ route('admin.stock-imports.spreadsheet') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
            @csrf
            <label for="spreadsheet-file" class="sr-only">File Excel hoặc CSV</label>
            <input id="spreadsheet-file" type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required
                   class="block w-full max-w-md text-sm text-zinc-600 file:mr-3 file:rounded-full file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-zinc-900 hover:file:bg-zinc-200">
            <button type="submit" class="btn-pill-dark">Đọc file</button>
        </form>
        <p class="text-xs text-zinc-500">Nhận .xlsx hoặc .csv, tối đa 2 MB và {{ App\Services\StockImportSpreadsheet::MAX_LINES }} dòng. Cột khác (tên giày, màu, size...) được bỏ qua.</p>
        @if ($errors->spreadsheet->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" data-spreadsheet-errors>
                <p class="font-semibold">Chưa nhập được phần sau. Sửa trong file rồi tải lại, hoặc thêm tay ở phiếu bên dưới:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">@foreach ($errors->spreadsheet->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
    </section>
@endunless
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
