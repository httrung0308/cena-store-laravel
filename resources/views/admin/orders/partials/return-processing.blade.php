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
        $progressBadge = ['label' => 'Chờ duyệt ghi hỏng', 'class' => 'bg-error-bg text-error-text border border-error/30'];
    } elseif ($pendingItems->isNotEmpty()) {
        $progressBadge = ['label' => 'Chờ kiểm tra', 'class' => 'bg-warning-bg text-warning-text border border-warning/30'];
    } else {
        $progressBadge = ['label' => 'Đã xử lý', 'class' => 'bg-success-bg text-success-text border border-success/30'];
    }

    $eventLabel = static fn (string $type): string => match ($type) {
        ShipmentReturnEvent::RECEIVED => 'Đã nhận vào kho cách ly',
        ShipmentReturnEvent::RESTOCKED => 'Đã nhập lại kho bán',
        ShipmentReturnEvent::WRITE_OFF_PROPOSED => 'Đề xuất ghi hỏng',
        ShipmentReturnEvent::WRITE_OFF_APPROVED => 'Đã duyệt ghi hỏng',
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
                    text="Quy trình gồm 2 bước: Bước 1 xác nhận nhận từng đôi từ đơn vị vận chuyển vào kho cách ly; Bước 2 kiểm tra tình trạng để nhập lại kho bán hoặc đề xuất/duyệt ghi hỏng."
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

    {{-- Bước 1: Nhận hàng hoàn --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-main">Bước 1 — Nhận hàng hoàn</h3>
                <p class="text-xs text-muted">Đối chiếu từng sản phẩm trong kiện hoàn trước khi đưa vào khu cách ly.</p>
            </div>
        </div>

        <div class="space-y-3">
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
                    @endphp
                    <article class="rounded-2xl border border-border bg-surface-card p-4 space-y-3" x-data="{ openIncident: false }">
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
                                    <h4 class="font-semibold text-main break-words">{{ $item->product_name_snapshot }}</h4>
                                    <p class="text-xs text-main">{{ $item->color_name_snapshot }} · Size {{ $item->size_value_snapshot }}</p>
                                    <p class="text-[11px] font-mono text-muted">SKU {{ $item->sku_snapshot }} · Đôi #{{ $unit->unit_no }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col items-start sm:items-end gap-1 shrink-0 text-xs">
                                <span class="inline-flex items-center rounded-full border border-border bg-surface px-2.5 py-0.5 text-[11px] font-medium text-muted">
                                    Trước khi hoàn: {{ $unit->state->label() }}
                                </span>
                                @if ($returnItem)
                                    <span class="inline-flex items-center rounded-full border border-success/30 bg-success-bg px-2.5 py-0.5 text-[11px] font-semibold text-success-text">
                                        Đã nhận về cách ly
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-warning/30 bg-warning-bg px-2.5 py-0.5 text-[11px] font-semibold text-warning-text">
                                        Chưa nhận hàng hoàn
                                    </span>
                                @endif
                            </div>
                        </div>

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

                        @if ($returnItem)
                            <div class="rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs text-muted">
                                Nhận bởi <span class="font-semibold text-main">{{ $returnItem->receivedBy?->full_name ?? '—' }}</span>
                                lúc <span class="font-medium text-main">{{ $returnItem->received_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                                @if ($returnItem->note)
                                    · {{ $returnItem->note }}
                                @endif
                            </div>
                        @elseif ($canReceive)
                            <form method="POST"
                                  action="{{ route('admin.shipment-returns.receive', $shipment) }}"
                                  class="space-y-3 border-t border-border pt-3"
                                  x-data="{ busy: false }"
                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                @csrf
                                <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                <div class="space-y-1">
                                    <label class="admin-label text-muted" for="receive-note-{{ $unit->id }}">Ghi chú tình trạng khi nhận</label>
                                    <textarea id="receive-note-{{ $unit->id }}"
                                              name="note"
                                              rows="2"
                                              class="input w-full border-border bg-surface text-main text-sm"
                                              required
                                              minlength="5"
                                              maxlength="500"
                                              placeholder="Mô tả tình trạng hộp/giày thực tế khi nhận hoàn (tối thiểu 5 ký tự)...">{{ old('unit_id') == $unit->id ? old('note') : '' }}</textarea>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <button type="submit" :disabled="busy" class="btn-pill-dark px-4 py-2 text-xs disabled:opacity-50">
                                        <span x-show="!busy">Xác nhận đã nhận</span>
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
                        @else
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
                        @endif

                        @if ($canReportIncident)
                            @if ($returnItem)
                                <div class="flex justify-end">
                                    <button type="button"
                                            @click="openIncident = !openIncident"
                                            class="text-xs font-semibold text-muted hover:text-error underline underline-offset-2 transition-colors">
                                        <span x-text="openIncident ? 'Ẩn báo cáo sự cố' : 'Báo cáo sự cố / bất thường'">Báo cáo sự cố / bất thường</span>
                                    </button>
                                </div>
                            @endif
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
    </div>

    {{-- Bước 2: Kiểm tra & xử lý hàng cách ly --}}
    <div class="space-y-4 border-t border-border pt-6">
        <div>
            <h3 class="text-base font-semibold text-main">Bước 2 — Kiểm tra & xử lý hàng cách ly</h3>
            <p class="text-xs text-muted">Đánh giá các đôi đã nhận để nhập lại kho bán hoặc đề xuất/phê duyệt ghi hỏng.</p>
        </div>

        @if ($returnItems->isEmpty())
            <div class="rounded-2xl border border-dashed border-border bg-surface p-5 text-center text-xs text-muted">
                Chưa có sản phẩm nào được nhận vào kho cách ly. Hoàn tất Bước 1 để tiếp tục kiểm tra.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($returnItems as $returnItem)
                    @php
                        $unit = $returnItem->unit;
                        $item = $unit?->item;
                        $thumbnailUrl = $item?->variant?->color?->thumbnail?->thumbnailUrl(160);
                        $proposal = $returnItem->events->firstWhere('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED);
                    @endphp
                    <article class="rounded-2xl border border-border bg-surface-card p-4 space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3 min-w-0">
                                @if ($thumbnailUrl)
                                    <img src="{{ $thumbnailUrl }}"
                                         alt="{{ $item?->product_name_snapshot ?? 'Sản phẩm hoàn' }}"
                                         class="h-12 w-12 shrink-0 rounded-xl border border-border object-cover bg-surface">
                                @else
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-border bg-surface text-[10px] font-bold uppercase text-muted">
                                        Giày
                                    </div>
                                @endif
                                <div class="min-w-0 space-y-0.5">
                                    <h4 class="font-semibold text-main break-words">{{ $item?->product_name_snapshot ?? 'Sản phẩm hoàn' }}</h4>
                                    <p class="text-xs text-muted">
                                        {{ $item?->color_name_snapshot ?? '—' }} · Size {{ $item?->size_value_snapshot ?? '—' }}
                                        · <span class="font-mono">SKU {{ $item?->sku_snapshot ?? '—' }} · Đôi #{{ $unit?->unit_no ?? '—' }}</span>
                                    </p>
                                    <p class="text-xs text-muted">
                                        Nhận bởi <span class="font-medium text-main">{{ $returnItem->receivedBy?->full_name ?? '—' }}</span>
                                        lúc {{ $returnItem->received_at->setTimezone($tz)->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            </div>

                            @if ($returnItem->disposition === ReturnDisposition::Pending)
                                <span class="inline-flex items-center self-start rounded-full border border-warning/30 bg-warning-bg px-2.5 py-0.5 text-xs font-semibold text-warning-text">
                                    {{ $proposal ? 'Chờ duyệt ghi hỏng' : 'Chờ kiểm tra' }}
                                </span>
                            @elseif ($returnItem->disposition === ReturnDisposition::Restock)
                                <span class="inline-flex items-center self-start rounded-full border border-success/30 bg-success-bg px-2.5 py-0.5 text-xs font-semibold text-success-text">
                                    {{ $returnItem->disposition->label() }}
                                </span>
                            @else
                                <span class="inline-flex items-center self-start rounded-full border border-error/30 bg-error-bg px-2.5 py-0.5 text-xs font-semibold text-error-text">
                                    {{ $returnItem->disposition->label() }}
                                </span>
                            @endif
                        </div>

                        @if ($returnItem->disposition === ReturnDisposition::Pending)
                            @if ($proposal)
                                <div class="rounded-xl border border-warning/30 bg-warning-bg p-3 text-xs text-main space-y-1">
                                    <p class="font-semibold text-warning-text">
                                        Đề xuất ghi hỏng bởi {{ $proposal->actor?->full_name ?? '—' }} · {{ $proposal->occurred_at->setTimezone($tz)->format('d/m/Y H:i') }}
                                    </p>
                                    <p class="whitespace-pre-wrap">{{ $proposal->note }}</p>
                                </div>
                            @endif

                            <div class="grid gap-4 border-t border-border pt-4 lg:grid-cols-2">
                                {{-- Nhập lại kho bán: cả Admin và Staff --}}
                                <form method="POST"
                                      action="{{ route('admin.shipment-return-items.restock', $returnItem) }}"
                                      class="space-y-3 rounded-xl border border-border bg-surface p-3.5"
                                      x-data="{ busy: false }"
                                      x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                    @csrf
                                    <label class="admin-label text-main" for="restock-note-{{ $returnItem->id }}">Kiểm tra đạt — Nhập lại kho bán</label>
                                    <textarea id="restock-note-{{ $returnItem->id }}"
                                              name="note"
                                              rows="3"
                                              class="input w-full border-border bg-surface-card text-main text-sm"
                                              required
                                              minlength="20"
                                              maxlength="500"
                                              placeholder="Mô tả kết quả kiểm tra ngoại quan, hộp và lý do đủ điều kiện bán lại (tối thiểu 20 ký tự)..."></textarea>
                                    <button type="submit" :disabled="busy" class="btn-pill-dark px-4 py-2 text-xs disabled:opacity-50">
                                        <span x-show="!busy">Nhập lại kho bán</span>
                                        <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang nhập kho...</span>
                                    </button>
                                </form>

                                {{-- Ghi hỏng: phân quyền Staff đề xuất / Admin duyệt --}}
                                <div class="rounded-xl border border-border bg-surface p-3.5 space-y-3">
                                    @if ($proposal)
                                        @if ($isAdmin)
                                            <form method="POST"
                                                  action="{{ route('admin.shipment-return-items.write-off-approval', $returnItem) }}"
                                                  class="space-y-3"
                                                  x-data="{ busy: false }"
                                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                                @csrf
                                                <label class="admin-label text-main" for="approve-note-{{ $returnItem->id }}">Phê duyệt ghi hỏng (Admin)</label>
                                                <textarea id="approve-note-{{ $returnItem->id }}"
                                                          name="note"
                                                          rows="3"
                                                          class="input w-full border-border bg-surface-card text-main text-sm"
                                                          required
                                                          minlength="20"
                                                          maxlength="500"
                                                          placeholder="Xác nhận kết quả xem xét và phê duyệt ghi hỏng (tối thiểu 20 ký tự)..."></textarea>
                                                <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error-text border-error/40 px-4 py-2 text-xs font-bold disabled:opacity-50">
                                                    <span x-show="!busy">Duyệt ghi hỏng</span>
                                                    <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang duyệt...</span>
                                                </button>
                                            </form>
                                        @else
                                            <div class="flex h-full items-center text-xs font-medium text-warning-text">
                                                Đã đề xuất ghi hỏng · Đang chờ Admin phê duyệt.
                                            </div>
                                        @endif
                                    @else
                                        @if (! $isAdmin)
                                            <form method="POST"
                                                  action="{{ route('admin.shipment-return-items.write-off-proposal', $returnItem) }}"
                                                  class="space-y-3"
                                                  x-data="{ busy: false }"
                                                  x-on:submit="if (busy) { $event.preventDefault(); return false; } busy = true">
                                                @csrf
                                                <label class="admin-label text-main" for="writeoff-note-{{ $returnItem->id }}">Hàng hỏng — Đề xuất ghi hỏng</label>
                                                <textarea id="writeoff-note-{{ $returnItem->id }}"
                                                          name="note"
                                                          rows="3"
                                                          class="input w-full border-border bg-surface-card text-main text-sm"
                                                          required
                                                          minlength="20"
                                                          maxlength="500"
                                                          placeholder="Mô tả chi tiết tình trạng hư hỏng và chứng cứ kiểm tra (tối thiểu 20 ký tự)..."></textarea>
                                                <button type="submit" :disabled="busy" class="btn-pill bg-error-bg text-error-text border-error/40 px-4 py-2 text-xs font-bold disabled:opacity-50">
                                                    <span x-show="!busy">Đề xuất ghi hỏng</span>
                                                    <span x-cloak x-show="busy" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang gửi...</span>
                                                </button>
                                            </form>
                                        @else
                                            <div class="flex h-full items-center text-xs leading-relaxed text-muted">
                                                Nếu hàng hỏng không thể bán lại, Nhân viên (Staff) cần lập đề xuất ghi hỏng trước để Admin phê duyệt.
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-border bg-surface p-3.5 space-y-2 text-xs">
                                <p class="font-semibold text-main">
                                    Đã xử lý bởi {{ $returnItem->inspectedBy?->full_name ?? '—' }}
                                    lúc {{ $returnItem->inspected_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}
                                </p>
                                @if ($returnItem->events->isNotEmpty())
                                    <ol class="space-y-1 border-t border-border pt-2 text-muted">
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
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
