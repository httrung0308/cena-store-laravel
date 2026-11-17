@extends('layouts.admin')
@section('title', 'Quản Lý Đơn Hàng')
@section('eyebrow', 'QUẢN TRỊ BÁN HÀNG')
@php
    $tz = (string) config('store.business_timezone');
    $returnProgressOptions = [
        'awaiting_receive' => 'Chờ nhận',
        'partially_received' => 'Đã nhận một phần',
        'awaiting_inspection' => 'Chờ kiểm tra',
        'awaiting_write_off_approval' => 'Hàng hỏng cần xử lý',
        'completed' => 'Đã xử lý',
    ];
    $resolveReturnBadge = static function ($order): ?array {
        if ($order->order_status !== App\Enums\OrderStatus::Failed || ! $order->shipment) {
            return null;
        }
        $shipment = $order->shipment;
        $totalUnits = max(1, (int) $order->items->sum('quantity'));
        $returnItems = $shipment->shipmentReturnItems;
        $receivedCount = $returnItems->count();
        if ($receivedCount === 0) {
            if ($shipment->status === App\Enums\ShipmentStatus::Lost) {
                return ['label' => 'Thất lạc', 'class' => 'bg-error-bg text-error-text border border-error/30'];
            }

            return ['label' => 'Chờ nhận', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
        }
        if ($receivedCount < $totalUnits && $shipment->status === App\Enums\ShipmentStatus::Returning) {
            return ['label' => 'Đã nhận một phần', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
        }
        $pendingItems = $returnItems->where('disposition', App\Enums\ReturnDisposition::Pending);
        if ($pendingItems->isNotEmpty()) {
            $hasWriteOffProposal = $shipment->returnEvents
                ->where('event_type', App\Models\ShipmentReturnEvent::WRITE_OFF_PROPOSED)
                ->whereIn('shipment_return_item_id', $pendingItems->pluck('id'))
                ->isNotEmpty();

            return $hasWriteOffProposal
                ? ['label' => 'Hàng hỏng cần xử lý', 'class' => 'bg-error-bg text-error-text border border-error/30']
                : ['label' => 'Chờ kiểm tra', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
        }

        return ['label' => 'Đã xử lý', 'class' => 'bg-success-bg text-success-text border border-success/30'];
    };
    $activeChips = [];
    if (filled($filters['search'] ?? null)) {
        $activeChips[] = [
            'label' => 'Từ khóa: "'.$filters['search'].'"',
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($filters['status'] ?? null)) {
        $activeChips[] = [
            'label' => 'Trạng thái: '.App\Enums\OrderStatus::from($filters['status'])->label(),
            'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null]),
        ];
    }
    if (filled($filters['payment_status'] ?? null)) {
        $activeChips[] = [
            'label' => 'Thanh toán: '.App\Enums\OrderPaymentStatus::from($filters['payment_status'])->label(),
            'url' => request()->fullUrlWithQuery(['payment_status' => null, 'page' => null]),
        ];
    }
    if (filled($filters['return_progress'] ?? null)) {
        $activeChips[] = [
            'label' => 'Hàng hoàn: '.($returnProgressOptions[$filters['return_progress']] ?? $filters['return_progress']),
            'url' => request()->fullUrlWithQuery(['return_progress' => null, 'page' => null]),
        ];
    }
@endphp
@section('content')
    <div x-data="adminAjaxList()" class="space-y-6">
        <x-admin.search-form
            :action="route('admin.orders.index')"
            id="order-search"
            label="Tìm đơn hàng"
            placeholder="Tìm kiếm theo mã đơn, người nhận, số điện thoại..."
            :value="$filters['search'] ?? ''"
            :ajax="true"
            :active-chips="$activeChips"
        >
            <x-slot:filters>
                <x-admin.filter-select id="order-status-filter" name="status" label="Trạng thái:" :active="filled($filters['status'] ?? null)">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\OrderStatus::cases() as $state)
                        <option value="{{ $state->value }}" @selected(($filters['status'] ?? '') === $state->value)>{{ $state->label() }}</option>
                    @endforeach
                </x-admin.filter-select>

                <x-admin.filter-select id="order-payment-status-filter" name="payment_status" label="Thanh toán:" :active="filled($filters['payment_status'] ?? null)">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\OrderPaymentStatus::cases() as $paymentState)
                        <option value="{{ $paymentState->value }}" @selected(($filters['payment_status'] ?? '') === $paymentState->value)>{{ $paymentState->label() }}</option>
                    @endforeach
                </x-admin.filter-select>

                <x-admin.filter-select id="order-return-progress-filter" name="return_progress" label="Hàng hoàn:" :active="filled($filters['return_progress'] ?? null)">
                    <option value="">Tất cả</option>
                    @foreach ($returnProgressOptions as $progressKey => $progressLabel)
                        <option value="{{ $progressKey }}" @selected(($filters['return_progress'] ?? '') === $progressKey)>{{ $progressLabel }}</option>
                    @endforeach
                </x-admin.filter-select>
            </x-slot:filters>
        </x-admin.search-form>

        <div id="admin-list-region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none transition-opacity': listLoading }" class="space-y-4">
            <div class="flex items-center justify-between px-1 text-xs text-muted">
                <span>Tổng cộng <strong class="font-bold text-main">{{ number_format($orders->total()) }}</strong> đơn hàng</span>
                @if ($orders->hasPages())
                    <span>Trang {{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
                @endif
            </div>

            <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
                {{-- Mobile summary cards --}}
                <div class="divide-y divide-border md:hidden">
                    @forelse ($orders as $order)
                        @php
                            $firstItem = $order->items->first();
                            $totalPairs = $order->items->sum('quantity');
                            $extraItems = max(0, $order->items->count() - 1);
                            $itemsSummary = $order->items
                                ->map(fn ($item) => $item->product_name_snapshot.' ('.$item->quantity.' đôi)')
                                ->implode(', ');
                            $returnBadge = $resolveReturnBadge($order);
                        @endphp
                        <article class="p-4 space-y-2.5 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('admin.orders.show', $order) }}"
                                   class="inline-flex items-center gap-1 font-mono font-bold text-accent underline decoration-accent/40 underline-offset-2 hover:decoration-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded break-all">
                                    <span>{{ $order->order_code }}</span>
                                    <span aria-hidden="true">→</span>
                                </a>
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <x-orders.status-badge :status="$order->order_status" />
                                    @if ($returnBadge)
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $returnBadge['class'] }}">
                                            {{ $returnBadge['label'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <p class="font-bold text-main break-words">{{ $order->shipping_name }}</p>
                                <p class="text-[11px] text-muted font-mono">{{ $order->shipping_phone }}</p>
                            </div>
                            @if ($firstItem)
                                <div class="flex items-center gap-1 text-[11px] text-muted min-w-0">
                                    <span class="truncate">
                                        {{ $firstItem->product_name_snapshot }}@if ($extraItems > 0) (+{{ $extraItems }} SP khác)@endif · <span class="font-semibold text-main">{{ $totalPairs }} đôi</span>
                                    </span>
                                    @if ($extraItems > 0 || mb_strlen($firstItem->product_name_snapshot) > 28)
                                        <x-admin.tooltip :text="$itemsSummary" label="Xem đầy đủ sản phẩm" />
                                    @endif
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-accent text-sm tabular-nums">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</span>
                                    <span @class(['badge', 'bg-success-bg text-success border border-success/30' => $order->payment_status === App\Enums\OrderPaymentStatus::Paid, 'bg-muted/15 text-muted border border-border/50' => $order->payment_status !== App\Enums\OrderPaymentStatus::Paid])>{{ $order->payment_status->label() }}</span>
                                </div>
                                <span class="text-[11px] text-muted tabular-nums">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="p-10 text-center text-xs text-muted">Chưa có đơn hàng.</div>
                    @endforelse
                </div>

                {{-- Desktop table --}}
                <div class="hidden md:block">
                    <table class="w-full table-fixed text-left text-xs text-muted border-collapse">
                        <thead class="bg-surface text-main font-bold border-b border-border">
                            <tr>
                                <th class="w-[27%] px-3.5 py-3">Đơn hàng</th>
                                <th class="w-[20%] px-3.5 py-3">Khách hàng</th>
                                <th class="w-[24%] px-3.5 py-3">Sản phẩm</th>
                                <th class="w-[16%] px-3.5 py-3 text-right">Tổng tiền</th>
                                <th class="w-[13%] px-3.5 py-3 text-right">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border font-medium">
                        @forelse ($orders as $order)
                            @php
                                $firstItem = $order->items->first();
                                $totalPairs = $order->items->sum('quantity');
                                $extraItems = max(0, $order->items->count() - 1);
                                $itemsSummary = $order->items
                                    ->map(fn ($item) => $item->product_name_snapshot.' ('.$item->quantity.' đôi)')
                                    ->implode(', ');
                                $hasLongName = mb_strlen((string) $order->shipping_name) > 22;
                                $returnBadge = $resolveReturnBadge($order);
                            @endphp
                            <tr class="hover:bg-surface/50 transition-colors align-top">
                                <td class="px-3.5 py-3 min-w-0">
                                    <a href="{{ route('admin.orders.show', $order) }}"
                                       class="inline-flex items-center gap-1 font-mono font-bold text-accent underline decoration-accent/40 underline-offset-2 hover:decoration-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded break-all">
                                        <span>{{ $order->order_code }}</span>
                                        <span aria-hidden="true">→</span>
                                    </a>
                                    <span class="block mt-1 text-[11px] text-muted tabular-nums">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                                </td>
                                <td class="px-3.5 py-3 min-w-0">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <strong class="font-bold text-main text-xs truncate">{{ $order->shipping_name }}</strong>
                                        @if ($hasLongName)
                                            <x-admin.tooltip :text="$order->shipping_name" label="Xem đầy đủ tên người nhận" />
                                        @endif
                                    </div>
                                    <span class="block mt-0.5 text-[11px] text-muted font-mono">{{ $order->shipping_phone }}</span>
                                </td>
                                <td class="px-3.5 py-3 min-w-0">
                                    @if ($firstItem)
                                        <div class="flex items-center gap-1 min-w-0">
                                            <span class="block truncate font-semibold text-main text-xs">
                                                {{ $firstItem->product_name_snapshot }}
                                            </span>
                                            @if ($extraItems > 0 || mb_strlen($firstItem->product_name_snapshot) > 26)
                                                <x-admin.tooltip :text="$itemsSummary" label="Xem đầy đủ sản phẩm trong đơn" />
                                            @endif
                                        </div>
                                    @endif
                                    <span class="inline-flex items-center gap-1.5 mt-1 text-[11px] text-muted">
                                        <span class="bg-surface text-main border border-border px-1.5 py-0.5 rounded-md font-mono font-bold">{{ $totalPairs }} đôi</span>
                                        @if ($extraItems > 0)
                                            <span>+{{ $extraItems }} SP khác</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-3.5 py-3 text-right">
                                    <span class="block font-mono font-extrabold text-accent text-xs tabular-nums whitespace-nowrap">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</span>
                                    <span @class(['badge mt-1 inline-block', 'bg-success-bg text-success border border-success/30' => $order->payment_status === App\Enums\OrderPaymentStatus::Paid, 'bg-muted/15 text-muted border border-border/50' => $order->payment_status !== App\Enums\OrderPaymentStatus::Paid])>{{ $order->payment_status->label() }}</span>
                                </td>
                                <td class="px-3.5 py-3 text-right">
                                    <div class="flex flex-col items-end gap-1">
                                        <x-orders.status-badge :status="$order->order_status" />
                                        @if ($returnBadge)
                                            <span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $returnBadge['class'] }}">
                                                {{ $returnBadge['label'] }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-12 text-center text-xs text-muted">Chưa có đơn hàng.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$orders" />
            </div>
        </div>
    </div>
@endsection
