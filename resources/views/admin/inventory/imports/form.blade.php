@extends('layouts.admin')
@section('title', $import->exists ? 'Sửa Phiếu Nháp' : 'Tạo Phiếu Nháp')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.stock-imports.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
<div class="max-w-5xl space-y-8">
@unless ($import->exists)
    <section class="admin-card space-y-4" aria-labelledby="spreadsheet-title" data-spreadsheet-import>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="spreadsheet-title" class="text-base font-bold text-main flex items-center gap-2">
                    <span>📊</span> <span>Nhập nhanh từ file Excel / CSV</span>
                </h2>
                <p class="mt-1 text-xs text-muted">File cần có dòng tiêu đề với 3 cột <strong class="text-main">SKU</strong>, <strong class="text-main">Số lượng</strong> và <strong class="text-main">Giá nhập</strong> (thứ tự cột tùy ý). Các dòng hợp lệ sẽ được điền vào phiếu bên dưới để bạn rà soát trước khi lưu.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.stock-imports.template', ['format' => 'xlsx']) }}"
                   class="px-3 py-1.5 bg-surface hover:bg-border/40 text-accent font-bold text-xs rounded-lg border border-border transition-colors flex items-center gap-1 shadow-sm">
                    <span>📊</span> Tải file mẫu (.xlsx)
                </a>
                <a href="{{ route('admin.stock-imports.template', ['format' => 'csv']) }}"
                   class="px-3 py-1.5 bg-surface hover:bg-border/40 text-muted hover:text-main font-bold text-xs rounded-lg border border-border transition-colors flex items-center gap-1 shadow-sm">
                    <span>📄</span> Tải file mẫu (.csv)
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.stock-imports.spreadsheet') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3 pt-1">
            @csrf
            <label for="spreadsheet-file" class="sr-only">File Excel hoặc CSV</label>
            <input id="spreadsheet-file" type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required
                   class="block w-full max-w-md text-xs text-main file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-surface file:text-main file:border file:border-border hover:file:bg-border/40 cursor-pointer">
            <button type="submit" class="px-5 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md">
                Đọc file vào phiếu
            </button>
        </form>
        <p class="text-[11px] text-muted">Nhận .xlsx hoặc .csv, tối đa 2 MB và {{ App\Services\StockImportSpreadsheet::MAX_LINES }} dòng. Các cột phụ khác (tên giày, màu, size...) sẽ được tự động bỏ qua.</p>

        @if ($errors->spreadsheet->any())
            <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs font-semibold text-error space-y-2" data-spreadsheet-errors>
                <p class="font-bold">Chưa đọc được một số dòng. Bạn có thể sửa trực tiếp trong file rồi tải lại, hoặc chỉnh sửa ở bảng bên dưới:</p>
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->spreadsheet->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endunless

@php
    $initialRows = is_array(old('items', $items)) ? array_values(old('items', $items)) : [];
