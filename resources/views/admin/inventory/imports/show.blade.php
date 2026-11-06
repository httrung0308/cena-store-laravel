@extends('layouts.admin')
@section('title', 'Chi Tiết Phiếu Nhập')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-all" href="{{ route('admin.stock-imports.index') }}">← Danh sách phiếu</a>
@endsection
@php($badges = ['DRAFT' => 'bg-warning-bg text-warning border border-warning/30', 'POSTED' => 'bg-success-bg text-success border border-success/30', 'CANCELLED' => 'bg-muted/15 text-muted'])
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <section class="admin-card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-mono font-bold text-lg text-accent break-all">{{ $import->import_code }}</p>
            <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px] {{ $badges[$import->status->value] ?? 'bg-surface text-muted border border-border' }}">{{ $import->status->label() }}</span>
        </div>
        <div class="grid gap-2 text-xs text-muted sm:grid-cols-2">
            <p>Nhà cung cấp: <span class="font-bold text-main">{{ $import->supplier_code_snapshot }} — {{ $import->supplier_name_snapshot }}</span></p>
            <p>Người tạo: <span class="font-bold text-main">{{ $import->createdBy?->full_name }}</span></p>
            @if ($import->posted_at)
                <p>Ghi sổ: <span class="font-bold text-main">{{ $import->posted_at->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }} — {{ $import->postedBy?->full_name }}</span></p>
            @endif
        </div>
        @if ($import->note)
            <p class="whitespace-pre-wrap rounded-xl bg-surface/50 border border-border p-3 text-xs text-main">{{ $import->note }}</p>
        @endif
    </section>

    <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4">SKU snapshot</th>
                        <th class="p-4 text-center">Số lượng</th>
                        <th class="p-4">Giá nhập/đôi</th>
                        <th class="p-4 text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                    @foreach ($import->items as $line)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="p-4 font-mono font-bold text-accent">{{ $line->sku_snapshot }}</td>
                            <td class="p-4 text-center">
                                <span class="bg-surface text-main border border-border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]">{{ $line->quantity }} đôi</span>
                            </td>
                            <td class="p-4 whitespace-nowrap text-main">{{ number_format($line->unit_cost_amount, 0, ',', '.') }} ₫</td>
                            <td class="p-4 whitespace-nowrap text-right font-extrabold text-accent text-xs">{{ number_format($line->subtotal_amount, 0, ',', '.') }} ₫</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-surface/60 border-t border-border flex justify-end text-xs font-bold text-main">
            Tổng cộng: {{ $import->total_quantity }} đôi — <span class="ml-1 text-accent text-sm font-extrabold">{{ number_format($import->total_cost_amount, 0, ',', '.') }} ₫</span>
        </div>
    </div>

    @if ($import->status === App\Enums\StockImportStatus::Draft)
        <section class="admin-card space-y-4">
            <h2 class="text-base font-bold text-main">Xử lý phiếu nháp</h2>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.stock-imports.post', $import) }}">
                    @csrf
                    <button class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-all shadow-md">Ghi sổ nhập kho</button>
                </form>
                <a class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-all" href="{{ route('admin.stock-imports.edit', $import) }}">Sửa nháp</a>
            </div>
            <form class="space-y-3 border-t border-border pt-4" method="POST" action="{{ route('admin.stock-imports.cancel', $import) }}">
                @csrf
                <label for="cancel-reason" class="admin-label text-muted">Lý do hủy</label>
                <div class="flex flex-wrap gap-2">
                    <input id="cancel-reason" class="input w-full max-w-xl border-border bg-surface text-main text-xs" name="reason" required maxlength="500" placeholder="Nhập lý do hủy phiếu...">
                    <button class="px-4 py-2 text-error hover:bg-error-bg rounded-xl font-bold border border-error/30 text-xs transition-colors">Hủy phiếu nháp</button>
                </div>
            </form>
        </section>
    @else
        <p class="rounded-2xl border border-border bg-surface/50 p-4 text-xs text-muted">Phiếu kết thúc, không sửa hoặc xóa. Sai sót phiếu đã ghi sổ phải được xử lý theo quy trình điều chỉnh kho.</p>
    @endif
</div>
@endsection
