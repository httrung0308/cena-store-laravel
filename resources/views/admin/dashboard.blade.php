@extends('layouts.admin')

@section('title', 'Tổng Quan Vận Hành')
@section('eyebrow', 'BÀN LÀM VIỆC HẰNG NGÀY')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.stock-imports.create') }}"
           class="px-4 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors shadow-sm inline-flex items-center gap-1.5">
            <span>+ Tạo phiếu nhập kho</span>
        </a>
        <a href="{{ route('admin.products.create') }}"
           class="px-4 py-2 bg-surface hover:bg-border/40 text-main text-xs font-bold rounded-xl border border-border transition-colors shadow-sm inline-flex items-center gap-1.5">
            <span>+ Thêm sản phẩm</span>
        </a>
        <a href="{{ route('admin.reports.index') }}"
           class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm inline-flex items-center gap-1.5">
            <span>{{ $isAdmin ? 'Báo cáo doanh thu & tồn kho' : 'Báo cáo vận hành & tồn kho' }}</span>
            <span aria-hidden="true">→</span>
        </a>
    </div>
@endsection

@php
    $vnd = static fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
@endphp

@section('content')
    <div class="space-y-6">
        {{-- Operational status bar & snapshot timestamp --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-border bg-surface-card px-4 py-3 text-xs shadow-sm">
            <div class="flex flex-wrap items-center gap-2.5 min-w-0">
                @if ($totalActionableCount > 0)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-warning-bg text-warning-text border border-warning/30 font-bold">
                        <span class="w-2 h-2 rounded-full bg-warning shrink-0" aria-hidden="true"></span>
                        <span>{{ number_format($totalActionableCount) }} đầu việc chờ xử lý</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-success-bg text-success border border-success/30 font-bold">
                        <span class="w-2 h-2 rounded-full bg-success shrink-0" aria-hidden="true"></span>
                        <span>Đã xử lý hết hàng đợi chính</span>
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-3 shrink-0 text-[11px] text-muted">
                <span class="inline-flex items-center">
                    <span>Tại thời điểm tải trang: <strong class="font-mono font-bold text-main">{{ $loadedAt->format('H:i — d/m/Y') }}</strong></span>
                    <x-admin.tooltip
                        placement="bottom"
                        label="Giải thích thời điểm số liệu"
                        text="Số liệu tĩnh tại thời điểm tải trang; bấm Tải lại để cập nhật."
                    />
                </span>
                <a href="{{ route('admin.dashboard') }}"
                   class="px-2.5 py-1 rounded-lg border border-border bg-surface hover:bg-border/40 text-main font-semibold transition-colors whitespace-nowrap shrink-0">
                    Tải lại
                </a>
            </div>
        </div>

        {{-- 1. Primary Actionable Counters Strip (100% matched with filtered list queries) --}}
        <section aria-label="Bộ đếm công việc cần xử lý" @class([
            'grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3',
            'xl:grid-cols-6' => $isAdmin,
            'xl:grid-cols-5' => ! $isAdmin,
        ])>
            {{-- Counter 1: Đơn chờ xác nhận (status = PENDING) --}}
            <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::Pending->value]) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-warning/50 bg-warning-bg/25 hover:border-warning' => $pendingOrdersCount > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $pendingOrdersCount === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Đơn chờ xác nhận</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-warning-text' => $pendingOrdersCount > 0,
                        'text-muted' => $pendingOrdersCount === 0,
                    ])>{{ number_format($pendingOrdersCount) }}</span>
                    <span class="text-[11px] font-semibold text-muted">Đơn mới</span>
                </div>
            </a>

            {{-- Counter 2: Đơn đã xác nhận chờ đóng gói (status = CONFIRMED) --}}
            <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::Confirmed->value]) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-accent/45 bg-accent/5 hover:border-accent' => $confirmedOrdersCount > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $confirmedOrdersCount === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Chờ đóng gói</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-accent' => $confirmedOrdersCount > 0,
                        'text-muted' => $confirmedOrdersCount === 0,
                    ])>{{ number_format($confirmedOrdersCount) }}</span>
                    <span class="text-[11px] font-semibold text-muted">Đã xác nhận</span>
                </div>
            </a>

            {{-- Counter 3: Đơn đang đóng gói / chờ giao (status = PACKING) --}}
            <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::Packing->value]) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-accent/45 bg-accent/5 hover:border-accent' => $packingOrdersCount > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $packingOrdersCount === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Đang đóng gói / Chờ giao</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-accent' => $packingOrdersCount > 0,
                        'text-muted' => $packingOrdersCount === 0,
                    ])>{{ number_format($packingOrdersCount) }}</span>
                    <span class="text-[11px] font-semibold text-muted">Chờ xuất kho</span>
                </div>
            </a>

            {{-- Counter 4: Phiếu nhập kho nháp (status = DRAFT) --}}
            <a href="{{ route('admin.stock-imports.index', ['status' => App\Enums\StockImportStatus::Draft->value]) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-warning/45 bg-warning-bg/20 hover:border-warning' => $draftImportsCount > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $draftImportsCount === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Phiếu nhập kho nháp</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-warning-text' => $draftImportsCount > 0,
                        'text-muted' => $draftImportsCount === 0,
                    ])>{{ number_format($draftImportsCount) }}</span>
                    <span class="text-[11px] font-semibold text-muted">Chưa ghi sổ</span>
                </div>
            </a>

            {{-- Counter 5: Sản phẩm hết hàng (stock = out_of_stock) --}}
            <a href="{{ route('admin.products.index', ['stock' => 'out_of_stock']) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-error/45 bg-error-bg/25 hover:border-error' => $outOfStockProductsCount > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $outOfStockProductsCount === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Sản phẩm hết hàng</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-error' => $outOfStockProductsCount > 0,
                        'text-muted' => $outOfStockProductsCount === 0,
                    ])>{{ number_format($outOfStockProductsCount) }}</span>
                    <span class="text-[11px] font-semibold text-muted">{{ number_format($lowStockVariantsCount) }} size ≤ {{ $lowStockThreshold }} đôi</span>
                </div>
            </a>

            {{-- Counter 6: Admin-only -> Đơn chờ hoàn tiền (payment_status = REFUND_PENDING) --}}
            @if ($isAdmin)
                <a href="{{ route('admin.orders.index', ['payment_status' => App\Enums\OrderPaymentStatus::RefundPending->value]) }}"
                   @class([
                       'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                       'border-error/50 bg-error-bg/30 hover:border-error' => $pendingRefundOrdersCount > 0,
                       'border-border bg-surface-card hover:border-accent/60' => $pendingRefundOrdersCount === 0,
                   ])>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Đơn chờ hoàn tiền</span>
                        <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between gap-2">
                        <span @class([
                            'text-2xl font-extrabold tabular-nums font-mono',
                            'text-error' => $pendingRefundOrdersCount > 0,
                            'text-muted' => $pendingRefundOrdersCount === 0,
                        ])>{{ number_format($pendingRefundOrdersCount) }}</span>
                        <span class="text-[11px] font-semibold text-muted">{{ $requestedRefundsCount }} yêu cầu</span>
                    </div>
                </a>
            @endif
        </section>

        {{-- 2. Admin-only: Pending Refund Approvals + Clear Bridge to Financial Reports --}}
        @if ($isAdmin)
            <div class="grid gap-4 lg:grid-cols-12">
                <section aria-labelledby="admin-refund-heading" class="lg:col-span-7 rounded-2xl border border-border bg-surface-card p-5 shadow-sm flex flex-col justify-between gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 id="admin-refund-heading" class="text-sm font-extrabold text-main">Yêu cầu hoàn tiền chờ Admin duyệt</h2>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-[11px] font-bold font-mono',
                                'bg-error-bg text-error border border-error/30' => $requestedRefundsCount > 0,
                                'bg-surface text-muted border border-border' => $requestedRefundsCount === 0,
                            ])>{{ $requestedRefundsCount }}</span>
                            <x-admin.tooltip
                                label="Quy tắc duyệt hoàn tiền"
                                text="Phát sinh khi hủy đơn trả trước; chỉ Admin mới có quyền phê duyệt hoàn tiền."
                            />
                        </div>
                        <a href="{{ route('admin.orders.index', ['payment_status' => App\Enums\OrderPaymentStatus::RefundPending->value]) }}"
                           class="text-xs font-bold text-accent hover:underline whitespace-nowrap">
                            Lọc đơn chờ hoàn tiền ({{ $pendingRefundOrdersCount }}) →
                        </a>
                    </div>

                    @if ($pendingRefunds->isNotEmpty())
                        <div class="divide-y divide-border rounded-xl border border-border overflow-hidden">
                            @foreach ($pendingRefunds as $refund)
                                <div class="flex flex-wrap items-center justify-between gap-3 bg-surface/40 px-3.5 py-2.5 text-xs">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.orders.show', $refund->order_id) }}" class="font-mono font-bold text-accent hover:underline">
                                                {{ $refund->order?->order_code ?? '#'.$refund->order_id }}
                                            </a>
                                            <span class="text-muted">·</span>
                                            <span class="font-semibold text-main truncate">{{ $refund->order?->shipping_name }}</span>
                                        </div>
                                        <p class="text-[11px] text-muted mt-0.5">
                                            Yêu cầu lúc {{ $refund->created_at?->setTimezone($timezone)->format('d/m/Y H:i') }}
                                            @if ($refund->requestedBy)
                                                · bởi {{ $refund->requestedBy->full_name }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <span class="font-mono font-extrabold text-error">{{ $vnd($refund->amount) }}</span>
                                        <a href="{{ route('admin.orders.show', $refund->order_id) }}"
                                           class="px-3 py-1.5 rounded-lg bg-warning-bg text-warning-text border border-warning/40 hover:bg-warning/20 font-bold text-xs transition-colors">
                                            Mở đơn duyệt →
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-border bg-surface/40 px-4 py-3 text-xs text-muted flex items-center justify-between gap-3">
                            <span>Không có yêu cầu hoàn tiền chờ duyệt.</span>
                            <span class="badge bg-success-bg text-success border border-success/30 shrink-0">Đã xử lý hết</span>
                        </div>
                    @endif
                </section>

                <section aria-labelledby="admin-reports-bridge-heading" class="lg:col-span-5 rounded-2xl border border-accent/30 bg-surface-card p-5 shadow-sm flex flex-col justify-between gap-4">
                    <div>
                        <h2 id="admin-reports-bridge-heading" class="text-sm font-extrabold text-main">Doanh thu, Giá vốn &amp; Lợi nhuận</h2>
                        <p class="text-xs text-muted mt-1 leading-relaxed">
                            Doanh thu ròng, giá vốn (COGS), chi phí và tỷ suất lợi nhuận theo kỳ.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-border/60">
                        <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-muted">
                            @if ($onDeliveryOrdersCount > 0)
                                <span>Đang vận chuyển: <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::OnDelivery->value]) }}" class="font-bold text-main hover:text-accent underline">{{ $onDeliveryOrdersCount }} đơn</a></span>
                            @else
                                <span>Sổ cái tài chính &amp; biểu đồ theo kỳ</span>
                            @endif
                        </div>
                        <a href="{{ route('admin.reports.index') }}"
                           class="px-3.5 py-2 rounded-xl bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold transition-colors shadow-sm inline-flex items-center gap-1.5">
                            <span>Mở trang Báo cáo</span>
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </section>
            </div>
        @endif

        {{-- 3. Direct Operational Queues (Responsive 12-col layout: Orders & Draft Imports on left, Low-Stock SKUs on right) --}}
        <div class="grid gap-6 lg:grid-cols-12 items-start">
            {{-- Left Column (7 cols on lg): Priority Orders Queue + Draft Stock Imports --}}
            <div class="lg:col-span-7 space-y-6 min-w-0">
                {{-- Priority Orders Queue --}}
                <section aria-labelledby="priority-orders-heading" class="rounded-2xl border border-border bg-surface-card shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <h2 id="priority-orders-heading" class="text-base font-extrabold text-main">Đơn hàng cần xử lý ưu tiên</h2>
                        <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-accent hover:underline shrink-0">
                            Tất cả đơn hàng →
                        </a>
                    </div>

                    {{-- Quick Filter Chips to Filtered Order Lists --}}
                    <div class="px-5 py-2.5 bg-surface/60 border-b border-border flex flex-wrap items-center gap-2 text-xs">
                        <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::Pending->value]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-surface-card hover:border-accent text-main font-semibold transition-colors">
                            <span>Chờ xác nhận</span>
                            <span class="font-mono font-bold text-warning-text">({{ $pendingOrdersCount }})</span>
                        </a>
                        <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::Confirmed->value]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-surface-card hover:border-accent text-main font-semibold transition-colors">
                            <span>Đã xác nhận</span>
                            <span class="font-mono font-bold text-accent">({{ $confirmedOrdersCount }})</span>
                        </a>
                        <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::Packing->value]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-surface-card hover:border-accent text-main font-semibold transition-colors">
                            <span>Đang đóng gói</span>
                            <span class="font-mono font-bold text-accent">({{ $packingOrdersCount }})</span>
                        </a>
                        @if ($onDeliveryOrdersCount > 0)
                            <a href="{{ route('admin.orders.index', ['status' => App\Enums\OrderStatus::OnDelivery->value]) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-surface-card hover:border-accent text-muted hover:text-main font-semibold transition-colors">
                                <span>Đang vận chuyển</span>
                                <span class="font-mono font-bold">({{ $onDeliveryOrdersCount }})</span>
                            </a>
                        @endif
                    </div>

                    @if ($actionableOrders->isNotEmpty())
                        {{-- Mobile cards (< md) --}}
                        <div class="divide-y divide-border md:hidden">
                            @foreach ($actionableOrders as $order)
                                @php
                                    $firstItem = $order->items->first();
                                    $totalPairs = (int) $order->items->sum('quantity');
                                    $extraItems = max(0, $order->items->count() - 1);
                                    $nextActionLabel = match ($order->order_status) {
                                        App\Enums\OrderStatus::Pending => 'Xác nhận đơn',
                                        App\Enums\OrderStatus::Confirmed => 'Đóng gói',
                                        App\Enums\OrderStatus::Packing => 'Giao vận chuyển',
                                        default => 'Mở đơn',
                                    };
                                @endphp
                                <article class="p-4 space-y-2.5 text-xs">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-bold text-accent hover:underline text-sm">
                                                {{ $order->order_code }}
                                            </a>
                                            <p class="text-[11px] text-muted mt-0.5">Đặt lúc {{ $order->created_at->setTimezone($timezone)->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <x-orders.status-badge :status="$order->order_status" />
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <p class="font-bold text-main">{{ $order->shipping_name }}</p>
                                            <p class="text-[11px] text-muted">{{ $order->shipping_phone }}</p>
                                        </div>
                                        <span class="font-mono font-extrabold text-main text-sm">{{ $vnd($order->total_amount) }}</span>
                                    </div>
                                    @if ($firstItem)
                                        <p class="text-[11px] text-muted truncate">
                                            {{ $firstItem->product_name_snapshot }}@if ($extraItems > 0) (+{{ $extraItems }} SP khác)@endif · <strong class="text-main">{{ $totalPairs }} đôi</strong>
                                        </p>
                                    @endif
                                    <div class="pt-1 flex justify-end">
                                        <a href="{{ route('admin.orders.show', $order) }}"
                                           class="px-3 py-1.5 rounded-lg bg-accent hover:bg-accent-hover text-accent-text font-bold text-xs transition-colors">
                                            {{ $nextActionLabel }} →
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        {{-- Desktop table (>= md) --}}
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full text-left text-xs text-muted border-collapse">
                                <thead class="bg-surface text-main font-bold border-b border-border">
                                    <tr>
                                        <th class="py-3 px-4">Mã đơn &amp; Thời gian</th>
                                        <th class="py-3 px-4">Khách nhận</th>
                                        <th class="py-3 px-4">Sản phẩm</th>
                                        <th class="py-3 px-4 text-right">Tổng tiền</th>
                                        <th class="py-3 px-4 text-center">Trạng thái</th>
                                        <th class="py-3 px-4 text-right">Xử lý</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border font-medium">
                                    @foreach ($actionableOrders as $order)
                                        @php
                                            $firstItem = $order->items->first();
                                            $totalPairs = (int) $order->items->sum('quantity');
                                            $extraItems = max(0, $order->items->count() - 1);
                                            $nextActionLabel = match ($order->order_status) {
                                                App\Enums\OrderStatus::Pending => 'Xác nhận đơn',
                                                App\Enums\OrderStatus::Confirmed => 'Đóng gói',
                                                App\Enums\OrderStatus::Packing => 'Giao vận chuyển',
                                                default => 'Chi tiết',
                                            };
                                        @endphp
                                        <tr class="hover:bg-surface/50 transition-colors">
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-bold text-accent hover:underline block">
                                                    {{ $order->order_code }}
                                                </a>
                                                <span class="text-[11px] text-muted">{{ $order->created_at->setTimezone($timezone)->format('d/m H:i') }}</span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="font-bold text-main block truncate max-w-[140px]">{{ $order->shipping_name }}</span>
                                                <span class="text-[11px] text-muted font-mono">{{ $order->shipping_phone }}</span>
                                            </td>
                                            <td class="py-3 px-4 max-w-[190px]">
                                                @if ($firstItem)
                                                    <span class="block truncate font-semibold text-main" title="{{ $firstItem->product_name_snapshot }}">
                                                        {{ $firstItem->product_name_snapshot }}
                                                    </span>
                                                @endif
                                                <span class="text-[11px] text-muted">
                                                    <strong class="font-mono text-main">{{ $totalPairs }} đôi</strong>@if ($extraItems > 0) · +{{ $extraItems }} SP @endif
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right whitespace-nowrap font-mono font-extrabold text-main">
                                                {{ $vnd($order->total_amount) }}
                                            </td>
                                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                                <x-orders.status-badge :status="$order->order_status" />
                                            </td>
                                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                                <a href="{{ route('admin.orders.show', $order) }}"
                                                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors">
                                                    <span>{{ $nextActionLabel }}</span>
                                                    <span aria-hidden="true">→</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6 text-center space-y-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-success-bg text-success border border-success/30 font-bold text-sm" aria-hidden="true">✓</span>
                            <p class="text-xs font-bold text-main">Không có đơn hàng chờ xác nhận hoặc đóng gói.</p>
                        </div>
                    @endif
                </section>

                {{-- Draft Stock Imports Queue --}}
                <section aria-labelledby="draft-imports-heading" class="rounded-2xl border border-border bg-surface-card shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-border flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 id="draft-imports-heading" class="text-base font-extrabold text-main">Phiếu nhập kho nháp</h2>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold font-mono bg-surface border border-border text-main">
                                {{ $draftImportsCount }}
                            </span>
                        </div>
                        <a href="{{ route('admin.stock-imports.index', ['status' => App\Enums\StockImportStatus::Draft->value]) }}"
                           class="text-xs font-bold text-accent hover:underline">
                            Danh sách phiếu nháp ({{ $draftImportsCount }}) →
                        </a>
                    </div>

                    @if ($draftImports->isNotEmpty())
                        <div class="divide-y divide-border">
                            @foreach ($draftImports as $import)
                                <div class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 text-xs hover:bg-surface/40 transition-colors">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.stock-imports.show', $import) }}" class="font-mono font-bold text-accent hover:underline">
                                                {{ $import->import_code }}
                                            </a>
                                            <span class="text-muted">·</span>
                                            <span class="font-bold text-main truncate">{{ $import->supplier_name_snapshot }}</span>
                                        </div>
                                        <p class="text-[11px] text-muted mt-0.5">
                                            Cập nhật {{ $import->updated_at?->setTimezone($timezone)->format('d/m/Y H:i') }}
                                            · Tổng <strong class="font-mono text-main">{{ number_format($import->total_quantity) }} đôi</strong>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <a href="{{ route('admin.stock-imports.edit', $import) }}"
                                           class="px-2.5 py-1.5 rounded-lg border border-border bg-surface hover:bg-border/40 text-main font-semibold transition-colors">
                                            Sửa
                                        </a>
                                        <a href="{{ route('admin.stock-imports.show', $import) }}"
                                           class="px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold transition-colors">
                                            Kiểm tra &amp; ghi sổ →
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 text-xs text-muted bg-surface/30">
                            <span>Không có phiếu nhập kho nháp.</span>
                            <a href="{{ route('admin.stock-imports.create') }}" class="font-bold text-accent hover:underline">+ Tạo phiếu nhập mới →</a>
                        </div>
                    @endif
                </section>
            </div>

            {{-- Right Column (5 cols on lg): Low/Out-of-Stock Variants --}}
            <div class="lg:col-span-5 space-y-6 min-w-0">
                {{-- Low / Depleted Stock Variants Queue --}}
                <section aria-labelledby="low-stock-heading" class="rounded-2xl border border-border bg-surface-card shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-border flex flex-wrap items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 id="low-stock-heading" class="text-base font-extrabold text-main">Biến thể cần nhập kho</h2>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-[11px] font-bold font-mono',
                                'bg-error-bg text-error border border-error/30' => $depletedVariantsCount > 0,
                                'bg-warning-bg text-warning-text border border-warning/30' => $depletedVariantsCount === 0 && $lowStockVariantsCount > 0,
                                'bg-surface text-muted border border-border' => $lowStockVariantsCount === 0,
                            ])>{{ $lowStockVariantsCount }} size</span>
                            <x-admin.tooltip
                                label="Quy tắc biến thể sắp hết"
                                :text="'Các size đang mở bán có tồn khả dụng ≤ ' . $lowStockThreshold . ' đôi (ưu tiên hết hàng lên trước).'"
                            />
                        </div>
                        <a href="{{ route('admin.products.index', ['stock' => 'out_of_stock']) }}"
                           class="text-xs font-bold text-accent hover:underline whitespace-nowrap">
                            SP hết hàng ({{ $outOfStockProductsCount }}) →
                        </a>
                    </div>

                    @if ($lowStockVariants !== [])
                        <div class="divide-y divide-border">
                            @foreach ($lowStockVariants as $variant)
                                <div class="px-5 py-3 flex items-center justify-between gap-3 text-xs hover:bg-surface/40 transition-colors">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('admin.products.show', $variant['product_id']) }}"
                                           class="font-bold text-main hover:text-accent truncate block">
                                            {{ $variant['product'] }}
                                        </a>
                                        <p class="text-[11px] text-muted mt-0.5 truncate">
                                            <span class="font-mono">{{ $variant['sku'] }}</span> · {{ $variant['color'] }} · Size <strong class="text-main">{{ $variant['size'] }}</strong>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2.5 shrink-0">
                                        <span @class([
                                            'badge font-mono',
                                            'bg-error-bg text-error border border-error/30' => $variant['sellable'] <= 0,
                                            'bg-warning-bg text-warning-text border border-warning/30' => $variant['sellable'] > 0,
                                        ])>
                                            {{ $variant['sellable'] <= 0 ? 'Hết hàng' : 'Còn '.$variant['sellable'].' đôi' }}
                                        </span>
                                        <a href="{{ route('admin.stock-imports.create', ['variant_id' => $variant['variant_id']]) }}"
                                           class="px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors whitespace-nowrap">
                                            + Nhập kho
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($lowStockVariantsCount > count($lowStockVariants))
                            <div class="px-5 py-3 bg-surface/50 border-t border-border flex items-center justify-between text-xs">
                                <span class="text-muted">Còn {{ number_format($lowStockVariantsCount - count($lowStockVariants)) }} size sắp hết khác</span>
                                <a href="{{ route('admin.reports.index') }}" class="font-bold text-accent hover:underline">
                                    Xem toàn bộ danh sách size sắp hết →
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="p-6 text-center">
                            <p class="text-xs font-bold text-main">Tồn kho các size đang mở bán ổn định (trên {{ $lowStockThreshold }} đôi).</p>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection
