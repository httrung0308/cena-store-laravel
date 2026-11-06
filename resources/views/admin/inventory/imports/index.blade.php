@extends('layouts.admin')
@section('title', 'Phiếu Nhập Kho')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5" href="{{ route('admin.stock-imports.create') }}">
        <span>+ Tạo phiếu nháp</span>
    </a>
@endsection
@php($badges = ['DRAFT' => 'bg-warning-bg text-warning border border-warning/30', 'POSTED' => 'bg-success-bg text-success border border-success/30', 'CANCELLED' => 'bg-muted/15 text-muted'])
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.stock-imports.index')" id="import-search" label="Tìm mã phiếu" placeholder="Tìm kiếm theo mã phiếu nhập..." :value="request('search', '')">
            <select class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:border-accent" name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach (App\Enums\StockImportStatus::cases() as $state)
                    <option value="{{ $state->value }}" @selected(request('status') === $state->value)>{{ $state->label() }}</option>
                @endforeach
            </select>
        </x-admin.search-form>

        <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
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
                            <td class="p-4 whitespace-nowrap text-muted text-xs">{{ $import->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }}</td>
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
