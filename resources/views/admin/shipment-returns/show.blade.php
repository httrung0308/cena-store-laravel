@extends('layouts.admin')
@section('title', 'Tiếp Nhận Hàng Hoàn')
@section('eyebrow', 'QUẢN TRỊ KHO')
@use('App\Enums\OrderUnitState')
@use('App\Enums\ReturnDisposition')
@use('App\Enums\RoleSlug')
@use('App\Models\ShipmentReturnEvent')
@php
    $tz = (string) config('store.business_timezone');
    $isAdmin = auth()->user()->hasRole(RoleSlug::Admin);
    $isStaffProcessor = auth()->user()->hasRole(RoleSlug::Staff) && ! $isAdmin;
    $returnsByUnit = $shipment->shipmentReturnItems->keyBy('order_item_unit_id');
@endphp
@section('actions')
    <a class="rounded-xl border border-border bg-surface px-4 py-2 text-xs font-bold text-muted hover:text-main" href="{{ route('admin.shipment-returns.index') }}">← Hàng hoàn cách ly</a>
@endsection
@section('content')
    <div class="space-y-6">
        <section class="admin-card space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-main">Đơn {{ $shipment->order->order_code }}</h2>
                    <p class="text-sm text-muted">{{ $shipment->order->shipping_name }} · {{ $shipment->order->shipping_phone }}</p>
                </div>
                <span class="badge border border-border bg-surface text-main">{{ $shipment->status->label() }}</span>
            </div>
            <div class="grid gap-2 text-sm text-muted sm:grid-cols-2">
                <p>Ngày giao thất bại: <span class="text-main">{{ $shipment->failed_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</span></p>
                <p>Đơn vị vận chuyển: <span class="text-main">{{ $shipment->carrier_name }}</span></p>
                <p>Mã vận đơn: <span class="text-main">{{ $shipment->tracking_code ?: '—' }}</span></p>
                <p>Ghi chú giao thất bại: <span class="text-main">{{ $shipment->note ?: '—' }}</span></p>
            </div>
            <p class="rounded-xl border border-warning/30 bg-warning-bg p-3 text-sm text-main">Nhận đúng từng đôi vào kho cách ly. Đôi # là số thứ tự trong dòng đơn, không phải số sê-ri vật lý. Chưa triển khai đánh dấu thất lạc hoặc hoàn tiền.</p>
        </section>

        @foreach ($shipment->order->items as $orderItem)
            <section class="admin-card space-y-4">
                <div>
                    <h3 class="font-semibold text-main">{{ $orderItem->product_name_snapshot }}</h3>
                    <p class="text-sm text-muted">{{ $orderItem->color_name_snapshot }} · Size {{ $orderItem->size_value_snapshot }} · SKU {{ $orderItem->sku_snapshot }} · {{ $orderItem->quantity }} đôi</p>
                </div>
                <div class="space-y-3">
                    @foreach ($orderItem->units as $unit)
                        @php($returnItem = $returnsByUnit->get($unit->id))
                        <article class="rounded-xl border border-border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="font-semibold text-main">Đôi #{{ $unit->unit_no }}</h4>
                                <span class="text-sm text-muted">{{ $unit->state->label() }}@if ($returnItem) · {{ $returnItem->disposition->label() }}@endif</span>
                            </div>
                            @php($unitEvents = $shipment->returnEvents->where('order_item_unit_id', $unit->id))
                            @if ($unitEvents->isNotEmpty())
                                <ol class="mt-3 space-y-2 border-t border-border pt-3 text-xs text-muted">
                                    @foreach ($unitEvents as $event)
                                        @php($eventLabel = match ($event->event_type) { ShipmentReturnEvent::MISSING_UNIT => 'Thiếu đôi', ShipmentReturnEvent::SKU_MISMATCH => 'SKU không khớp', ShipmentReturnEvent::DAMAGED_UNIT => 'Hư hỏng', ShipmentReturnEvent::NO_EVIDENCE => 'Thiếu chứng cứ', default => str_replace('_', ' ', $event->event_type) })
                                        <li><span class="font-semibold text-main">{{ $eventLabel }}</span> · {{ $event->actor?->full_name ?? '—' }} · {{ $event->occurred_at->setTimezone($tz)->format('d/m/Y H:i') }}@if ($event->note) — {{ $event->note }}@endif</li>
                                    @endforeach
                                </ol>
                            @endif
                            @if (! $returnItem && $unit->state === OrderUnitState::Outbound && $shipment->status === App\Enums\ShipmentStatus::Returning && $isStaffProcessor)
                                <form method="POST" action="{{ route('admin.shipment-returns.receive', $shipment) }}" class="mt-4 space-y-3 border-t border-border pt-4">
                                    @csrf
                                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                    <label class="admin-label" for="receive-note-{{ $unit->id }}">Tình trạng khi nhận</label>
                                    <textarea id="receive-note-{{ $unit->id }}" name="note" class="input min-h-24 w-full border-border bg-surface text-main" required minlength="5" maxlength="500">{{ old('unit_id') == $unit->id ? old('note') : '' }}</textarea>
                                    <button class="btn-pill-dark px-4 py-2">Nhận đôi vào kho cách ly</button>
                                </form>
                            @elseif (! $returnItem)
                                <p class="mt-2 text-sm text-muted">Chưa ghi nhận nhận hàng hoàn.</p>
                            @else
                                <p class="mt-2 text-sm text-muted">Nhận bởi {{ $returnItem->receivedBy?->full_name ?? '—' }} lúc {{ $returnItem->received_at->setTimezone($tz)->format('d/m/Y H:i') }} · {{ $returnItem->note }}</p>
                                @if ($returnItem->disposition === ReturnDisposition::Pending)
                                    @php($proposal = $returnItem->events->firstWhere('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED))
                                    @if ($proposal)
                                        <div class="mt-3 rounded-xl border border-warning/30 bg-warning-bg p-3 text-sm text-main">
                                            <p class="font-semibold">Đề xuất ghi hỏng bởi {{ $proposal->actor?->full_name ?? '—' }} · {{ $proposal->occurred_at->setTimezone($tz)->format('d/m/Y H:i') }}</p>
                                            <p class="mt-1 whitespace-pre-wrap">{{ $proposal->note }}</p>
                                        </div>
                                    @endif
                                    @if ($isStaffProcessor && ! $proposal)
                                        <div class="mt-4 grid gap-4 border-t border-border pt-4 lg:grid-cols-2">
                                            <form method="POST" action="{{ route('admin.shipment-return-items.restock', $returnItem) }}" class="space-y-3">
                                                @csrf
                                                <label class="admin-label" for="restock-note-{{ $returnItem->id }}">Kết quả kiểm tra đạt</label>
                                                <textarea id="restock-note-{{ $returnItem->id }}" name="note" class="input min-h-24 w-full border-border bg-surface text-main" required minlength="20" maxlength="500" placeholder="Mô tả kiểm tra và lý do có thể bán lại"></textarea>
                                                <button class="btn-pill-dark px-4 py-2">Đạt, nhập lại kho bán</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.shipment-return-items.write-off-proposal', $returnItem) }}" class="space-y-3">
                                                @csrf
                                                <label class="admin-label" for="writeoff-note-{{ $returnItem->id }}">Tình trạng cần Admin xem xét</label>
                                                <textarea id="writeoff-note-{{ $returnItem->id }}" name="note" class="input min-h-24 w-full border-border bg-surface text-main" required minlength="20" maxlength="500" placeholder="Mô tả hư hỏng và chứng cứ kiểm tra"></textarea>
                                                <button class="btn-pill px-4 py-2 text-error">Đề xuất ghi hỏng</button>
                                            </form>
                                        </div>
                                    @elseif ($isAdmin && $proposal)
                                        <form method="POST" action="{{ route('admin.shipment-return-items.write-off-approval', $returnItem) }}" class="mt-4 space-y-3 border-t border-border pt-4">
                                            @csrf
                                            <label class="admin-label" for="approve-note-{{ $returnItem->id }}">Ghi chú phê duyệt của Admin</label>
                                            <textarea id="approve-note-{{ $returnItem->id }}" name="note" class="input min-h-20 w-full border-border bg-surface text-main" required minlength="20" maxlength="500" placeholder="Xác nhận xem xét và phê duyệt ghi hỏng"></textarea>
                                            <button class="btn-pill px-4 py-2 text-error">Phê duyệt ghi hỏng và ghi nhận tổn thất</button>
                                        </form>
                                    @endif
                                @else
                                    <p class="mt-3 text-sm font-semibold text-main">Đã xử lý bởi {{ $returnItem->inspectedBy?->full_name ?? '—' }} lúc {{ $returnItem->inspected_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}.</p>
                                @endif
                            @endif
                            @if ($isStaffProcessor && (($unit->state === OrderUnitState::Outbound && $shipment->status === App\Enums\ShipmentStatus::Returning) || ($returnItem && $returnItem->disposition === ReturnDisposition::Pending)))
                                <form method="POST" action="{{ route('admin.shipment-returns.incidents', $shipment) }}" class="mt-4 space-y-3 border-t border-border pt-4">
                                    @csrf
                                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                    <label class="admin-label" for="incident-type-{{ $unit->id }}">Ghi nhận bất thường của đôi</label>
                                    <select id="incident-type-{{ $unit->id }}" name="incident_type" class="input w-full border-border bg-surface text-main" required>
                                        @if (! $returnItem)<option value="MISSING_UNIT">Thiếu đôi trong kiện</option><option value="SKU_MISMATCH">SKU thực nhận không khớp</option>@endif
                                        <option value="DAMAGED_UNIT">Đôi bị hư hỏng</option>
                                        <option value="NO_EVIDENCE">Thiếu chứng cứ nhận hàng</option>
                                    </select>
                                    <textarea name="note" class="input min-h-20 w-full border-border bg-surface text-main" required minlength="20" maxlength="1000" placeholder="Mô tả hiện trạng, SKU thấy trên kiện hoặc chứng cứ còn thiếu"></textarea>
                                    <button class="btn-pill px-4 py-2">Lưu bất thường</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
