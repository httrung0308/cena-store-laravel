@use('App\Enums\OrderUnitState')
@use('App\Enums\ReturnDisposition')
@use('App\Enums\RoleSlug')
@use('App\Enums\ShipmentStatus')
@use('App\Models\ShipmentReturnEvent')
@php
    $shipment = $order->shipment;
    $tz = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->hasRole(RoleSlug::Admin) ?? false;
    $returnItems = $shipment->shipmentReturnItems;
    $returnsByUnit = $returnItems->keyBy('order_item_unit_id');
    $totalUnits = max(1, (int) $order->items->sum('quantity'));
    $receivedCount = $returnItems->count();
    $pendingItems = $returnItems->where('disposition', ReturnDisposition::Pending);
    $hasWriteOffProposal = $pendingItems->isNotEmpty()
        && $shipment->returnEvents
            ->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED)
            ->whereIn('shipment_return_item_id', $pendingItems->pluck('id'))
            ->isNotEmpty();

    if ($receivedCount === 0) {
        $progressBadge = $shipment->status === ShipmentStatus::Lost
            ? ['label' => 'Thất lạc', 'class' => 'bg-error-bg text-error-text border border-error/30']
            : ['label' => 'Chờ nhận', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
    } elseif ($receivedCount < $totalUnits && $shipment->status === ShipmentStatus::Returning) {
        $progressBadge = ['label' => 'Đã nhận một phần', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
    } elseif ($hasWriteOffProposal) {
        $progressBadge = ['label' => 'Hàng hỏng cần xử lý', 'class' => 'bg-error-bg text-error-text border border-error/30'];
    } elseif ($pendingItems->isNotEmpty()) {
        $progressBadge = ['label' => 'Chờ kiểm tra', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
    } else {
        $progressBadge = ['label' => 'Đã xử lý', 'class' => 'bg-success-bg text-success-text border border-success/30'];
    }

    $eventLabel = static fn (string $type): string => match ($type) {
        ShipmentReturnEvent::RECEIVED => 'Đã nhận vào kho cách ly',
        ShipmentReturnEvent::RESTOCKED => 'Đã nhập lại kho bán',
        ShipmentReturnEvent::WRITE_OFF_PROPOSED => 'Ghi nhận hàng hỏng',
        ShipmentReturnEvent::WRITE_OFF_APPROVED => 'Đã ghi hỏng & ghi nhận tổn thất',
        ShipmentReturnEvent::MISSING_UNIT => 'Thiếu đôi trong kiện',
        ShipmentReturnEvent::SKU_MISMATCH => 'SKU không khớp',
        ShipmentReturnEvent::DAMAGED_UNIT => 'Hư hỏng',
        ShipmentReturnEvent::NO_EVIDENCE => 'Thiếu chứng cứ',
        default => str_replace('_', ' ', $type),
    };
@endphp

<section id="xu-ly-hang-hoan" class="admin-card space-y-6 scroll-mt-6">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-semibold text-main">Xử lý hàng hoàn</h2>
                <x-admin.tooltip
                    :inline="true"
                    text="Xử lý từng đôi trong kiện hoàn theo 1 bước (nhập lại kho hoặc ghi nhận/ghi hỏng ngay khi nhận) hoặc 2 bước (đưa vào khu cách ly rồi kiểm tra sau)."
                    label="Hướng dẫn xử lý hàng hoàn"
                />
            </div>
            <p class="text-xs text-muted">
                Vận đơn {{ $shipment->carrier_name }}@if ($shipment->tracking_code) · {{ $shipment->tracking_code }}@endif
                @if ($shipment->failed_at)
                    · Giao thất bại lúc {{ $shipment->failed_at->setTimezone($tz)->format('d/m/Y H:i') }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $progressBadge['class'] }}">
                {{ $progressBadge['label'] }}
            </span>
            <span class="inline-flex items-center rounded-full border border-border bg-surface px-2.5 py-1 text-xs font-medium text-muted">
                {{ $receivedCount }}/{{ $totalUnits }} đôi đã nhận
            </span>
        </div>
    </div>

    <div class="space-y-4">
        @foreach ($order->items as $item)
            @php
                $thumbnailUrl = $item->variant?->color?->thumbnail?->thumbnailUrl(160);
            @endphp
            @foreach ($item->units as $unit)
                @php
                    $returnItem = $returnsByUnit->get($unit->id);
                    $unitIncidents = $shipment->returnEvents
                        ->where('order_item_unit_id', $unit->id)
                        ->whereIn('event_type', [
                            ShipmentReturnEvent::MISSING_UNIT,
                            ShipmentReturnEvent::SKU_MISMATCH,
                            ShipmentReturnEvent::DAMAGED_UNIT,
                            ShipmentReturnEvent::NO_EVIDENCE,
                        ]);
                    $canReceive = ! $returnItem
                        && $unit->state === OrderUnitState::Outbound
                        && $shipment->status === ShipmentStatus::Returning;
                    $canReportIncident = ($unit->state === OrderUnitState::Outbound && $shipment->status === ShipmentStatus::Returning)
                        || ($returnItem && $returnItem->disposition === ReturnDisposition::Pending);
                    $proposal = $returnItem?->events->firstWhere('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED);
                @endphp

                <article class="rounded-2xl border border-border bg-surface-card p-4 space-y-4" x-data="{ openIncident: false }">
                    {{-- Header: thông tin đôi giày & trạng thái tổng hợp --}}
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-3 min-w-0">
                            @if ($thumbnailUrl)
                                <img src="{{ $thumbnailUrl }}"
                                     alt="{{ $item->product_name_snapshot }}"
                                     class="h-14 w-14 shrink-0 rounded-xl border border-border object-cover bg-surface">
                            @else
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-border bg-surface text-[10px] font-bold uppercase text-muted">
                                    Giày
                                </div>
                            @endif
                            <div class="min-w-0 space-y-0.5">
                                <h3 class="font-semibold text-main break-words">{{ $item->product_name_snapshot }}</h3>
                                <p class="text-xs text-main">{{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }}</p>
                                <p class="text-[11px] font-mono text-muted">SKU {{ $item->sku_snapshot }} · Đôi #{{ $unit->unit_no }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col items-start sm:items-end gap-1 shrink-0 text-xs">
                            @if (! $returnItem)
                                <span class="inline-flex items-center rounded-full border border-warning/30 bg-warning-bg px-2.5 py-0.5 text-[11px] font-semibold text-warning-text">
                                    Chưa nhận hàng hoàn
                                </span>
                            @elseif ($returnItem->disposition === ReturnDisposition::Pending)
                                @if ($proposal)
                                    <span class="inline-flex items-center rounded-full border border-error/30 bg-error-bg px-2.5 py-0.5 text-[11px] font-semibold text-error-text">
                                        Hàng hỏng cần xử lý
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-warning/30 bg-warning-bg px-2.5 py-0.5 text-[11px] font-semibold text-warning-text">
                                        Đang cách ly · Chờ kiểm tra
                                    </span>
                                @endif
                            @elseif ($returnItem->disposition === ReturnDisposition::Restock)
                                <span class="inline-flex items-center rounded-full border border-success/30 bg-success-bg px-2.5 py-0.5 text-[11px] font-semibold text-success-text">
                                    {{ $returnItem->disposition->label() }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-error/30 bg-error-bg px-2.5 py-0.5 text-[11px] font-semibold text-error-text">
                                    {{ $returnItem->disposition->label() }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Bất thường đã ghi nhận --}}
                    @if ($unitIncidents->isNotEmpty())
                        <div class="rounded-xl border border-warning/30 bg-warning-bg p-3 space-y-1.5 text-xs">
                            <p class="font-semibold text-warning-text">Bất thường đã ghi nhận</p>
                            <ol class="space-y-1 text-main">
                                @foreach ($unitIncidents as $incident)
                                    <li>
                                        <span class="font-semibold">{{ $eventLabel($incident->event_type) }}</span>
                                        · {{ $incident->actor?->full_name ?? '—' }}
                                        · {{ $incident->occurred_at->setTimezone($tz)->format('d/m/Y H:i') }}
                                        @if ($incident->note)
                                            — {{ $incident->note }}
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    @if ($canReceive)
                        {{-- Chưa nhận hàng hoàn: 1-step & 2-step flows --}}
                        <div class="grid gap-3 border-t border-border pt-3 lg:grid-cols-2">
                            {{-- 1-step: Hàng tốt — Nhập lại kho --}}
                            <form method="POST"
                                  action="{{ route('admin.shipment-returns.receive-and-restock', $shipment) }}"
                                  class="space-y-2.5 rounded-xl border border-border bg-surface p-3.5"
                                  x-data="{ busy: false }"
                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                <label class="admin-label text-main" for="receive-restock-note-{{ $unit->id }}">Hàng tốt — Nhập lại kho</label>
                                <textarea id="receive-restock-note-{{ $unit->id }}"
                                          name="note"
                                          rows="2"
                                          class="input w-full border-border bg-surface-card text-main text-sm"
                                          required
                                          minlength="5"
                                          maxlength="500"
                                          placeholder="Hộp/giày nguyên vẹn, đủ phụ kiện để nhập lại kho bán (tối thiểu 5 ký tự)..."></textarea>
                                <button type="submit" :disabled="busy" class="btn-pill-dark px-4 py-2 text-xs disabled:opacity-50">
                                    <span x-show="!busy">Hàng tốt — Nhập lại kho</span>
                                    <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                                </button>
                            </form>

                            {{-- 1-step: Hàng hỏng (Staff flag / Admin write-off) --}}
                            <form method="POST"
                                  action="{{ $isAdmin ? route('admin.shipment-returns.receive-and-write-off', $shipment) : route('admin.shipment-returns.receive-and-flag-damaged', $shipment) }}"
                                  class="space-y-2.5 rounded-xl border border-border bg-surface p-3.5"
                                  x-data="{ busy: false }"
                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                <label class="admin-label text-main" for="receive-damaged-note-{{ $unit->id }}">
                                    {{ $isAdmin ? 'Hàng hỏng — Ghi hỏng & ghi nhận tổn thất' : 'Hàng hỏng — Ghi nhận chờ Admin xử lý' }}
                                </label>
                                <textarea id="receive-damaged-note-{{ $unit->id }}"
                                          name="note"
                                          rows="2"
                                          class="input w-full border-border bg-surface-card text-main text-sm"
                                          required
                                          minlength="20"
                                          maxlength="500"
                                          placeholder="Mô tả chi tiết tình trạng hư hỏng và chứng cứ kiểm tra (tối thiểu 20 ký tự)..."></textarea>
                                <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error-text border-error/40 px-4 py-2 text-xs font-bold disabled:opacity-50">
                                    <span x-show="!busy">{{ $isAdmin ? 'Ghi hỏng & ghi nhận tổn thất' : 'Ghi nhận hàng hỏng' }}</span>
                                    <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang xử lý...</span>
                                </button>
                            </form>
                        </div>

                        {{-- 2-step fallback: Chưa kiểm tra — Đưa vào cách ly --}}
                        <div class="rounded-xl border border-dashed border-border bg-surface/60 p-3.5 space-y-2.5">
                            <form method="POST"
                                  action="{{ route('admin.shipment-returns.receive', $shipment) }}"
                                  class="space-y-2.5"
                                  x-data="{ busy: false }"
                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                <label class="admin-label text-muted" for="receive-note-{{ $unit->id }}">Chưa kiểm tra — Đưa vào cách ly</label>
                                <textarea id="receive-note-{{ $unit->id }}"
                                          name="note"
                                          rows="2"
                                          class="input w-full border-border bg-surface text-main text-sm"
                                          required
                                          minlength="5"
                                          maxlength="500"
                                          placeholder="Mô tả tình trạng hộp/giày thực tế khi nhận hoàn để đưa vào kho cách ly (tối thiểu 5 ký tự)...">{{ old('unit_id') == $unit->id ? old('note') : '' }}</textarea>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <button type="submit" :disabled="busy" class="btn-pill px-4 py-2 text-xs font-semibold disabled:opacity-50">
                                        <span x-show="!busy">Chưa kiểm tra — Đưa vào cách ly</span>
                                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang ghi nhận...</span>
                                    </button>
                                    @if ($canReportIncident)
                                        <button type="button"
                                                @click="openIncident = !openIncident"
                                                class="text-xs font-semibold text-muted hover:text-error underline underline-offset-2 transition-colors">
                                            <span x-text="openIncident ? 'Ẩn báo cáo sự cố' : 'Báo cáo sự cố / bất thường'">Báo cáo sự cố / bất thường</span>
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @elseif (! $returnItem)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs text-muted">
                            <span>
                                @if ($shipment->status === ShipmentStatus::Lost)
                                    Kiện hàng đang ở trạng thái thất lạc nên không thể tiếp nhận hàng hoàn.
                                @elseif ($shipment->status !== ShipmentStatus::Returning)
                                    Kiện hàng hiện ở trạng thái «{{ $shipment->status->label() }}», chưa ở trạng thái đang hoàn để tiếp nhận.
                                @else
                                    Đôi này đang ở trạng thái «{{ $unit->state->label() }}» nên không cần xác nhận nhận hoàn.
                                @endif
                            </span>
                            @if ($canReportIncident)
                                <button type="button"
                                        @click="openIncident = !openIncident"
                                        class="text-xs font-semibold text-muted hover:text-error underline underline-offset-2 transition-colors">
                                    <span x-text="openIncident ? 'Ẩn báo cáo sự cố' : 'Báo cáo sự cố / bất thường'">Báo cáo sự cố / bất thường</span>
                                </button>
                            @endif
                        </div>
                    @elseif ($returnItem->disposition === ReturnDisposition::Pending)
                        {{-- Đang cách ly: xử lý bước 2 --}}
                        <div class="rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs text-muted">
                            Nhận bởi <span class="font-semibold text-main">{{ $returnItem->receivedBy?->full_name ?? '—' }}</span>
                            lúc <span class="font-medium text-main">{{ $returnItem->received_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                            @if ($returnItem->note)
                                · {{ $returnItem->note }}
                            @endif
                        </div>

                        @if ($proposal)
                            <div class="rounded-xl border border-warning/30 bg-warning-bg p-3 text-xs text-main space-y-1">
                                <p class="font-semibold text-warning-text">
                                    Ghi nhận hàng hỏng bởi {{ $proposal->actor?->full_name ?? '—' }} · {{ $proposal->occurred_at->setTimezone($tz)->format('d/m/Y H:i') }}
                                </p>
                                <p class="whitespace-pre-wrap">{{ $proposal->note }}</p>
                            </div>
                        @endif

                        <div class="grid gap-4 border-t border-border pt-3 lg:grid-cols-2">
                            {{-- Nhập lại kho bán: cả Admin và Staff --}}
                            <form method="POST"
                                  action="{{ route('admin.shipment-return-items.restock', $returnItem) }}"
                                  class="space-y-3 rounded-xl border border-border bg-surface p-3.5"
                                  x-data="{ busy: false }"
                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <label class="admin-label text-main" for="restock-note-{{ $returnItem->id }}">Hàng tốt — Nhập lại kho</label>
                                <textarea id="restock-note-{{ $returnItem->id }}"
                                          name="note"
                                          rows="3"
                                          class="input w-full border-border bg-surface-card text-main text-sm"
                                          required
                                          minlength="20"
                                          maxlength="500"
                                          placeholder="Mô tả kết quả kiểm tra ngoại quan, hộp và lý do đủ điều kiện bán lại (tối thiểu 20 ký tự)..."></textarea>
                                <button type="submit" :disabled="busy" class="btn-pill-dark px-4 py-2 text-xs disabled:opacity-50">
                                    <span x-show="!busy">Hàng tốt — Nhập lại kho</span>
                                    <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang nhập kho...</span>
                                </button>
                            </form>

                            {{-- Ghi hỏng: Staff ghi nhận / Admin ghi hỏng trực tiếp --}}
                            <div class="rounded-xl border border-border bg-surface p-3.5 space-y-3">
                                @if ($isAdmin)
                                    <form method="POST"
                                          action="{{ route('admin.shipment-return-items.write-off-approval', $returnItem) }}"
                                          class="space-y-3"
                                          x-data="{ busy: false }"
                                          x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                        @csrf
                                        <label class="admin-label text-main" for="approve-note-{{ $returnItem->id }}">Ghi hỏng & ghi nhận tổn thất</label>
                                        <textarea id="approve-note-{{ $returnItem->id }}"
                                                  name="note"
                                                  rows="3"
                                                  class="input w-full border-border bg-surface-card text-main text-sm"
                                                  required
                                                  minlength="20"
                                                  maxlength="500"
                                                  placeholder="Xác nhận kết quả kiểm tra và ghi hỏng hàng hoàn (tối thiểu 20 ký tự)..."></textarea>
                                        <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error-text border-error/40 px-4 py-2 text-xs font-bold disabled:opacity-50">
                                            <span x-show="!busy">Ghi hỏng & ghi nhận tổn thất</span>
                                            <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang ghi hỏng...</span>
                                        </button>
                                    </form>
                                @elseif ($proposal)
                                    <div class="flex h-full items-center text-xs font-medium text-warning-text">
                                        Đã ghi nhận hàng hỏng · Đang chờ Admin xử lý.
                                    </div>
                                @else
                                    <form method="POST"
                                          action="{{ route('admin.shipment-return-items.write-off-proposal', $returnItem) }}"
                                          class="space-y-3"
                                          x-data="{ busy: false }"
                                          x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                        @csrf
                                        <label class="admin-label text-main" for="writeoff-note-{{ $returnItem->id }}">Hàng hỏng — Ghi nhận chờ Admin xử lý</label>
                                        <textarea id="writeoff-note-{{ $returnItem->id }}"
                                                  name="note"
                                                  rows="3"
                                                  class="input w-full border-border bg-surface-card text-main text-sm"
                                                  required
                                                  minlength="20"
                                                  maxlength="500"
                                                  placeholder="Mô tả chi tiết tình trạng hư hỏng và chứng cứ kiểm tra (tối thiểu 20 ký tự)..."></textarea>
                                        <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error-text border-error/40 px-4 py-2 text-xs font-bold disabled:opacity-50">
                                            <span x-show="!busy">Hàng hỏng — Ghi nhận chờ Admin xử lý</span>
                                            <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang gửi...</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($canReportIncident)
                            <div class="flex justify-end">
                                <button type="button"
                                        @click="openIncident = !openIncident"
                                        class="text-xs font-semibold text-muted hover:text-error underline underline-offset-2 transition-colors">
                                    <span x-text="openIncident ? 'Ẩn báo cáo sự cố' : 'Báo cáo sự cố / bất thường'">Báo cáo sự cố / bất thường</span>
                                </button>
                            </div>
                        @endif
                    @else
                        {{-- Đã hoàn tất xử lý (Restock / WriteOff) --}}
                        <div class="rounded-xl border border-border bg-surface p-3.5 space-y-2 text-xs">
                            <p class="font-semibold text-main">
                                Đã xử lý bởi {{ $returnItem->inspectedBy?->full_name ?? '—' }}
                                lúc {{ $returnItem->inspected_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}
                            </p>
                            @if ($returnItem->events->isNotEmpty())
                                <details class="border-t border-border pt-2">
                                    <summary class="cursor-pointer font-semibold text-muted hover:text-main">
                                        Lịch sử xử lý ({{ $returnItem->events->count() }})
                                    </summary>
                                    <ol class="mt-2 space-y-1 text-muted">
                                        @foreach ($returnItem->events as $event)
                                            <li>
                                                <span class="font-semibold text-main">{{ $eventLabel($event->event_type) }}</span>
                                                · {{ $event->actor?->full_name ?? '—' }}
                                                · {{ $event->occurred_at->setTimezone($tz)->format('d/m/Y H:i') }}
                                                @if ($event->note)
                                                    — {{ $event->note }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            @endif
                        </div>
                    @endif

                    {{-- Form báo cáo sự cố (ẩn/hiện) --}}
                    @if ($canReportIncident)
                        <form x-cloak
                              x-show="openIncident"
                              x-transition
                              method="POST"
                              action="{{ route('admin.shipment-returns.incidents', $shipment) }}"
                              class="space-y-3 rounded-xl border border-error/30 bg-error-bg/40 p-3.5"
                              x-data="{ busy: false }"
                              x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                            @csrf
                            <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                            <div class="space-y-1">
                                <label class="admin-label text-main" for="incident-type-{{ $unit->id }}">Loại bất thường</label>
                                <select id="incident-type-{{ $unit->id }}" name="incident_type" class="input w-full border-border bg-surface text-main text-sm" required>
                                    @if (! $returnItem)
                                        <option value="MISSING_UNIT">Thiếu đôi trong kiện</option>
                                        <option value="SKU_MISMATCH">SKU thực nhận không khớp</option>
                                    @endif
                                    <option value="DAMAGED_UNIT">Đôi bị hư hỏng</option>
                                    <option value="NO_EVIDENCE">Thiếu chứng cứ nhận hàng</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="admin-label text-main" for="incident-note-{{ $unit->id }}">Chi tiết sự cố & chứng cứ</label>
                                <textarea id="incident-note-{{ $unit->id }}"
                                          name="note"
                                          rows="2"
                                          class="input w-full border-border bg-surface text-main text-sm"
                                          required
                                          minlength="20"
                                          maxlength="1000"
                                          placeholder="Mô tả hiện trạng thực tế, SKU nhìn thấy hoặc chứng cứ còn thiếu (tối thiểu 20 ký tự)..."></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="openIncident = false" class="btn-pill px-3 py-1.5 text-xs">Đóng</button>
                                <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error-text border-error/40 px-4 py-1.5 text-xs font-bold disabled:opacity-50">
                                    <span x-show="!busy">Lưu báo cáo bất thường</span>
                                    <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang lưu...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </article>
            @endforeach
        @endforeach
    </div>
</section>
