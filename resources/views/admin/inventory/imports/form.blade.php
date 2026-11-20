@extends('layouts.admin')
@section('title', $import->exists ? 'Sửa Phiếu Nháp' : 'Tạo Phiếu Nháp')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.stock-imports.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
@php
    $initialRows = is_array(old('items', $items)) ? array_values(old('items', $items)) : [];
    $initialSupplierId = (string) old('supplier_id', $import->supplier_id ?? '');
    $initialNote = (string) old('note', $import->note ?? '');
    $supplierOptions = $suppliers->map(fn ($s) => [
        'id' => (string) $s->id,
        'code' => (string) $s->code,
        'name' => (string) $s->name,
        'is_active' => (bool) $s->is_active,
    ])->values();
    $variantOptions = $variants->map(fn ($v) => [
        'id' => (string) $v->id,
        'sku' => (string) $v->sku,
        'product' => (string) ($v->color?->product?->name ?? ''),
        'color' => (string) ($v->color?->color_name ?? ''),
        'size' => (string) $v->size_value,
        'sellable' => max(0, (int) $v->stock_qty - (int) $v->reserved_qty),
        'is_active' => (bool) $v->is_active,
    ])->values();
@endphp
<div class="max-w-5xl mx-auto space-y-8"
     x-data="{
         selectedSupplierId: @js($initialSupplierId),
         note: @js($initialNote),
         suppliers: @js($supplierOptions),
         normalizeVi(str) {
             return String(str ?? '')
                 .normalize('NFD')
                 .replace(/[\u0300-\u036f]/g, '')
                 .replace(/đ/g, 'd')
                 .replace(/Đ/g, 'D')
                 .toLowerCase()
                 .trim();
         },
         findSupplier(id) {
             const target = String(id ?? '').trim();
             if (!target) return null;
             return this.suppliers.find((s) => s.id === target) ?? null;
         }
     }">
