@extends('layouts.admin')
@section('title', 'Chi tiết phiếu nhập')
@section('eyebrow', 'Quản lý nhập kho')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.stock-imports.index') }}">Danh sách phiếu</a>
@endsection
@php($badges = ['DRAFT' => 'bg-warning-bg text-warning-text border border-warning/30', 'POSTED' => 'bg-success-bg text-success-text border border-success/30', 'CANCELLED' => 'bg-surface-alt text-muted border border-border'])
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <section class="admin-card space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3"><p class="break-all text-lg font-semibold text-main">{{ $import->import_code }}</p><span class="badge {{ $badges[$import->status->value] ?? 'bg-surface-alt text-muted border border-border' }}">{{ $import->status->label() }}</span></div>
        <div class="grid gap-2 text-sm text-muted sm:grid-cols-2">
            <p>Nhà cung cấp: <span class="font-medium text-main">{{ $import->supplier_code_snapshot }} — {{ $import->supplier_name_snapshot }}</span></p>
            <p>Người tạo: <span class="font-medium text-main">{{ $import->createdBy?->full_name }}</span></p>
            @if ($import->posted_at)<p>Ghi sổ: <span class="font-medium text-main">{{ $import->posted_at->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }} — {{ $import->postedBy?->full_name }}</span></p>@endif
        </div>
        @if ($import->note)<p class="whitespace-pre-wrap rounded-xl bg-surface-alt/60 border border-border/50 p-3 text-sm text-main">{{ $import->note }}</p>@endif
    </section>
    <div class="admin-table-wrap"><table class="admin-table min-w-[600px]"><thead><tr><th>SKU snapshot</th><th>Số lượng</th><th>Giá nhập/đôi</th><th>Thành tiền</th></tr></thead><tbody>
        @foreach ($import->items as $line)<tr class="hover:bg-surface-alt/40 transition-colors"><td class="font-semibold text-main">{{ $line->sku_snapshot }}</td><td class="text-main">{{ $line->quantity }}</td><td class="whitespace-nowrap text-main">{{ number_format($line->unit_cost_amount, 0, ',', '.') }} ₫</td><td class="whitespace-nowrap font-semibold text-main">{{ number_format($line->subtotal_amount, 0, ',', '.') }} ₫</td></tr>@endforeach
    </tbody></table></div>
    <p class="text-right text-lg font-semibold text-main">Tổng {{ $import->total_quantity }} đôi — {{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</p>
    @if ($import->status === App\Enums\StockImportStatus::Draft)
        <section class="admin-card space-y-4">
            <h2 class="text-xl font-semibold text-main">Xử lý phiếu nháp</h2>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.stock-imports.post', $import) }}">@csrf<button class="btn-pill-dark px-6 py-3">Ghi sổ nhập kho</button></form>
                <a class="btn-pill px-6 py-3" href="{{ route('admin.stock-imports.edit', $import) }}">Sửa nháp</a>
            </div>
            <form class="space-y-2 border-t border-border pt-4" method="POST" action="{{ route('admin.stock-imports.cancel', $import) }}">@csrf
                <label for="cancel-reason" class="admin-label text-muted">Lý do hủy</label>
                <div class="flex flex-wrap gap-2"><input id="cancel-reason" class="input w-full max-w-xl border-border bg-surface text-main" name="reason" required maxlength="500"><button class="btn-pill text-error hover:bg-error-bg">Hủy phiếu nháp</button></div>
            </form>
        </section>
    @else
        <p class="rounded-3xl border border-border bg-surface-alt/60 p-4 text-sm text-muted">Phiếu kết thúc, không sửa hoặc xóa. Sai sót phiếu đã ghi sổ phải được xử lý theo quy trình điều chỉnh kho.</p>
    @endif
</div>
@endsection
