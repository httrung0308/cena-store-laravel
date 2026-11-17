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
            <span>Mở Báo cáo</span>
            <span aria-hidden="true">→</span>
        </a>
    </div>
@endsection

@php
    $vnd = static fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $inStockLowVariantsCount = max(0, $lowStockVariantsCount - $depletedVariantsCount);
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

        {{-- 1. One Row of 4 Main KPIs --}}
        <section aria-label="Chỉ số tổng quan chính" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {{-- KPI 1: Doanh thu kỳ hiện tại --}}
            <a href="{{ route('admin.reports.index') }}"
               class="group rounded-2xl border border-border bg-surface-card hover:border-accent/60 p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Doanh thu kỳ hiện tại</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3">
                    <span class="block text-2xl font-extrabold tabular-nums font-mono text-main">{{ $vnd($currentMonthRevenue) }}</span>
                    <span class="mt-1 block text-[11px] font-semibold text-muted">
                        Kỳ tháng {{ $loadedAt->format('m/Y') }} (đến {{ $loadedAt->format('H:i') }})
                    </span>
                </div>
            </a>

            {{-- KPI 2: Đơn mới --}}
            <a href="{{ route('admin.operations-work-items.index', ['type' => \App\Services\OperationsQueueService::TYPE_ORDER_PENDING]) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-warning/50 bg-warning-bg/25 hover:border-warning' => $queueCounts['order_pending'] > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $queueCounts['order_pending'] === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Đơn mới</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-warning-text' => $queueCounts['order_pending'] > 0,
                        'text-muted' => $queueCounts['order_pending'] === 0,
                    ])>{{ number_format($queueCounts['order_pending']) }}</span>
                    <span class="text-[11px] font-semibold text-muted">Chờ xác nhận</span>
                </div>
            </a>

            {{-- KPI 3: Việc tồn đọng --}}
            <a href="{{ route('admin.operations-work-items.index') }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-accent/45 bg-accent/5 hover:border-accent' => $queueCounts['total'] > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $queueCounts['total'] === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Việc tồn đọng</span>
                    <span class="text-xs font-bold text-muted group-hover:text-accent transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <span @class([
                        'text-2xl font-extrabold tabular-nums font-mono',
                        'text-accent' => $queueCounts['total'] > 0,
                        'text-muted' => $queueCounts['total'] === 0,
                    ])>{{ number_format($queueCounts['total']) }}</span>
                    <span class="text-[11px] font-semibold text-muted">Trung tâm công việc</span>
                </div>
            </a>

            {{-- KPI 4: Tồn kho cần chú ý --}}
            <a href="{{ route('admin.products.index', ['stock' => 'out_of_stock']) }}"
               @class([
                   'group rounded-2xl border p-4 transition-all flex flex-col justify-between shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                   'border-error/45 bg-error-bg/25 hover:border-error' => $outOfStockProductsCount > 0 || $lowStockVariantsCount > 0,
                   'border-border bg-surface-card hover:border-accent/60' => $outOfStockProductsCount === 0 && $lowStockVariantsCount === 0,
               ])>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-bold text-main group-hover:text-accent transition-colors">Tồn kho cần chú ý</span>
                    <span class="text-[10px] font-bold uppercase tracking-wide text-muted">Snapshot hiện tại</span>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-1.5">
                        <span @class([
                            'text-2xl font-extrabold tabular-nums font-mono',
                            'text-error' => $outOfStockProductsCount > 0,
                            'text-muted' => $outOfStockProductsCount === 0,
                        ])>{{ number_format($outOfStockProductsCount) }}</span>
                        <span class="text-xs font-bold text-main">SP hết hàng</span>
                    </div>
                    <span class="mt-1 block text-[11px] font-semibold text-muted">
                        {{ number_format($lowStockVariantsCount) }} biến thể ≤ {{ $lowStockThreshold }} đôi
                    </span>
                </div>
            </a>
        </section>

        {{-- 2. Conditional "Chờ bạn duyệt" Block (ONLY when refund approvals or write-off proposals > 0) --}}
        @if ($requestedRefundsCount > 0 || $writeOffApprovalCount > 0)
            <section aria-labelledby="admin-approval-heading" class="rounded-2xl border border-error/40 bg-surface-card p-5 shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <h2 id="admin-approval-heading" class="text-sm font-extrabold text-main">Chờ bạn duyệt</h2>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold font-mono bg-error-bg text-error border border-error/30">
                            {{ number_format($requestedRefundsCount + $writeOffApprovalCount) }}
                        </span>
                        <x-admin.tooltip
                            label="Quy tắc phê duyệt Admin"
                            text="Bao gồm yêu cầu hoàn tiền và đề xuất ghi hỏng hàng hoàn; chỉ Admin mới có quyền phê duyệt."
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs font-bold">
                        @if ($requestedRefundsCount > 0)
                            <a href="{{ route('admin.operations-work-items.index', ['type' => \App\Services\OperationsQueueService::TYPE_REFUND_APPROVAL]) }}"
                               class="text-accent hover:underline">
                                Hoàn tiền ({{ number_format($requestedRefundsCount) }}) →
                            </a>
                        @endif
                        @if ($writeOffApprovalCount > 0)
                            <a href="{{ route('admin.operations-work-items.index', ['type' => \App\Services\OperationsQueueService::TYPE_WRITE_OFF_APPROVAL]) }}"
                               class="text-accent hover:underline">
                                Ghi hỏng ({{ number_format($writeOffApprovalCount) }}) →
                            </a>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-border rounded-xl border border-border overflow-hidden">
                    @foreach ($pendingRefunds as $refund)
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-surface/40 px-3.5 py-2.5 text-xs">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border border-error/30 bg-error-bg px-2 py-0.5 text-[10px] font-bold text-error">
                                        Hoàn tiền
                                    </span>
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

                    @foreach ($pendingWriteOffs as $writeOff)
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-surface/40 px-3.5 py-2.5 text-xs">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border border-error/30 bg-error-bg px-2 py-0.5 text-[10px] font-bold text-error">
                                        Ghi hỏng
                                    </span>
                                    <span class="font-mono font-bold text-main">{{ $writeOff['reference'] }}</span>
                                    <span class="text-muted">·</span>
                                    <span class="text-muted truncate">{{ $writeOff['related_info'] }}</span>
                                </div>
                                <p class="text-[11px] text-muted mt-0.5">
                                    Đề xuất lúc {{ $writeOff['occurred_at']->timezone($timezone)->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <a href="{{ $writeOff['action_url'] }}"
                               class="px-3 py-1.5 rounded-lg bg-warning-bg text-warning-text border border-warning/40 hover:bg-warning/20 font-bold text-xs transition-colors shrink-0">
                                Ghi hỏng →
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 3. Two-Column Operational Body: Top 6 Work Items + Hàng cần nhập --}}
        <div class="grid gap-6 lg:grid-cols-12 items-start">
            {{-- Left Column (7 cols on lg): Việc tồn đọng cần chú ý --}}
            <section aria-labelledby="top-work-items-heading" class="lg:col-span-7 rounded-2xl border border-border bg-surface-card shadow-sm overflow-hidden min-w-0">
                <div class="p-5 border-b border-border flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2 id="top-work-items-heading" class="text-base font-extrabold text-main">Việc tồn đọng cần chú ý</h2>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold font-mono bg-surface border border-border text-main">
                            {{ number_format($queueCounts['total']) }}
                        </span>
                        <x-admin.tooltip
                            label="Thứ tự ưu tiên"
                            text="Hiển thị tối đa 6 đầu việc ưu tiên cao nhất và chờ lâu nhất từ Trung tâm công việc."
                        />
                    </div>
                    <a href="{{ route('admin.operations-work-items.index') }}" class="text-xs font-bold text-accent hover:underline shrink-0">
                        Xem tất cả →
                    </a>
                </div>

                @if ($draftImportsCount > 0)
                    <div class="px-5 py-2.5 bg-warning-bg/20 border-b border-border flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="text-main font-semibold">
                            Có <strong class="font-mono text-warning-text">{{ number_format($draftImportsCount) }}</strong> phiếu nhập kho nháp chưa ghi sổ.
                        </span>
                        <a href="{{ route('admin.stock-imports.index', ['status' => \App\Enums\StockImportStatus::Draft->value]) }}"
                           class="font-bold text-accent hover:underline whitespace-nowrap">
                            Phiếu nhập nháp ({{ number_format($draftImportsCount) }}) →
                        </a>
                    </div>
                @endif

                @if ($topWorkItems !== [])
                    <div class="divide-y divide-border">
                        @foreach ($topWorkItems as $item)
                            @php
                                $occurredAtLocal = $item['occurred_at']->timezone($timezone);
                                $fullTimestamp = $occurredAtLocal->format('d/m/Y H:i:s');
                            @endphp
                            <div class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs hover:bg-surface/40 transition-colors">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $item['type_badge_class'] }}">
                                            {{ $item['type_label'] }}
                                        </span>
                                        <a href="{{ $item['action_url'] }}" class="font-bold text-accent hover:underline">
                                            {{ $item['action_title'] ?? $item['title'] }} →
                                        </a>
                                        <span class="font-mono text-[11px] font-semibold text-muted">{{ $item['reference'] }}</span>
                                    </div>
                                    <p class="text-[11px] text-muted truncate">{{ $item['related_info'] ?? $item['description'] }}</p>
                                </div>
                                <div class="shrink-0 text-[11px] text-muted tabular-nums sm:text-right" title="{{ $fullTimestamp }}">
                                    {{ $occurredAtLocal->locale('vi')->diffForHumans() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center space-y-1.5">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-success-bg text-success border border-success/30 font-bold text-sm" aria-hidden="true">✓</span>
                        <p class="text-xs font-bold text-main">Không có việc tồn đọng cần xử lý.</p>
                    </div>
                @endif
            </section>

            {{-- Right Column (5 cols on lg): Hàng cần nhập --}}
            <section aria-labelledby="low-stock-heading" class="lg:col-span-5 rounded-2xl border border-border bg-surface-card shadow-sm overflow-hidden min-w-0">
                <div class="p-5 border-b border-border flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 id="low-stock-heading" class="text-base font-extrabold text-main">Hàng cần nhập</h2>
                            <x-admin.tooltip
                                label="Quy tắc hàng cần nhập"
                                :text="'Phân biệt biến thể hết hàng (0 đôi) và sắp hết (1–' . $lowStockThreshold . ' đôi) đang mở bán theo tồn khả dụng.'"
                            />
                        </div>
                        <p class="mt-1 text-[11px] text-muted">
                            <strong class="font-mono text-error">{{ number_format($outOfStockProductsCount) }}</strong> sản phẩm hết hàng ·
                            <strong class="font-mono text-error">{{ number_format($depletedVariantsCount) }}</strong> biến thể hết hàng (0 đôi) ·
                            <strong class="font-mono text-warning-text">{{ number_format($inStockLowVariantsCount) }}</strong> biến thể sắp hết (1–{{ $lowStockThreshold }} đôi)
                        </p>
                    </div>
                    <a href="{{ route('admin.products.index', ['stock' => 'out_of_stock']) }}"
                       class="text-xs font-bold text-accent hover:underline whitespace-nowrap">
                        SP hết hàng ({{ number_format($outOfStockProductsCount) }}) →
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
                                        {{ $variant['sellable'] <= 0 ? 'Hết hàng (0 đôi)' : 'Sắp hết ('.$variant['sellable'].' đôi)' }}
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
                            <span class="text-muted">Còn {{ number_format($lowStockVariantsCount - count($lowStockVariants)) }} biến thể cần nhập khác</span>
                            <a href="{{ route('admin.reports.index', ['tab' => 'inventory']) }}" class="font-bold text-accent hover:underline">
                                Xem báo cáo tồn kho →
                            </a>
                        </div>
                    @endif
                @else
                    <div class="p-6 text-center">
                        <p class="text-xs font-bold text-main">Tồn kho các biến thể đang mở bán ổn định (trên {{ $lowStockThreshold }} đôi).</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
