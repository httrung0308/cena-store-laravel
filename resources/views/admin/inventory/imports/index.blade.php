@extends('layouts.admin')
@section('title', 'Phiếu nhập kho')
@section('eyebrow', 'Quản lý nhập kho')
@section('actions')
    <a class="btn-pill-dark" href="{{ route('admin.stock-imports.create') }}">+ Tạo phiếu nháp</a>
@endsection
@php($badges = ['DRAFT' => 'bg-amber-100 text-amber-700', 'POSTED' => 'bg-green-100 text-green-600', 'CANCELLED' => 'bg-zinc-100 text-zinc-600'])
@section('content')
    <div class="space-y-6">
        <x-admin.search-form :action="route('admin.stock-imports.index')" id="import-search" label="Tìm mã phiếu" placeholder="Tìm mã phiếu..." :value="request('search', '')">
            <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">Trạng thái
                <select class="input border-zinc-300 py-2 font-normal" name="status"><option value="">Tất cả</option>@foreach (App\Enums\StockImportStatus::cases() as $state)<option value="{{ $state->value }}" @selected(request('status') === $state->value)>{{ $state->label() }}</option>@endforeach</select>
            </label>
        </x-admin.search-form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã phiếu</th><th>Nhà cung cấp</th><th>Trạng thái</th><th>Số lượng</th><th>Tổng tiền nhập</th><th>Ngày tạo</th></tr></thead><tbody>
        @forelse ($imports as $import)
            <tr>
                <td><a class="break-all font-semibold text-zinc-900 underline" href="{{ route('admin.stock-imports.show', $import) }}">{{ $import->import_code }}</a></td>
                <td>{{ $import->supplier_name_snapshot }}</td>
                <td><span class="badge {{ $badges[$import->status->value] ?? 'bg-zinc-100 text-zinc-600' }}">{{ $import->status->label() }}</span></td>
                <td>{{ $import->total_quantity }}</td>
                <td class="whitespace-nowrap font-semibold text-zinc-900">{{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</td>
                <td class="whitespace-nowrap">{{ $import->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-20 text-center text-zinc-500">Chưa có phiếu nhập.</td></tr>
        @endforelse
        </tbody></table></div>
        <x-ui.pagination :paginator="$imports" />
    </div>
@endsection