@if ($errors->spreadsheet->any())
    <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs font-semibold text-error space-y-2" data-spreadsheet-errors>
        <p class="font-bold">Chưa đọc được một số dòng từ file Excel / CSV. Các dòng hợp lệ đã được điền sẵn bên dưới:</p>
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->spreadsheet->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $import->exists ? route('admin.stock-imports.update', $import) : route('admin.stock-imports.store') }}"
      class="space-y-8"
      x-data="{
          rows: @js($initialRows),
          variants: @js($variantOptions),
          cleanDigits(val) {
              return String(val ?? '').replace(/[^0-9]/g, '');
          },
          formatThousands(val) {
              const clean = this.cleanDigits(val);
              if (!clean) return '';
              return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
          },
          formatVnd(val) {
              const clean = this.cleanDigits(val);
              if (!clean) return '0 ₫';
              return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ₫';
          },
          findVariant(id) {
              const target = String(id ?? '').trim();
              if (!target) return null;
              return this.variants.find(v => v.id === target) ?? null;
          },
          filterVariants(query) {
              const q = String(query ?? '').trim().toLowerCase();
              if (!q) return this.variants;
              const tokens = q.split(/\s+/).filter(Boolean);
              return this.variants.filter(v => {
                  const hay = (v.sku + ' ' + v.product + ' ' + v.color + ' size ' + v.size).toLowerCase();
                  return tokens.every(t => hay.includes(t));
              });
          },
          isDuplicateVariant(index) {
              const vid = String(this.rows[index]?.variant_id ?? '').trim();
              if (!vid) return false;
              return this.rows.some((r, idx) => idx !== index && String(r.variant_id ?? '').trim() === vid);
          },
          get totalQuantity() {
              return this.rows.reduce((sum, r) => sum + (parseInt(r.quantity, 10) || 0), 0);
          },
          get totalEstimatedCost() {
              return this.rows.reduce((sum, r) => {
                  const qty = parseInt(r.quantity, 10) || 0;
                  const cost = parseInt(this.cleanDigits(r.unit_cost_amount), 10) || 0;
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
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-5 relative"
                 x-data="{
                     open: false,
                     query: '',
                     activeIndex: -1,
                     get filtered() {
                         const q = normalizeVi(this.query);
                         if (!q) return suppliers;
                         const tokens = q.split(/\s+/).filter(Boolean);
                         return suppliers.filter((s) => {
                             const hay = normalizeVi(s.code + ' ' + s.name);
                             return tokens.every((t) => hay.includes(t));
                         });
                     },
                     openDropdown() {
                         this.open = true;
                         const idx = this.filtered.findIndex((s) => s.id === String(selectedSupplierId));
                         this.activeIndex = idx >= 0 ? idx : (this.filtered.length > 0 ? 0 : -1);
                         this.$nextTick(() => this.$refs.supplierSearch?.focus());
                     },
                     closeDropdown() {
                         this.open = false;
                         this.query = '';
                         this.activeIndex = -1;
                     },
                     toggleDropdown() {
                         if (this.open) {
                             this.closeDropdown();
                         } else {
                             this.openDropdown();
                         }
                     },
                     moveActive(delta) {
                         const list = this.filtered;
                         if (!list.length) {
                             this.activeIndex = -1;
                             return;
                         }
                         if (this.activeIndex < 0) {
                             this.activeIndex = delta > 0 ? 0 : list.length - 1;
                         } else {
                             this.activeIndex = (this.activeIndex + delta + list.length) % list.length;
                         }
                         this.$nextTick(() => {
                             const el = this.$refs.supplierListbox?.children[this.activeIndex];
                             el?.scrollIntoView({ block: 'nearest' });
                         });
                     },
                     selectSupplier(supplier) {
                         selectedSupplierId = supplier.id;
                         this.closeDropdown();
                         this.$nextTick(() => this.$refs.supplierTrigger?.focus());
                     },
                     selectActive() {
                         const list = this.filtered;
                         if (this.activeIndex >= 0 && this.activeIndex < list.length) {
                             this.selectSupplier(list[this.activeIndex]);
                         }
                     }
                 }"
                 @click.outside="closeDropdown()"
                 @keydown.escape.stop="closeDropdown(); $refs.supplierTrigger?.focus()">
                <label for="import-supplier" class="admin-label">Nhà cung cấp <span class="text-error">*</span></label>
                <input type="hidden" name="supplier_id" :value="selectedSupplierId">

                <button type="button"
                        id="import-supplier"
                        x-ref="supplierTrigger"
                        @click="toggleDropdown()"
                        @keydown.arrow-down.prevent="openDropdown()"
                        @keydown.arrow-up.prevent="openDropdown()"
                        :aria-expanded="open.toString()"
                        aria-haspopup="listbox"
                        class="input w-full border-border bg-surface text-main text-xs text-left flex items-center justify-between gap-2 min-h-[38px]">
                    <span class="min-w-0 flex-1 truncate flex items-center gap-1.5" x-show="findSupplier(selectedSupplierId)" x-cloak>
                        <span class="font-mono font-bold text-accent" x-text="findSupplier(selectedSupplierId)?.code"></span>
                        <span class="text-main truncate" x-text="'— ' + (findSupplier(selectedSupplierId)?.name ?? '')"></span>
                        <span x-show="findSupplier(selectedSupplierId) && !findSupplier(selectedSupplierId)?.is_active"
                              class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold border bg-error-bg text-error border-error/30">
                            Ngừng hợp tác
                        </span>
                    </span>
                    <span class="text-muted truncate" x-show="!findSupplier(selectedSupplierId)">-- Chọn nhà cung cấp --</span>
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" x-cloak
                     class="absolute left-0 right-0 z-30 mt-1 rounded-xl border border-border bg-surface-card shadow-elevated overflow-hidden">
                    <div class="p-2 border-b border-border bg-surface">
                        <label for="supplier-search" class="sr-only">Tìm nhà cung cấp theo mã hoặc tên</label>
                        <input id="supplier-search"
                               x-ref="supplierSearch"
                               type="search"
                               x-model="query"
                               @input="activeIndex = filtered.length > 0 ? 0 : -1"
                               @keydown.arrow-down.prevent="moveActive(1)"
                               @keydown.arrow-up.prevent="moveActive(-1)"
                               @keydown.enter.prevent="selectActive()"
                               @keydown.escape.prevent.stop="closeDropdown(); $refs.supplierTrigger?.focus()"
                               placeholder="Tìm theo mã hoặc tên nhà cung cấp..."
                               class="input w-full border-border bg-bg text-xs text-main py-1.5 px-2.5">
                    </div>
                    <ul x-ref="supplierListbox" role="listbox" class="max-h-60 overflow-y-auto divide-y divide-border/50">
                        <template x-for="(s, idx) in filtered" :key="s.id">
                            <li role="option" :aria-selected="String(selectedSupplierId) === s.id ? 'true' : 'false'">
                                <button type="button"
                                        @click="selectSupplier(s)"
                                        @mouseenter="activeIndex = idx"
                                        class="w-full px-3 py-2 text-left text-xs hover:bg-surface transition-colors flex items-center justify-between gap-2"
                                        :class="[
                                            String(selectedSupplierId) === s.id ? 'bg-accent/10' : '',
                                            activeIndex === idx ? 'bg-surface ring-1 ring-inset ring-accent/40' : ''
                                        ]">
                                    <span class="min-w-0 flex-1 truncate">
                                        <span class="font-mono font-bold text-accent" x-text="s.code"></span>
                                        <span class="text-main" x-text="' — ' + s.name"></span>
                                    </span>
                                    <span x-show="!s.is_active"
                                          class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold border bg-error-bg text-error border-error/30">
                                        Ngừng hợp tác
                                    </span>
                                </button>
                            </li>
                        </template>
                        <li x-show="filtered.length === 0" class="px-3 py-4 text-center text-xs text-muted">
                            Không tìm thấy nhà cung cấp phù hợp
                        </li>
                    </ul>
                </div>
                <x-forms.field-error name="supplier_id" />
            </div>

            <div class="md:col-span-7">
                <label for="import-note" class="admin-label">Ghi chú / Lý do nhập tặng</label>
                <input id="import-note" type="text" class="input w-full border-border bg-surface text-main text-xs" name="note"
                       x-model="note" placeholder="Ví dụ: Nhập bổ sung đợt 1, Lô hàng khuyến mãi...">
                <p class="mt-1 text-[11px] text-muted">Nếu có dòng nhập giá 0 ₫ (hàng tặng/khuyến mãi), cần ghi rõ lý do tối thiểu 20 ký tự.</p>
                <x-forms.field-error name="note" />
            </div>
        </div>
    </section>

    <section class="admin-card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-main">Các dòng sản phẩm & SKU</h2>
                <p class="text-xs text-muted mt-0.5">Tìm chọn biến thể theo SKU, tên giày, màu hoặc size; nhập số lượng và đơn giá vốn tương ứng.</p>
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
                <fieldset class="min-w-0 space-y-3 rounded-xl border p-4 transition-colors"
                          :class="isDuplicateVariant(i) ? 'border-error bg-error-bg/20' : 'border-border bg-surface/30'">
                    <div class="flex items-center justify-between">
                        <legend class="px-2 font-bold text-main text-xs uppercase" x-text="'Dòng ' + (i + 1)"></legend>
                        <button type="button" class="text-xs font-bold text-error hover:underline" x-on:click="removeRow(i)">
                            Bỏ dòng
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-6 lg:grid-cols-12 items-end">
                        <div class="sm:col-span-6 lg:col-span-6 relative"
                             x-data="{ open: false, query: '' }"
                             @click.outside="open = false"
                             @keydown.escape.stop="open = false">
                            <span class="block text-xs font-semibold text-muted">SKU / Biến thể sản phẩm <span class="text-error">*</span></span>
                            <input type="hidden" :name="'items['+i+'][variant_id]'" :value="row.variant_id">

                            <button type="button"
                                    @click="open = !open; if (open) { $nextTick(() => $refs.skuSearch?.focus()); }"
                                    :aria-expanded="open.toString()"
                                    aria-haspopup="listbox"
                                    class="input mt-1 w-full border-border bg-surface text-xs text-left flex items-center justify-between gap-2 min-h-[38px]"
                                    :class="isDuplicateVariant(i) ? 'border-error ring-1 ring-error/40' : ''">
                                <span class="min-w-0 flex-1 truncate" x-show="findVariant(row.variant_id)" x-cloak>
                                    <span class="font-mono font-bold text-accent" x-text="findVariant(row.variant_id)?.sku"></span>
                                    <span class="text-main" x-text="' — ' + (findVariant(row.variant_id)?.product ?? '')"></span>
                                    <span class="text-muted" x-text="' / ' + (findVariant(row.variant_id)?.color ?? '') + ' / Size ' + (findVariant(row.variant_id)?.size ?? '')"></span>
                                </span>
                                <span class="text-muted truncate" x-show="!findVariant(row.variant_id)">-- Chọn SKU hoặc gõ tìm kiếm --</span>
                                <span class="shrink-0 flex items-center gap-1.5">
                                    <span x-show="findVariant(row.variant_id)" x-cloak
                                          class="px-1.5 py-0.5 rounded text-[10px] font-bold border"
                                          :class="(findVariant(row.variant_id)?.sellable ?? 0) <= 0 ? 'bg-error-bg text-error border-error/30' : ((findVariant(row.variant_id)?.sellable ?? 0) <= 5 ? 'bg-warning-bg text-warning border-warning/30' : 'bg-surface-alt text-muted border-border')"
                                          x-text="'Tồn: ' + (findVariant(row.variant_id)?.sellable ?? 0)"></span>
                                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </span>
                            </button>

                            <div x-show="open" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 rounded-xl border border-border bg-surface-card shadow-elevated overflow-hidden">
                                <div class="p-2 border-b border-border bg-surface">
                                    <label :for="'sku-search-' + i" class="sr-only">Tìm SKU, tên sản phẩm, màu sắc hoặc size</label>
                                    <input :id="'sku-search-' + i"
                                           x-ref="skuSearch"
                                           type="search"
                                           x-model="query"
                                           placeholder="Gõ SKU, tên giày, màu, size..."
                                           class="input w-full border-border bg-bg text-xs text-main py-1.5 px-2.5">
                                </div>
                                <ul role="listbox" class="max-h-60 overflow-y-auto divide-y divide-border/50">
                                    <template x-for="v in filterVariants(query)" :key="v.id">
                                        <li>
                                            <button type="button"
                                                    @click="row.variant_id = v.id; open = false; query = ''"
                                                    class="w-full px-3 py-2 text-left text-xs hover:bg-surface transition-colors flex items-center justify-between gap-2"
                                                    :class="String(row.variant_id) === v.id ? 'bg-accent/10' : ''">
                                                <span class="min-w-0 flex-1">
                                                    <span class="font-mono font-bold text-accent" x-text="v.sku"></span>
                                                    <span class="text-error text-[10px] font-bold ml-1" x-show="!v.is_active">(ngừng bán)</span>
                                                    <span class="block text-[11px] text-main truncate" x-text="v.product + ' · ' + v.color + ' · Size ' + v.size"></span>
                                                </span>
                                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                      :class="v.sellable <= 0 ? 'bg-error-bg text-error border-error/30' : (v.sellable <= 5 ? 'bg-warning-bg text-warning border-warning/30' : 'bg-surface text-muted border-border')"
                                                      x-text="'Tồn: ' + v.sellable + ' đôi'"></span>
                                            </button>
                                        </li>
                                    </template>
                                    <li x-show="filterVariants(query).length === 0" class="px-3 py-4 text-center text-xs text-muted">
                                        Không tìm thấy biến thể phù hợp.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="sm:col-span-2 lg:col-span-2">
                            <label class="text-xs font-semibold text-muted block">Số lượng <span class="text-error">*</span>
                                <input class="input mt-1 w-full border-border bg-surface text-xs font-mono font-normal text-main"
                                       type="number" min="1" max="2147483647" step="1"
                                       x-bind:name="'items['+i+'][quantity]'" x-model.number="row.quantity" placeholder="1" required>
                            </label>
                        </div>

                        <div class="sm:col-span-2 lg:col-span-2">
                            <label class="text-xs font-semibold text-muted block">Giá nhập/đôi (₫) <span class="text-error">*</span>
                                <input type="hidden" x-bind:name="'items['+i+'][unit_cost_amount]'" :value="cleanDigits(row.unit_cost_amount)">
                                <div class="relative mt-1">
                                    <input class="input w-full border-border bg-surface text-xs font-mono font-normal text-main pr-7 text-right"
                                           type="text"
                                           inputmode="numeric"
                                           :value="formatThousands(row.unit_cost_amount)"
                                           @input="row.unit_cost_amount = cleanDigits($event.target.value); $event.target.value = formatThousands(row.unit_cost_amount)"
                                           placeholder="950.000"
                                           required>
                                    <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted">₫</span>
                                </div>
                            </label>
                        </div>

                        <div class="sm:col-span-2 lg:col-span-2">
                            <span class="text-[11px] font-semibold text-muted block">Thành tiền dòng</span>
                            <div class="input mt-1 w-full border-border bg-surface-alt/70 text-xs font-mono font-bold text-accent flex items-center justify-end px-3">
                                <span x-text="formatVnd((parseInt(row.quantity, 10) || 0) * (parseInt(cleanDigits(row.unit_cost_amount), 10) || 0))"></span>
                            </div>
                        </div>
                    </div>

                    <p x-show="isDuplicateVariant(i)" x-cloak role="alert"
                       class="text-xs font-semibold text-error flex items-center gap-1.5 pt-1">
                        <span aria-hidden="true">⚠</span>
                        <span>Biến thể này đang bị trùng với một dòng khác trong cùng phiếu. Vui lòng chọn biến thể khác hoặc bỏ dòng thừa.</span>
                    </p>
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

        <p class="text-xs text-muted">Lưu phiếu nháp chưa làm thay đổi số lượng tồn kho hay giá vốn bình quân. Tồn kho và sổ quỹ chỉ được cập nhật khi bạn bấm <strong>Ghi sổ</strong> ở bước tiếp theo.</p>
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
