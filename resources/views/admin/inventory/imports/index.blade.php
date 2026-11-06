@extends('layouts.admin')
@section('title', 'Phiếu nhập kho')
@section('eyebrow', 'Quản lý nhập kho')
@section('actions')
    <a class="btn-pill-dark" href="{{ route('admin.stock-imports.create') }}">+ Tạo phiếu nháp</a>
@endsection
@php($badges = ['DRAFT' => 'bg-warning-bg text-warning-text border border-warning/30', 'POSTED' => 'bg-success-bg text-success-text border border-success/30', 'CANCELLED' => 'bg-surface-alt text-muted border border-border'])
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.stock-imports.index')" id="import-search" label="Tìm mã phiếu" placeholder="Tìm mã phiếu..." :value="request('search', '')">
            <label class="flex items-center gap-2 text-sm font-semibold text-muted">Trạng thái
                <select class="input border-border bg-surface text-main py-2 font-normal" name="status"><option value="">Tất cả</option>@foreach (App\Enums\StockImportStatus::cases() as $state)<option value="{{ $state->value }}" @selected(request('status') === $state->value)>{{ $state->label() }}</option>@endforeach</select>
            </label>
        </x-admin.search-form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã phiếu</th><th>Nhà cung cấp</th><th>Trạng thái</th><th>Số lượng</th><th>Tổng tiền nhập</th><th>Ngày tạo</th></tr></thead><tbody>
        @forelse ($imports as $import)
            <tr class="hover:bg-surface-alt/40 transition-colors">
                <td><a class="break-all font-semibold text-accent hover:underline" href="{{ route('admin.stock-imports.show', $import) }}">{{ $import->import_code }}</a></td>
                <td class="text-main">{{ $import->supplier_name_snapshot }}</td>
                <td><span class="badge {{ $badges[$import->status->value] ?? 'bg-surface-alt text-muted border border-border' }}">{{ $import->status->label() }}</span></td>
                <td class="text-main">{{ $import->total_quantity }}</td>
                <td class="whitespace-nowrap font-semibold text-main">{{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</td>
                <td class="whitespace-nowrap text-muted">{{ $import->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-20 text-center text-muted">Chưa có phiếu nhập.</td></tr>
        @endforelse
        </tbody></table></div>
        <x-ui.pagination :paginator="$imports" />
    </div>
@endsection
