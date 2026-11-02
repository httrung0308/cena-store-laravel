@extends('layouts.admin')
@section('title', 'Chi tiết phiếu nhập')
@section('eyebrow', 'Quản lý nhập kho')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.stock-imports.index') }}">Danh sách phiếu</a>
@endsection
@php($badges = ['DRAFT' => 'bg-amber-100 text-amber-700', 'POSTED' => 'bg-green-100 text-green-600', 'CANCELLED' => 'bg-zinc-100 text-zinc-600'])
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <section class="admin-card space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3"><p class="break-all text-lg font-semibold">{{ $import->import_code }}</p><span class="badge {{ $badges[$import->status->value] ?? 'bg-zinc-100 text-zinc-600' }}">{{ $import->status->label() }}</span></div>
        <div class="grid gap-2 text-sm text-zinc-500 sm:grid-cols-2">
            <p>Nhà cung cấp: <span class="font-medium text-zinc-900">{{ $import->supplier_code_snapshot }} — {{ $import->supplier_name_snapshot }}</span></p>
            <p>Người tạo: <span class="font-medium text-zinc-900">{{ $import->createdBy?->full_name }}</span></p>
            @if ($import->posted_at)<p>Ghi sổ: <span class="font-medium text-zinc-900">{{ $import->posted_at->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }} — {{ $import->postedBy?->full_name }}</span></p>@endif
        </div>
        @if ($import->note)<p class="whitespace-pre-wrap rounded-xl bg-zinc-50 p-3 text-sm">{{ $import->note }}</p>@endif
    </section>
    <div class="admin-table-wrap"><table class="admin-table min-w-[600px]"><thead><tr><th>SKU snapshot</th><th>Số lượng</th><th>Giá nhập/đôi</th><th>Thành tiền</th></tr></thead><tbody>
        @foreach ($import->items as $line)<tr><td class="font-semibold text-zinc-900">{{ $line->sku_snapshot }}</td><td>{{ $line->quantity }}</td><td class="whitespace-nowrap">{{ number_format($line->unit_cost_amount, 0, ',', '.') }} ₫</td><td class="whitespace-nowrap">{{ number_format($line->subtotal_amount, 0, ',', '.') }} ₫</td></tr>@endforeach
    </tbody></table></div>
    <p class="text-right text-lg font-semibold">Tổng {{ $import->total_quantity }} đôi — {{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</p>
    @if ($import->status === App\Enums\StockImportStatus::Draft)
        <section class="admin-card space-y-4">
            <h2 class="text-xl font-semibold">Xử lý phiếu nháp</h2>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.stock-imports.post', $import) }}">@csrf<button class="btn-pill-dark px-6 py-3">Ghi sổ nhập kho</button></form>
                <a class="btn-pill px-6 py-3" href="{{ route('admin.stock-imports.edit', $import) }}">Sửa nháp</a>
            </div>
            <form class="space-y-2 border-t border-zinc-100 pt-4" method="POST" action="{{ route('admin.stock-imports.cancel', $import) }}">@csrf
                <label for="cancel-reason" class="admin-label">Lý do hủy</label>
                <div class="flex flex-wrap gap-2"><input id="cancel-reason" class="input w-full max-w-xl border-zinc-300" name="reason" required maxlength="500"><button class="btn-pill text-red-500">Hủy phiếu nháp</button></div>
            </form>
        </section>
    @else
        <p class="rounded-3xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">Phiếu kết thúc, không sửa hoặc xóa. Sai sót phiếu đã ghi sổ phải được xử lý theo quy trình điều chỉnh kho.</p>
    @endif
</div>
@endsection
