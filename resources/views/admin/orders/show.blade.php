@extends('layouts.admin')
@section('title', 'Chi Tiết Đơn Hàng')
@section('eyebrow', 'QUẢN TRỊ BÁN HÀNG')
@section('toast_status', '1')
@use('App\Enums\OrderPaymentStatus')
@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')
@use('App\Enums\RefundStatus')
@use('App\Enums\RoleSlug')
@php
    $tz = (string) config('store.business_timezone');
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $address = $order->shipping_address_snapshot ?? [];
    $fullAddress = implode(', ', array_filter([
        $address['street'] ?? null,
        $address['ward_name'] ?? null,
        $address['province_name'] ?? null,
    ]));
    $status = $order->order_status;
    $totalPairs = (int) $order->items->sum('quantity');
    $isAdmin = auth()->user()?->hasRole(RoleSlug::Admin) ?? false;
    $cancellable = in_array($status, [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Packing], true);
    $nextAction = match ($status) {
        OrderStatus::Pending => ['action' => 'confirm', 'label' => 'Xác nhận và đóng gói'],
        OrderStatus::Confirmed => ['action' => 'pack', 'label' => 'Bắt đầu đóng gói'],
        default => null,
    };

    $paymentSummaryText = match (true) {
        $order->payment_status === OrderPaymentStatus::Refunded => 'Đã hoàn tiền mô phỏng · '.$vnd($order->refunded_amount),
        $order->payment_status === OrderPaymentStatus::PartiallyRefunded => 'Đã hoàn một phần · '.$vnd($order->refunded_amount),
        $order->payment_status === OrderPaymentStatus::RefundPending => 'Đang chờ duyệt hoàn tiền mô phỏng',
        $order->payment_status === OrderPaymentStatus::Paid => 'Đã thu đủ · '.$vnd($order->paid_amount),
        $order->payment_method === PaymentMethod::Cod && $order->payment_status === OrderPaymentStatus::Unpaid && $status !== OrderStatus::Cancelled && $status !== OrderStatus::Failed
            => 'Thu hộ COD khi giao · Cần thu '.$vnd($order->total_amount),
        $order->payment_method === PaymentMethod::PrepaidDemo && $order->payment_status === OrderPaymentStatus::Unpaid && $status !== OrderStatus::Cancelled && $status !== OrderStatus::Failed
            => 'Chờ khách thanh toán trả trước (mô phỏng)',
        default => 'Chưa thu tiền · '.$order->payment_status->label(),
    };

    $pendingRefund = $order->refunds->firstWhere('status', RefundStatus::Requested);
    $showDistinctBuyer = $order->user !== null
        && ($order->user->full_name !== $order->shipping_name || filled($order->user->email));
@endphp
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.orders.index') }}">← Danh sách đơn</a>
    @if ($status === OrderStatus::Failed && $order->shipment)
        <a class="px-4 py-2 bg-accent text-accent-text hover:bg-accent-hover text-xs font-bold rounded-xl transition-colors" href="#xu-ly-hang-hoan">Xử lý hàng hoàn ↓</a>
    @endif