@endphp
<form method="POST" action="{{ $import->exists ? route('admin.stock-imports.update', $import) : route('admin.stock-imports.store') }}"
      class="space-y-8"
      x-data="{
          rows: @js($initialRows),
          formatVnd(val) {
              const clean = String(val ?? '').replace(/[^0-9]/g, '');
              if (!clean) return '0 ₫';
              return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ₫';
          },
          get totalQuantity() {
              return this.rows.reduce((sum, r) => sum + (parseInt(r.quantity, 10) || 0), 0);
          },
          get totalEstimatedCost() {
              return this.rows.reduce((sum, r) => {
                  const qty = parseInt(r.quantity, 10) || 0;
                  const cost = parseInt(String(r.unit_cost_amount).replace(/[^0-9]/g, ''), 10) || 0;
                  return sum + (qty * cost);
              }, 0);
          },
          addRow() {
              this.rows.push({ variant_id: '', quantity: 1, unit_cost_amount: '' });
          },
          removeRow(index) {
              this.rows.splice(index, 1);
          }
      }">
    @csrf
    @if ($import->exists)
        @method('PUT')
    @else
        <input type="hidden" name="command_key" value="{{ old('command_key', $commandKey) }}">
    @endif

    <section class="admin-card space-y-4">
        <h2 class="text-base font-bold text-main">Thông tin phiếu nhập</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="import-supplier" class="admin-label text-muted">Nhà cung cấp <span class="text-error">*</span></label>
                <select id="import-supplier" class="input w-full border-border bg-surface text-main text-xs" name="supplier_id" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $import->supplier_id) === (string) $supplier->id)>
                            {{ $supplier->code }} — {{ $supplier->name }}{{ $supplier->is_active ? '' : ' (đã ngừng hợp tác)' }}
                        </option>
                    @endforeach
                </select>
                <x-forms.field-error name="supplier_id" />
            </div>

            <div>
                <label for="import-note" class="admin-label text-muted">Ghi chú / Lý do nhập tặng</label>
                <input id="import-note" type="text" class="input w-full border-border bg-surface text-main text-xs" name="note"
                       value="{{ old('note', $import->note) }}" placeholder="Ví dụ: Nhập bổ sung đợt 1, Lô hàng khuyến mãi...">
                <p class="mt-1 text-[11px] text-muted">Với hàng nhập tặng giá 0₫, yêu cầu ghi rõ lý do tối thiểu 20 ký tự.</p>
                <x-forms.field-error name="note" />
            </div>
        </div>
    </section>

    <section class="admin-card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-main">Các dòng sản phẩm & SKU</h2>
                <p class="text-xs text-muted mt-0.5">Chọn biến thể, số lượng nhập và đơn giá vốn tương ứng.</p>
            </div>
            <button type="button" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm"
                    x-on:click="addRow()">
                + Thêm dòng SKU
            </button>
        </div>

        <p x-show="rows.length === 0" class="text-xs text-muted py-4 text-center border border-dashed border-border rounded-xl">
            Phiếu chưa có dòng nào. Bấm <strong>“+ Thêm dòng SKU”</strong> hoặc tải file Excel ở phía trên.
        </p>

        <div class="space-y-3">
            <template x-for="(row, i) in rows" :key="i">
                <fieldset class="min-w-0 space-y-3 rounded-xl border border-border p-4 bg-surface/30">
                    <div class="flex items-center justify-between">
                        <legend class="px-2 font-bold text-main text-xs uppercase" x-text="'Dòng ' + (i + 1)"></legend>
                        <button type="button" class="text-xs font-bold text-error hover:underline" x-on:click="removeRow(i)">
                            Bỏ dòng
                        </button>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-12 items-end">
                        <div class="lg:col-span-6">
                            <label class="block text-xs font-semibold text-muted">SKU / Biến thể sản phẩm <span class="text-error">*</span>
                                <select class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main"
                                        x-bind:name="'items['+i+'][variant_id]'" x-model="row.variant_id" required>
                                    <option value="">-- Chọn SKU hoặc gõ tìm kiếm --</option>
                                    @foreach ($variants as $variant)
                                        <option value="{{ $variant->id }}">
                                            {{ $variant->sku }} — {{ $variant->color?->product?->name }} / {{ $variant->color?->color_name }} / Size {{ $variant->size_value }}{{ $variant->is_active ? '' : ' (ngừng bán)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-xs font-semibold text-muted block">Số lượng <span class="text-error">*</span>
                                <input class="input mt-1 w-full border-border bg-surface text-xs font-mono font-normal text-main"
                                       type="number" min="1" max="2147483647" step="1"
                                       x-bind:name="'items['+i+'][quantity]'" x-model.number="row.quantity" placeholder="1" required>
                            </label>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-xs font-semibold text-muted block">Giá nhập/đôi (₫) <span class="text-error">*</span>
                                <input class="input mt-1 w-full border-border bg-surface text-xs font-mono font-normal text-main"
                                       inputmode="numeric" pattern="[0-9]+"
                                       x-bind:name="'items['+i+'][unit_cost_amount]'" x-model="row.unit_cost_amount" placeholder="950000" required>
                            </label>
                        </div>

                        <div class="lg:col-span-2">
                            <span class="text-[11px] font-semibold text-muted block">Thành tiền dòng</span>
                            <div class="input mt-1 w-full border-border bg-surface-alt/70 text-xs font-mono font-bold text-accent flex items-center justify-end px-3">
                                <span x-text="formatVnd((row.quantity || 0) * (parseInt(String(row.unit_cost_amount).replace(/[^0-9]/g, '') || 0, 10)))"></span>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </template>
        </div>

        {{-- Bottom Add Row & Summary Bar --}}
        <div x-show="rows.length > 0" class="pt-2 flex flex-wrap items-center justify-between gap-4 border-t border-border/70">
            <button type="button" class="px-4 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors shadow-sm"
                    x-on:click="addRow()">
                + Thêm dòng SKU tiếp theo
            </button>

            <div class="flex items-center gap-6 bg-surface-alt/50 px-4 py-2 rounded-xl border border-border/60">
                <div class="text-xs">
                    <span class="text-muted">Tổng số lượng:</span>
                    <strong class="text-main font-mono text-sm ml-1" x-text="totalQuantity + ' đôi'"></strong>
                </div>
                <div class="text-xs">
                    <span class="text-muted">Tổng tiền tạm tính:</span>
                    <strong class="text-accent font-mono text-sm ml-1" x-text="formatVnd(totalEstimatedCost)"></strong>
                </div>
            </div>
        </div>

        <p class="text-xs text-muted">Lưu ý: Tổng thành tiền chính thức sẽ do server tính và ghi sổ kế toán. Lưu phiếu nháp chưa làm thay đổi số lượng tồn kho hay giá vốn bình quân.</p>
    </section>

    <div class="flex items-center gap-3 pt-2">
        <button class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" type="submit">
            {{ $import->exists ? 'Cập nhật phiếu nháp' : 'Lưu phiếu nháp' }}
        </button>
        <a href="{{ route('admin.stock-imports.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
    </div>
</form>
</div>
@endsection
