@extends('layouts.admin')
@section('title', 'Phiếu Nhập Kho')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" x-data x-on:click="$dispatch('toggle-import-panel')"
                class="px-4 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors shadow-sm">
            Nhập từ Excel / CSV
        </button>
        <a class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5" href="{{ route('admin.stock-imports.create') }}">
            <span>+ Tạo phiếu nháp</span>
        </a>
    </div>
@endsection
@php
    $badges = [
        'DRAFT' => 'bg-warning-bg text-warning border border-warning/30',
        'POSTED' => 'bg-success-bg text-success border border-success/30',
        'CANCELLED' => 'bg-muted/15 text-muted',
    ];
    $tz = (string) config('store.business_timezone');
    $searchValue = (string) ($filters['search'] ?? request('search', ''));
    $statusValue = (string) ($filters['status'] ?? request('status', ''));
    $supplierValue = (string) ($filters['supplier_id'] ?? request('supplier_id', ''));

    $activeChips = [];
    if (filled($searchValue)) {
        $activeChips[] = [
            'label' => 'Mã phiếu: '.$searchValue,
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($statusValue)) {
        $statusLabel = App\Enums\StockImportStatus::tryFrom($statusValue)?->label() ?? $statusValue;
        $activeChips[] = [
            'label' => 'Trạng thái: '.$statusLabel,
            'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null]),
        ];
    }
    if (filled($supplierValue)) {
        $selectedSupplier = $suppliers->firstWhere('id', (int) $supplierValue);
        $supplierLabel = $selectedSupplier ? $selectedSupplier->code.' — '.$selectedSupplier->name : '#'.$supplierValue;
        $activeChips[] = [
            'label' => 'NCC: '.$supplierLabel,
            'url' => request()->fullUrlWithQuery(['supplier_id' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
    <div x-data="{
            ...adminAjaxList(),
            showImport: @js($errors->spreadsheet->any() || $errors->has('file')),
            toggleImport() { this.showImport = !this.showImport; }
         }"
         x-on:toggle-import-panel.window="toggleImport()"
         class="space-y-6">
        <section x-show="showImport"
                 x-cloak
                 x-transition
                 class="admin-card space-y-4 border-accent/40 bg-surface/80"
                 aria-labelledby="spreadsheet-title"
                 data-spreadsheet-import>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="spreadsheet-title" class="text-base font-bold text-main">Nhập nhanh từ file Excel / CSV</h2>
                    <p class="mt-1 text-xs text-muted">File cần có dòng tiêu đề với 3 cột <strong class="text-main">SKU</strong>, <strong class="text-main">Số lượng</strong> và <strong class="text-main">Giá nhập</strong> (thứ tự cột tùy ý). Các dòng hợp lệ sẽ được mở sang trang tạo phiếu nháp để bạn chọn nhà cung cấp và rà soát trước khi lưu.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.stock-imports.template', ['format' => 'xlsx']) }}"
                       class="px-3 py-1.5 bg-surface hover:bg-border/40 text-accent font-bold text-xs rounded-lg border border-border transition-colors shadow-sm">
                        Tải file mẫu (.xlsx)
                    </a>
                    <a href="{{ route('admin.stock-imports.template', ['format' => 'csv']) }}"
                       class="px-3 py-1.5 bg-surface hover:bg-border/40 text-muted hover:text-main font-bold text-xs rounded-lg border border-border transition-colors shadow-sm">
                        Tải file mẫu (.csv)
                    </a>
                    <button type="button" x-on:click="showImport = false" class="text-muted hover:text-main text-xs font-semibold px-2 py-1 ml-1" aria-label="Đóng khung nhập file">Đóng</button>
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
                    <p class="font-bold">Chưa đọc được file hoặc có dòng lỗi. Vui lòng kiểm tra lại:</p>
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->spreadsheet->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <x-admin.search-form :action="route('admin.stock-imports.index')"
                             id="import-search"
                             label="Tìm mã phiếu"
                             placeholder="Tìm kiếm theo mã phiếu nhập..."
                             :value="$searchValue"
                             :ajax="true"
                             :active-chips="$activeChips">
            <x-slot:filters>
                <x-admin.filter-select id="import-status-filter" name="status" label="Trạng thái:" :active="filled($statusValue)">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\StockImportStatus::cases() as $state)
                        <option value="{{ $state->value }}" @selected($statusValue === $state->value)>{{ $state->label() }}</option>
                    @endforeach
                </x-admin.filter-select>

                <x-admin.filter-select id="import-supplier-filter" name="supplier_id" label="Nhà cung cấp:" :active="filled($supplierValue)" class="max-w-[14rem] sm:max-w-[18rem] truncate">
                    <option value="">Tất cả</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierValue === (string) $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}</option>
                    @endforeach
                </x-admin.filter-select>
            </x-slot:filters>
        </x-admin.search-form>

        <div id="admin-list-region"
             x-ref="region"
             @click="handleRegionClick($event)"
             :class="{ 'opacity-60 pointer-events-none': listLoading }"
             class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm transition-opacity">
            <div class="px-4 py-2.5 border-b border-border bg-surface/50 flex flex-wrap items-center justify-between gap-2 text-xs">
                <span class="text-muted">
                    Tìm thấy <strong class="text-main font-extrabold tabular-nums">{{ $imports->total() }}</strong> phiếu nhập
                    @if (! empty($activeChips))
                        <span class="text-accent font-semibold">(đang áp dụng bộ lọc)</span>
                    @endif
                </span>
            </div>

            {{-- Mobile summary cards (< md) --}}
            <div class="md:hidden divide-y divide-border">
                @forelse ($imports as $import)
                    <article class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <a class="font-mono font-bold text-accent hover:underline text-sm" href="{{ route('admin.stock-imports.show', $import) }}">
                                {{ $import->import_code }}
                            </a>
                            <span class="shrink-0 px-2.5 py-0.5 rounded-full font-bold text-[11px] {{ $badges[$import->status->value] ?? 'bg-surface text-muted border border-border' }}">
                                {{ $import->status->label() }}
                            </span>
                        </div>
                        <p class="text-xs font-bold text-main">{{ $import->supplier_name_snapshot }}</p>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="bg-surface text-main border border-border px-2 py-0.5 rounded-lg font-mono font-bold text-[11px]">{{ $import->total_quantity }} đôi</span>
                                <span class="font-extrabold text-accent">{{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</span>
                            </div>
                            <span class="text-[11px] text-muted">{{ $import->created_at?->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-xs text-muted">Chưa có phiếu nhập.</div>
                @endforelse
            </div>

            {{-- Desktop table (>= md) --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs text-muted border-collapse">
                    <thead class="bg-surface text-main font-bold border-b border-border">
                        <tr>
                            <th class="p-4">Mã phiếu</th>
                            <th class="p-4">Nhà cung cấp</th>
                            <th class="p-4 text-center">Trạng thái</th>
                            <th class="p-4 text-center">Số lượng</th>
                            <th class="p-4">Tổng tiền nhập</th>
                            <th class="p-4">Ngày tạo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-medium">
                    @forelse ($imports as $import)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4">
                                <a class="font-mono font-bold text-accent hover:underline text-xs" href="{{ route('admin.stock-imports.show', $import) }}">{{ $import->import_code }}</a>
                            </td>
                            <td class="p-4 font-bold text-main">{{ $import->supplier_name_snapshot }}</td>
                            <td class="p-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px] {{ $badges[$import->status->value] ?? 'bg-surface text-muted border border-border' }}">{{ $import->status->label() }}</span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="bg-surface text-main border border-border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]">{{ $import->total_quantity }} đôi</span>
                            </td>
                            <td class="p-4 whitespace-nowrap font-extrabold text-accent text-xs">{{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</td>
                            <td class="p-4 whitespace-nowrap text-muted text-xs">{{ $import->created_at?->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-12 text-center text-xs text-muted">Chưa có phiếu nhập.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$imports" />
        </div>
    </div>
@endsection