@endsection
@section('content')
<div class="min-w-0 space-y-6">
    {{-- A. Trạng thái hiện tại và hành động tiếp theo --}}
    <section class="admin-card space-y-5">
        <div class="flex flex-col gap-4 border-b border-border pb-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <x-orders.status-badge :status="$status" />
                    @if ($status === OrderStatus::Failed && isset($returnProgress))
                        <span class="badge bg-warning-bg text-warning-text border border-warning/30">
                            {{ $returnProgress['progress_label'] ?? 'Đang hoàn hàng' }} · {{ $returnProgress['received_count'] ?? 0 }}/{{ $returnProgress['total_units'] ?? $totalPairs }} đôi đã nhận
                        </span>
                    @endif
                    <span class="text-xs text-muted tabular-nums">
                        Đặt lúc <strong class="font-semibold text-main">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</strong>
                    </span>
                    @if ($status === OrderStatus::Pending && $order->payment_expires_at)
                        <span class="text-xs text-warning-text tabular-nums">
                            · Hạn xác nhận: <strong class="font-semibold">{{ $order->payment_expires_at->setTimezone($tz)->format('d/m/Y H:i') }}</strong>
                        </span>
                    @endif
                </div>

                <div
                    x-data="{
                        copied: false,
                        copyCode(text) {
                            navigator.clipboard?.writeText(text).then(() => {
                                this.copied = true;
                                setTimeout(() => { this.copied = false; }, 1800);
                            });
                        }
                    }"
                    class="flex flex-wrap items-center gap-2 text-xs text-muted"
                >
                    <span>Mã đơn:</span>
                    <code class="rounded-lg border border-border bg-surface px-2 py-0.5 font-mono text-[11px] text-main break-all">{{ $order->order_code }}</code>
                    <button
                        type="button"
                        x-on:click="copyCode(@js($order->order_code))"
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface px-2 py-0.5 text-[11px] font-semibold text-muted hover:border-accent hover:text-accent transition-colors cursor-pointer"
                    >
                        <span x-show="!copied">Sao chép</span>
                        <span x-cloak x-show="copied" class="text-success">Đã sao chép</span>
                    </button>
                </div>
            </div>

            <div class="shrink-0 rounded-2xl border border-border bg-surface-alt/60 px-4 py-3 lg:text-right">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Tổng đơn ({{ $totalPairs }} đôi)</p>
                <p class="mt-0.5 text-2xl font-extrabold text-accent tabular-nums">{{ $vnd($order->total_amount) }}</p>
                <p class="mt-0.5 text-xs text-muted">{{ $order->payment_method->label() }} · <span class="font-medium text-main">{{ $paymentSummaryText }}</span></p>
            </div>
        </div>

        @if ($order->cancel_reason)
            <div class="rounded-xl border border-error/30 bg-error-bg px-4 py-3 text-xs text-error">
                <span class="font-bold">{{ $status === OrderStatus::Failed ? 'Lý do giao thất bại:' : 'Lý do hủy đơn:' }}</span>
                <span class="ml-1">{{ $order->cancel_reason }}</span>
            </div>
        @endif

        {{-- Hành động tiếp theo theo đúng trạng thái --}}
        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted">Hành động tiếp theo</h2>
            </div>

            @if ($nextAction !== null)
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('admin.orders.transition', [$order, $nextAction['action']]) }}" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">@csrf
                        <input type="hidden" name="expected_status" value="{{ $status->value }}">
                        <button :disabled="busy" class="btn-pill-dark px-6 py-2.5 disabled:opacity-50">
                            <span x-show="!busy">{{ $nextAction['label'] }}</span>
                            <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                        </button>
                    </form>

                    @if ($cancellable)
                        <x-ui.dialog id="confirm-cancel-order-dialog" title="Xác nhận hủy đơn hàng" trigger-class="btn-pill text-error hover:bg-error-bg">
                            <x-slot:trigger>Hủy đơn</x-slot:trigger>
                            <form method="POST" action="{{ route('admin.orders.transition', [$order, 'cancel']) }}" class="space-y-4" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="expected_status" value="{{ $status->value }}">
                                <p class="text-xs leading-relaxed text-muted">Hủy đơn hàng <strong class="text-main">{{ $order->order_code }}</strong> sẽ giải phóng toàn bộ số lượng hàng đã giữ cho đơn về tồn kho bán và cập nhật chứng từ hủy đơn. Thao tác này không thể hoàn tác.</p>
                                <div class="space-y-1.5">
                                    <label for="cancel-reason" class="admin-label text-muted">Lý do hủy</label>
                                    <input id="cancel-reason" name="reason" class="input w-full border-border bg-surface text-main" required minlength="5" maxlength="500" placeholder="Nhập lý do hủy đơn (tối thiểu 5 ký tự)...">
                                </div>
                                <div class="flex items-center justify-end gap-2 pt-2">
                                    <button type="button" x-on:click="closeDialog()" :disabled="busy" class="btn-pill text-xs">Đóng</button>
                                    <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error border-error/40 hover:bg-error/15 text-xs font-bold disabled:opacity-50">
                                        <span x-show="!busy">Xác nhận hủy đơn</span>
                                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang hủy đơn...</span>
                                    </button>
                                </div>
                            </form>
                        </x-ui.dialog>
                    @endif
                </div>
            @elseif ($status === OrderStatus::Packing)
                <div class="space-y-3 rounded-2xl border border-border bg-surface-alt/40 p-4">
                    <form method="POST" action="{{ route('admin.orders.transition', [$order, 'ship']) }}" class="grid gap-3 md:grid-cols-12 md:items-end" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">@csrf
                        <input type="hidden" name="expected_status" value="{{ $status->value }}">
                        <div class="md:col-span-4">
                            <x-admin.field name="carrier_name" label="Đơn vị vận chuyển" required placeholder="Ví dụ: GHN, GHTK" />
                        </div>
                        <div class="md:col-span-4">
                            <x-admin.field name="tracking_code" label="Mã vận đơn" placeholder="Không bắt buộc" />
                        </div>
                        <div class="flex flex-wrap items-center gap-2 md:col-span-4">
                            <button :disabled="busy" class="btn-pill-dark flex-1 py-2.5 disabled:opacity-50">
                                <span x-show="!busy">Giao vận chuyển</span>
                                <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xuất kho...</span>
                            </button>
                            <x-admin.tooltip :inline="true" text="Xác nhận bàn giao cho đơn vị vận chuyển và xuất kho thực tế." label="Giải thích giao vận chuyển" />
                        </div>
                    </form>

                    <div class="flex items-center justify-end border-t border-border/70 pt-3">
                        <x-ui.dialog id="confirm-cancel-order-dialog" title="Xác nhận hủy đơn hàng" trigger-class="btn-pill text-xs text-error hover:bg-error-bg">
                            <x-slot:trigger>Hủy đơn</x-slot:trigger>
                            <form method="POST" action="{{ route('admin.orders.transition', [$order, 'cancel']) }}" class="space-y-4" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="expected_status" value="{{ $status->value }}">
                                <p class="text-xs leading-relaxed text-muted">Hủy đơn hàng <strong class="text-main">{{ $order->order_code }}</strong> sẽ giải phóng toàn bộ số lượng hàng đã giữ cho đơn về tồn kho bán và cập nhật chứng từ hủy đơn. Thao tác này không thể hoàn tác.</p>
                                <div class="space-y-1.5">
                                    <label for="cancel-reason" class="admin-label text-muted">Lý do hủy</label>
                                    <input id="cancel-reason" name="reason" class="input w-full border-border bg-surface text-main" required minlength="5" maxlength="500" placeholder="Nhập lý do hủy đơn (tối thiểu 5 ký tự)...">
                                </div>
                                <div class="flex items-center justify-end gap-2 pt-2">
                                    <button type="button" x-on:click="closeDialog()" :disabled="busy" class="btn-pill text-xs">Đóng</button>
                                    <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error border-error/40 hover:bg-error/15 text-xs font-bold disabled:opacity-50">
                                        <span x-show="!busy">Xác nhận hủy đơn</span>
                                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang hủy đơn...</span>
                                    </button>
                                </div>
                            </form>
                        </x-ui.dialog>
                    </div>
                </div>
            @elseif ($status === OrderStatus::OnDelivery)
                <div class="space-y-3 rounded-2xl border border-border bg-surface-alt/40 p-4">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.orders.transition', [$order, 'deliver']) }}" class="flex flex-col gap-3 md:flex-row md:items-end" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">@csrf
                        <input type="hidden" name="expected_status" value="{{ $status->value }}">
                        @if ($order->payment_method === PaymentMethod::Cod)
                            <div class="flex-1 min-w-0">
                                <x-admin.field name="proof" type="file" label="Ảnh minh chứng giao COD" required accept="image/jpeg,image/png,image/webp" hint="JPEG, PNG hoặc WebP; tối đa 5 MB. Ảnh được lưu riêng tư." />
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button :disabled="busy" class="btn-pill-dark px-6 py-2.5 disabled:opacity-50">
                                <span x-show="!busy">Xác nhận giao thành công</span>
                                <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang ghi nhận...</span>
                            </button>
                        </div>
                    </form>

                    <div class="flex items-center justify-end border-t border-border/70 pt-3">
                        <x-ui.dialog id="confirm-fail-order-dialog" title="Xác nhận báo giao thất bại" trigger-class="btn-pill text-xs text-error hover:bg-error-bg">
                            <x-slot:trigger>Báo giao thất bại</x-slot:trigger>
                            <form method="POST" action="{{ route('admin.orders.transition', [$order, 'fail']) }}" class="space-y-4" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="expected_status" value="{{ $status->value }}">
                                <p class="text-xs leading-relaxed text-muted">Thao tác này đánh dấu đơn hàng <strong class="text-main">{{ $order->order_code }}</strong> giao thất bại để tiếp nhận hàng hoàn về kho theo quy trình. Không thể hoàn tác.</p>
                                <div class="space-y-1.5">
                                    <label for="fail-reason" class="admin-label text-muted">Lý do giao thất bại</label>
                                    <input id="fail-reason" name="reason" class="input w-full border-border bg-surface text-main" required minlength="5" maxlength="500" placeholder="Nhập lý do (tối thiểu 5 ký tự)...">
                                </div>
                                <div class="flex items-center justify-end gap-2 pt-2">
                                    <button type="button" x-on:click="closeDialog()" :disabled="busy" class="btn-pill text-xs">Đóng</button>
                                    <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error border-error/40 hover:bg-error/15 text-xs font-bold disabled:opacity-50">
                                        <span x-show="!busy">Xác nhận giao thất bại</span>
                                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                                    </button>
                                </div>
                            </form>
                        </x-ui.dialog>
                    </div>
                </div>
            @elseif ($status === OrderStatus::Failed && $order->shipment)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-warning/30 bg-warning-bg/40 px-4 py-3">
                    <p class="text-xs text-main">
                        Đơn giao thất bại. Tiếp nhận kiểm tra và xử lý từng đôi ngay tại khối <strong>Xử lý hàng hoàn</strong> bên dưới.
                    </p>
                    <a href="#xu-ly-hang-hoan" class="btn-pill-dark text-xs">Đi tới xử lý hàng hoàn ↓</a>
                </div>
            @elseif ($pendingRefund !== null)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-warning/30 bg-warning-bg/40 px-4 py-3">
                    <p class="text-xs text-main">
                        Đơn đã hủy có yêu cầu hoàn tiền mô phỏng <strong class="tabular-nums">{{ $vnd($pendingRefund->amount) }}</strong> đang chờ Admin duyệt.
                    </p>
                    @if ($isAdmin)
                        <x-ui.dialog id="confirm-approve-refund-top-{{ $pendingRefund->id }}-dialog" title="Xác nhận duyệt hoàn tiền" trigger-class="btn-pill-dark text-xs">
                            <x-slot:trigger>Duyệt hoàn tiền mô phỏng</x-slot:trigger>
                            <form method="POST" action="{{ route('admin.orders.refunds.approve', [$order, $pendingRefund]) }}" class="space-y-4" x-data="{ busy: false }" x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <p class="text-xs leading-relaxed text-muted">Xác nhận duyệt hoàn tiền mô phỏng số tiền <strong class="text-main">{{ $vnd($pendingRefund->amount) }}</strong> cho đơn hàng <strong class="text-main">{{ $order->order_code }}</strong>. Thao tác này sẽ ghi nhận chứng từ hoàn tiền và không thể hoàn tác.</p>
                                <div class="flex items-center justify-end gap-2 pt-2">
                                    <button type="button" x-on:click="closeDialog()" :disabled="busy" class="btn-pill text-xs">Đóng</button>
                                    <button type="submit" :disabled="busy" class="btn-pill bg-warning-bg text-warning-text border-warning/40 hover:bg-warning/15 text-xs font-bold disabled:opacity-50">
                                        <span x-show="!busy">Xác nhận duyệt hoàn tiền</span>
                                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang duyệt...</span>
                                    </button>
                                </div>
                            </form>
                        </x-ui.dialog>
                    @endif
                </div>
            @else
                <p class="text-xs text-muted">Đơn hàng đã hoàn tất, không còn thao tác xử lý tiếp theo.</p>
            @endif
        </div>
    </section>

    {{-- B. Giày trong đơn và số tiền --}}
    <section class="admin-card space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-main">Giày trong đơn</h2>
            <span class="badge border border-border bg-surface text-main font-mono">{{ $totalPairs }} đôi</span>
        </div>

        {{-- Mobile item cards --}}
        <div class="divide-y divide-border md:hidden">
            @foreach ($order->items as $item)
                @php
                    $thumbUrl = $item->variant?->color?->thumbnail?->thumbnailUrl(200);
                @endphp
                <div class="flex gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-border bg-surface flex items-center justify-center">
                        @if ($thumbUrl)
                            <img src="{{ $thumbUrl }}" alt="{{ $item->product_name_snapshot }}" class="h-full w-full object-cover">
                        @else
                            <span class="text-[10px] font-semibold text-muted">No img</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-1 text-xs">
                        <p class="font-bold text-main break-words">{{ $item->product_name_snapshot }}</p>
                        <p class="text-muted">{{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }}</p>
                        <p class="font-mono text-[11px] text-muted break-all">SKU: {{ $item->sku_snapshot }}</p>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <span class="text-muted tabular-nums">{{ $item->quantity }} đôi × {{ $vnd($item->unit_price) }}</span>
                            <span class="font-bold text-main tabular-nums">{{ $vnd($item->net_amount) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Desktop clean table (no horizontal scroll) --}}
        <div class="hidden md:block">
            <table class="w-full table-fixed border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs font-bold uppercase tracking-wider text-muted">
                        <th class="w-[54%] pb-3 pr-4">Sản phẩm</th>
                        <th class="w-[10%] pb-3 px-2 text-right">SL</th>
                        <th class="w-[18%] pb-3 px-2 text-right">Đơn giá</th>
                        <th class="w-[18%] pb-3 pl-2 text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($order->items as $item)
                        @php
                            $thumbUrl = $item->variant?->color?->thumbnail?->thumbnailUrl(200);
                        @endphp
                        <tr class="align-top">
                            <td class="py-3.5 pr-4 min-w-0">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-border bg-surface flex items-center justify-center">
                                        @if ($thumbUrl)
                                            <img src="{{ $thumbUrl }}" alt="{{ $item->product_name_snapshot }}" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-[10px] font-semibold text-muted">No img</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 space-y-0.5">
                                        <p class="font-bold text-main break-words">{{ $item->product_name_snapshot }}</p>
                                        <p class="text-xs text-muted">{{ $item->color_name_snapshot }} · <span class="font-semibold text-main">Size {{ $item->size_value_snapshot }}</span></p>
                                        <p class="font-mono text-[11px] text-muted break-all">SKU: {{ $item->sku_snapshot }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-2 text-right font-semibold text-main tabular-nums whitespace-nowrap">{{ $item->quantity }}</td>
                            <td class="py-3.5 px-2 text-right text-muted tabular-nums whitespace-nowrap">{{ $vnd($item->unit_price) }}</td>
                            <td class="py-3.5 pl-2 text-right font-bold text-main tabular-nums whitespace-nowrap">{{ $vnd($item->net_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tổng tiền ngay dưới danh sách giày --}}
        <div class="border-t border-border pt-4 flex justify-end">
            <dl class="w-full max-w-md space-y-1.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Tạm tính</dt>
                    <dd class="text-main tabular-nums">{{ $vnd($order->subtotal_amount) }}</dd>
                </div>
                @if ($order->merchandise_discount_amount > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Giảm giá {{ $order->coupon_code_snapshot ? '('.$order->coupon_code_snapshot.')' : '' }}</dt>
                        <dd class="text-accent font-medium tabular-nums">−{{ $vnd($order->merchandise_discount_amount) }}</dd>
                    </div>
                @endif
                @if ($order->shipping_discount_amount > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">Giảm vận chuyển</dt>
                        <dd class="text-accent font-medium tabular-nums">−{{ $vnd($order->shipping_discount_amount) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">Phí vận chuyển</dt>
                    <dd class="text-main tabular-nums">{{ $order->shipping_fee === 0 ? 'Miễn phí' : $vnd($order->shipping_fee) }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-border pt-2 text-base font-bold">
                    <dt class="text-main">Tổng cộng</dt>
                    <dd class="text-accent text-lg tabular-nums">{{ $vnd($order->total_amount) }}</dd>
                </div>
                <div class="flex justify-between gap-4 text-xs pt-0.5">
                    <dt class="text-muted">Thanh toán</dt>
                    <dd class="text-right font-medium text-main">{{ $paymentSummaryText }}</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- C. Người nhận, địa chỉ, thanh toán và vận chuyển --}}
    <div class="grid min-w-0 gap-6 md:grid-cols-2">
        <section class="admin-card min-w-0 space-y-4 text-sm">
            <h2 class="text-base font-bold text-main">Khách hàng & Giao nhận</h2>
            <div class="space-y-1.5 rounded-xl border border-border/60 bg-surface-alt/50 p-3.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Người nhận hàng</p>
                <p class="font-bold text-main break-words">{{ $order->shipping_name }} · <span class="font-mono">{{ $order->shipping_phone }}</span></p>
                <p class="text-xs leading-relaxed text-muted break-words">{{ $fullAddress ?: 'Chưa có địa chỉ chi tiết' }}</p>
            </div>

            @if ($showDistinctBuyer)
                <div class="space-y-1 text-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Tài khoản đặt hàng</p>
                    <p class="font-semibold text-main break-words">{{ $order->user->full_name }}</p>
                    @if ($order->user->email)
                        <p class="text-muted break-all">{{ $order->user->email }}</p>
                    @endif
                </div>
            @endif

            @if ($order->customer_note)
                <div class="rounded-xl border border-border bg-surface p-3 text-xs">
                    <p class="font-bold text-main">Ghi chú của khách</p>
                    <p class="mt-1 whitespace-pre-wrap break-words text-muted">{{ $order->customer_note }}</p>
                </div>
            @endif
        </section>

        <section class="admin-card min-w-0 space-y-4 text-sm">
            <h2 class="text-base font-bold text-main">Vận chuyển & Thanh toán</h2>

            <div class="space-y-1.5 rounded-xl border border-border/60 bg-surface-alt/50 p-3.5 text-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Vận chuyển</p>
                @if ($order->shipment)
                    <p class="text-sm font-semibold text-main">
                        {{ $order->shipment->carrier_name }}
                        @if ($order->shipment->tracking_code)
                            · <span class="font-mono text-xs">{{ $order->shipment->tracking_code }}</span>
                        @endif
                    </p>
                    <p class="text-muted">Trạng thái: <span class="font-medium text-main">{{ $order->shipment->status->label() }}</span></p>
                    @if ($order->shipment->shipped_at)
                        <p class="text-muted tabular-nums">Bàn giao: {{ $order->shipment->shipped_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                    @endif
                    @if ($order->shipment->delivered_at)
                        <p class="text-muted tabular-nums">Giao thành công: {{ $order->shipment->delivered_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                    @endif
                    @if ($order->shipment->deliveryProof)
                        <a class="inline-block pt-1 font-semibold text-accent underline hover:text-accent-hover" href="{{ route('admin.orders.delivery-proof', $order) }}" target="_blank" rel="noopener">Xem ảnh minh chứng giao COD →</a>
                    @endif
                @else
                    <p class="text-muted">Chưa bàn giao vận chuyển.</p>
                @endif
            </div>

            <div class="space-y-2 text-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Thanh toán & Hoàn tiền</p>
                <p class="text-main">
                    Phương thức: <span class="font-semibold">{{ $order->payment_method->label() }}</span> · {{ $order->payment_status->label() }}
                </p>
                @foreach ($order->payments as $payment)
                    <p class="text-muted">
                        Giao dịch: <span class="font-medium text-main">{{ $payment->status->label() }}</span>
                        @if ($payment->provider_transaction_id && $payment->method !== PaymentMethod::PrepaidDemo)
                            · <span class="font-mono">{{ $payment->provider_transaction_id }}</span>
                        @endif
                    </p>
                @endforeach
                @foreach ($order->refunds as $refund)
                    <div class="rounded-xl border border-border bg-surface p-2.5 flex flex-wrap items-center justify-between gap-2">
                        <span>
                            Hoàn tiền mô phỏng: <strong class="text-main">{{ $refund->status === RefundStatus::Approved ? 'Đã hoàn (mô phỏng)' : $refund->status->label() }}</strong> · <span class="font-mono font-bold text-main">{{ $vnd($refund->amount) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Khối xử lý hàng hoàn (giữ nguyên một thẻ/đôi và anchor #xu-ly-hang-hoan) --}}
    @if ($status === OrderStatus::Failed && $order->shipment)
        @include('admin.orders.partials.return-processing')
    @endif

    {{-- D. Lịch sử và thông tin kỹ thuật --}}
    <div class="space-y-4">
        <section class="admin-card">
            <h2 class="mb-3 text-base font-bold text-main">Lịch sử trạng thái</h2>
            <ol class="space-y-2.5 text-sm">
                @foreach ($order->statusHistories as $history)
                    <li class="border-b border-border/70 pb-2.5 last:border-b-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-wrap items-center gap-2">
                                <x-orders.status-badge :status="$history->to_status" />
                                <span class="text-xs text-muted">{{ $history->actor_name_snapshot }}</span>
                            </span>
                            <span class="text-xs text-muted tabular-nums">{{ $history->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                        </div>
                        @if ($history->note)
                            <details class="mt-1.5 text-xs text-muted">
                                <summary class="cursor-pointer select-none font-medium hover:text-main">Xem ghi chú</summary>
                                <p class="mt-1 whitespace-pre-wrap break-words rounded-lg bg-surface p-2 text-main">{{ $history->note }}</p>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        <details class="admin-card text-xs text-muted">
            <summary class="cursor-pointer select-none font-bold text-main hover:text-accent transition-colors">
                Thông tin kỹ thuật & chi tiết phân bổ từng đôi ({{ $totalPairs }} đôi)
            </summary>
            <div class="mt-4 space-y-4 border-t border-border pt-4">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <p>Order ID: <span class="font-mono text-main">#{{ $order->id }}</span></p>
                    <p class="break-all">Mã đơn đầy đủ: <span class="font-mono text-main">{{ $order->order_code }}</span></p>
                    @if ($order->payment_expires_at)
                        <p>Hạn thanh toán/xác nhận: <span class="font-mono text-main">{{ $order->payment_expires_at->setTimezone($tz)->format('d/m/Y H:i') }}</span></p>
                    @endif
                </div>
                <div class="space-y-2">
                    <p class="font-bold uppercase tracking-wider text-muted">Phân bổ từng đôi (`order_item_units`)</p>
                    @foreach ($order->items as $item)
                        <div class="rounded-xl border border-border bg-surface p-3 space-y-1">
                            <p class="font-semibold text-main">{{ $item->product_name_snapshot }} · {{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }} <span class="font-mono font-normal text-muted">({{ $item->sku_snapshot }})</span></p>
                            <p class="text-muted">
                                @foreach ($item->units as $unit)
                                    Đôi #{{ $unit->unit_no }}: <span class="font-medium text-main">{{ $unit->state->label() }}</span>@if (! $loop->last) · @endif
                                @endforeach
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    </div>
</div>
@endsection
