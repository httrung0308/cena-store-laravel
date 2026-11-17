<?php

namespace App\Services;

use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnDisposition;
use App\Enums\ShipmentStatus;
use App\Models\OperationsWorkItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\ShipmentReturnEvent;
use App\Models\ShipmentReturnItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class OperationsQueueService
{
    public const TYPE_ALL = 'all';

    public const TYPE_ORDER_PENDING = 'order_pending';

    public const TYPE_ORDER_CONFIRMED = 'order_confirmed';

    public const TYPE_ORDER_PACKING = 'order_packing';

    public const TYPE_SHIPMENT_RETURN = 'shipment_return';

    public const TYPE_QUARANTINE_INSPECTION = 'quarantine_inspection';

    public const TYPE_WRITE_OFF_APPROVAL = 'write_off_approval';

    public const TYPE_REFUND_APPROVAL = 'refund_approval';

    public const TYPE_INCIDENT = 'incident';

    /** @return list<string> */
    public static function allTypes(): array
    {
        return [
            self::TYPE_ORDER_PENDING,
            self::TYPE_ORDER_CONFIRMED,
            self::TYPE_ORDER_PACKING,
            self::TYPE_SHIPMENT_RETURN,
            self::TYPE_QUARANTINE_INSPECTION,
            self::TYPE_WRITE_OFF_APPROVAL,
            self::TYPE_REFUND_APPROVAL,
            self::TYPE_INCIDENT,
        ];
    }

    /**
     * Queue metadata for filter chips and labels.
     *
     * @param  array<string, int>|null  $counts
     * @return array<string, array{key: string, label: string, admin_only: bool, conditional?: bool}>
     */
    public function queueDefinitions(bool $isAdmin, ?array $counts = null, ?string $activeType = null): array
    {
        $hasConfirmedOrders = $counts !== null
            ? ($counts[self::TYPE_ORDER_CONFIRMED] ?? 0) > 0
            : Order::query()->where('order_status', OrderStatus::Confirmed->value)->exists();

        $definitions = [
            self::TYPE_ORDER_PENDING => [
                'key' => self::TYPE_ORDER_PENDING,
                'label' => 'Đơn chờ xác nhận',
                'admin_only' => false,
            ],
            self::TYPE_ORDER_CONFIRMED => [
                'key' => self::TYPE_ORDER_CONFIRMED,
                'label' => 'Chờ đóng gói (đơn cũ)',
                'admin_only' => false,
                'conditional' => true,
            ],
            self::TYPE_ORDER_PACKING => [
                'key' => self::TYPE_ORDER_PACKING,
                'label' => 'Đang đóng gói / Chờ giao',
                'admin_only' => false,
            ],
            self::TYPE_SHIPMENT_RETURN => [
                'key' => self::TYPE_SHIPMENT_RETURN,
                'label' => 'Nhận hàng hoàn',
                'admin_only' => false,
            ],
            self::TYPE_QUARANTINE_INSPECTION => [
                'key' => self::TYPE_QUARANTINE_INSPECTION,
                'label' => 'Kiểm tra cách ly',
                'admin_only' => false,
            ],
            self::TYPE_WRITE_OFF_APPROVAL => [
                'key' => self::TYPE_WRITE_OFF_APPROVAL,
                'label' => 'Hàng hỏng cần xử lý',
                'admin_only' => true,
            ],
            self::TYPE_REFUND_APPROVAL => [
                'key' => self::TYPE_REFUND_APPROVAL,
                'label' => 'Duyệt hoàn tiền',
                'admin_only' => true,
            ],
            self::TYPE_INCIDENT => [
                'key' => self::TYPE_INCIDENT,
                'label' => 'Sự cố & việc khác',
                'admin_only' => false,
            ],
        ];

        if (! $hasConfirmedOrders && $activeType !== self::TYPE_ORDER_CONFIRMED) {
            unset($definitions[self::TYPE_ORDER_CONFIRMED]);
        }

        if (! $isAdmin) {
            return array_filter($definitions, static fn (array $def): bool => ! $def['admin_only']);
        }

        return $definitions;
    }

    /**
     * Single source of truth for actionable queue counts across Dashboard & Work Center.
     *
     * @return array<string, int>
     */
    public function counts(bool $isAdmin): array
    {
        $orderCounts = Order::query()
            ->whereIn('order_status', [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Packing->value,
            ])
            ->selectRaw('order_status, COUNT(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        $orderPending = (int) ($orderCounts[OrderStatus::Pending->value] ?? 0);
        $orderConfirmed = (int) ($orderCounts[OrderStatus::Confirmed->value] ?? 0);
        $orderPacking = (int) ($orderCounts[OrderStatus::Packing->value] ?? 0);

        $shipmentReturn = $this->shipmentReturnBaseQuery()->count();
        $quarantineInspection = $this->quarantineInspectionBaseQuery()->count();
        $writeOffApproval = $isAdmin ? $this->writeOffApprovalBaseQuery()->count() : 0;
        $refundApproval = $isAdmin ? $this->refundApprovalBaseQuery()->count() : 0;
        $incident = $this->incidentBaseQuery($isAdmin)->count();

        $total = $orderPending
            + $orderConfirmed
            + $orderPacking
            + $shipmentReturn
            + $quarantineInspection
            + $writeOffApproval
            + $refundApproval
            + $incident;

        return [
            self::TYPE_ORDER_PENDING => $orderPending,
            self::TYPE_ORDER_CONFIRMED => $orderConfirmed,
            self::TYPE_ORDER_PACKING => $orderPacking,
            self::TYPE_SHIPMENT_RETURN => $shipmentReturn,
            self::TYPE_QUARANTINE_INSPECTION => $quarantineInspection,
            self::TYPE_WRITE_OFF_APPROVAL => $writeOffApproval,
            self::TYPE_REFUND_APPROVAL => $refundApproval,
            self::TYPE_INCIDENT => $incident,
            'total' => $total,
        ];
    }

    /**
     * Paginated active actionable queue items.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateActive(
        bool $isAdmin,
        string $type = self::TYPE_ALL,
        ?string $search = null,
        ?OperationsSourceType $sourceType = null,
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        $allowedTypes = $isAdmin
            ? self::allTypes()
            : array_values(array_filter(
                self::allTypes(),
                static fn (string $queueType): bool => ! in_array($queueType, [self::TYPE_WRITE_OFF_APPROVAL, self::TYPE_REFUND_APPROVAL], true)
            ));
        $targetTypes = ($type !== self::TYPE_ALL && $type !== '')
            ? (in_array($type, $allowedTypes, true) ? [$type] : [])
            : $allowedTypes;

        if ($sourceType !== null) {
            $targetTypes = array_values(array_filter(
                $targetTypes,
                fn (string $queueType): bool => $this->matchesSourceType($queueType, $sourceType)
            ));
        }

        $candidates = [];

        foreach ($targetTypes as $queueType) {
            foreach ($this->fetchQueueCandidates($queueType, $isAdmin, $search, $sourceType) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, function (array $a, array $b): int {
            $tierCmp = $this->priorityTier($a['type']) <=> $this->priorityTier($b['type']);
            if ($tierCmp !== 0) {
                return $tierCmp;
            }

            $sortCmp = $a['sort_at'] <=> $b['sort_at'];

            return $sortCmp !== 0 ? $sortCmp : ($a['id'] <=> $b['id']);
        });

        $total = count($candidates);
        $offset = max(0, ($page - 1) * $perPage);
        $pageSlice = array_slice($candidates, $offset, $perPage);
        $hydrated = $this->hydrateActiveSlice($pageSlice);

        return (new LengthAwarePaginator($hydrated, $total, $perPage, $page, [
            'path' => request()->url(),
        ]))->withQueryString();
    }

    /**
     * Paginated completed OperationsWorkItem records.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateCompleted(
        bool $isAdmin,
        string $type = self::TYPE_ALL,
        ?string $search = null,
        ?OperationsSourceType $sourceType = null,
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        $query = OperationsWorkItem::query()
            ->where('status', OperationsWorkItemStatus::Completed->value);

        if (! $isAdmin) {
            $query->where('source_type', '!=', OperationsSourceType::Payment->value)
                ->where('source_event_key', 'not like', '%:write-off-approval');
        }

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType->value);
        }

        if ($type !== self::TYPE_ALL && $type !== '') {
            $this->applyCompletedTypeFilter($query, $type);
        }

        $this->applyWorkItemSearch($query, $search);

        $paginator = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->through(fn (OperationsWorkItem $item): array => $this->formatWorkItem($item));

        return $paginator;
    }

    /**
     * @return list<array{type: string, id: int, sort_at: string}>
     */
    private function fetchQueueCandidates(string $queueType, bool $isAdmin, ?string $search, ?OperationsSourceType $sourceType): array
    {
        return match ($queueType) {
            self::TYPE_ORDER_PENDING => $this->orderCandidates(OrderStatus::Pending, self::TYPE_ORDER_PENDING, $search),
            self::TYPE_ORDER_CONFIRMED => $this->orderCandidates(OrderStatus::Confirmed, self::TYPE_ORDER_CONFIRMED, $search),
            self::TYPE_ORDER_PACKING => $this->orderCandidates(OrderStatus::Packing, self::TYPE_ORDER_PACKING, $search),
            self::TYPE_SHIPMENT_RETURN => $this->shipmentReturnCandidates($search),
            self::TYPE_QUARANTINE_INSPECTION => $this->returnItemCandidates(false, self::TYPE_QUARANTINE_INSPECTION, $search),
            self::TYPE_WRITE_OFF_APPROVAL => $isAdmin ? $this->returnItemCandidates(true, self::TYPE_WRITE_OFF_APPROVAL, $search) : [],
            self::TYPE_REFUND_APPROVAL => $isAdmin ? $this->refundCandidates($search) : [],
            self::TYPE_INCIDENT => $this->incidentCandidates($isAdmin, $search, $sourceType),
            default => [],
        };
    }

    /**
     * @return list<array{type: string, id: int, sort_at: string}>
     */
    private function orderCandidates(OrderStatus $status, string $queueType, ?string $search): array
    {
        $query = DB::table('orders')->where('order_status', $status->value);

        if ($search !== null && trim($search) !== '') {
            $pattern = $this->likePattern($search);
            $query->where(function (QueryBuilder $inner) use ($pattern): void {
                $inner->whereRaw("orders.order_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("orders.shipping_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("orders.shipping_phone LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereExists(function (QueryBuilder $items) use ($pattern): void {
                        $items->selectRaw('1')
                            ->from('order_items')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->where(function (QueryBuilder $itemMatch) use ($pattern): void {
                                $itemMatch->whereRaw("order_items.sku_snapshot LIKE ? ESCAPE '!'", [$pattern])
                                    ->orWhereRaw("order_items.product_name_snapshot LIKE ? ESCAPE '!'", [$pattern]);
                            });
                    });
            });
        }

        $dateColumn = match ($status) {
            OrderStatus::Confirmed => 'COALESCE(orders.confirmed_at, orders.created_at)',
            OrderStatus::Packing => 'COALESCE(orders.packed_at, orders.updated_at, orders.created_at)',
            default => 'orders.created_at',
        };

        return $query
            ->selectRaw("orders.id as id, {$dateColumn} as sort_at")
            ->get()
            ->map(static fn (object $row): array => [
                'type' => $queueType,
                'id' => (int) $row->id,
                'sort_at' => (string) $row->sort_at,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, sort_at: string}>
     */
    private function shipmentReturnCandidates(?string $search): array
    {
        $query = $this->shipmentReturnBaseQuery();

        if ($search !== null && trim($search) !== '') {
            $pattern = $this->likePattern($search);
            $query->where(function (QueryBuilder $inner) use ($pattern): void {
                $inner->whereRaw("orders.order_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipments.tracking_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipments.carrier_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereExists(function (QueryBuilder $items) use ($pattern): void {
                        $items->selectRaw('1')
                            ->from('order_items')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->whereRaw("order_items.sku_snapshot LIKE ? ESCAPE '!'", [$pattern]);
                    });
            });
        }

        return $query
            ->selectRaw('shipments.id as id, COALESCE(shipments.failed_at, shipments.updated_at, shipments.created_at) as sort_at')
            ->get()
            ->map(static fn (object $row): array => [
                'type' => self::TYPE_SHIPMENT_RETURN,
                'id' => (int) $row->id,
                'sort_at' => (string) $row->sort_at,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, sort_at: string}>
     */
    private function returnItemCandidates(bool $withWriteOffProposal, string $queueType, ?string $search): array
    {
        $query = $withWriteOffProposal
            ? $this->writeOffApprovalBaseQuery()
            : $this->quarantineInspectionBaseQuery();

        if ($search !== null && trim($search) !== '') {
            $pattern = $this->likePattern($search);
            $query->where(function (QueryBuilder $inner) use ($pattern): void {
                $inner->whereRaw("orders.order_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipments.tracking_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("product_variants.sku LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query
            ->selectRaw('shipment_return_items.id as id, COALESCE(shipment_return_items.received_at, shipment_return_items.created_at) as sort_at')
            ->get()
            ->map(static fn (object $row): array => [
                'type' => $queueType,
                'id' => (int) $row->id,
                'sort_at' => (string) $row->sort_at,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, sort_at: string}>
     */
    private function refundCandidates(?string $search): array
    {
        $query = $this->refundApprovalBaseQuery();

        if ($search !== null && trim($search) !== '') {
            $pattern = $this->likePattern($search);
            $query->where(function (QueryBuilder $inner) use ($pattern): void {
                $inner->whereRaw("orders.order_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("orders.shipping_name LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query
            ->selectRaw('refunds.id as id, refunds.created_at as sort_at')
            ->get()
            ->map(static fn (object $row): array => [
                'type' => self::TYPE_REFUND_APPROVAL,
                'id' => (int) $row->id,
                'sort_at' => (string) $row->sort_at,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, sort_at: string}>
     */
    private function incidentCandidates(bool $isAdmin, ?string $search, ?OperationsSourceType $sourceType): array
    {
        $query = $this->incidentBaseQuery($isAdmin);

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType->value);
        }

        $this->applyWorkItemSearch($query, $search);

        return $query
            ->select(['id', 'occurred_at'])
            ->get()
            ->map(static fn (OperationsWorkItem $item): array => [
                'type' => self::TYPE_INCIDENT,
                'id' => $item->id,
                'sort_at' => $item->getRawOriginal('occurred_at') ?? $item->occurred_at->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @param  list<array{type: string, id: int, sort_at: string}>  $slice
     * @return list<array<string, mixed>>
     */
    private function hydrateActiveSlice(array $slice): array
    {
        $idsByType = [];
        foreach ($slice as $entry) {
            $idsByType[$entry['type']][] = $entry['id'];
        }

        $orderIds = array_merge(
            $idsByType[self::TYPE_ORDER_PENDING] ?? [],
            $idsByType[self::TYPE_ORDER_CONFIRMED] ?? [],
            $idsByType[self::TYPE_ORDER_PACKING] ?? []
        );
        $orders = $orderIds === []
            ? collect()
            : Order::query()->with('items')->whereIn('id', $orderIds)->get()->keyBy('id');

        $shipmentIds = $idsByType[self::TYPE_SHIPMENT_RETURN] ?? [];
        $shipments = $shipmentIds === []
            ? collect()
            : Shipment::query()->with('order')->whereIn('id', $shipmentIds)->get()->keyBy('id');

        $returnItemIds = array_merge(
            $idsByType[self::TYPE_QUARANTINE_INSPECTION] ?? [],
            $idsByType[self::TYPE_WRITE_OFF_APPROVAL] ?? []
        );
        $returnItems = $returnItemIds === []
            ? collect()
            : ShipmentReturnItem::query()->with(['shipment.order', 'variant'])->whereIn('id', $returnItemIds)->get()->keyBy('id');

        $refundIds = $idsByType[self::TYPE_REFUND_APPROVAL] ?? [];
        $refunds = $refundIds === []
            ? collect()
            : Refund::query()->with('order')->whereIn('id', $refundIds)->get()->keyBy('id');

        $workItemIds = $idsByType[self::TYPE_INCIDENT] ?? [];
        $workItems = $workItemIds === []
            ? collect()
            : OperationsWorkItem::query()->whereIn('id', $workItemIds)->get()->keyBy('id');

        $formatted = [];
        foreach ($slice as $entry) {
            $type = $entry['type'];
            $id = $entry['id'];

            $row = match ($type) {
                self::TYPE_ORDER_PENDING, self::TYPE_ORDER_CONFIRMED, self::TYPE_ORDER_PACKING => $orders->has($id)
                    ? $this->formatOrder($orders->get($id), $type)
                    : null,
                self::TYPE_SHIPMENT_RETURN => $shipments->has($id)
                    ? $this->formatShipmentReturn($shipments->get($id))
                    : null,
                self::TYPE_QUARANTINE_INSPECTION, self::TYPE_WRITE_OFF_APPROVAL => $returnItems->has($id)
                    ? $this->formatReturnItem($returnItems->get($id), $type)
                    : null,
                self::TYPE_REFUND_APPROVAL => $refunds->has($id)
                    ? $this->formatRefund($refunds->get($id))
                    : null,
                self::TYPE_INCIDENT => $workItems->has($id)
                    ? $this->formatWorkItem($workItems->get($id))
                    : null,
                default => null,
            };

            if ($row !== null) {
                $formatted[] = $row;
            }
        }

        return $formatted;
    }

    /** @return array<string, mixed> */
    private function formatOrder(Order $order, string $type): array
    {
        $totalPairs = (int) $order->items->sum('quantity');
        $typeMeta = match ($type) {
            self::TYPE_ORDER_PENDING => [
                'type_label' => 'Đơn chờ xác nhận',
                'type_badge_class' => 'bg-warning-bg text-warning-text border-warning/30',
                'status_label' => $order->order_status->label(),
                'status_badge_class' => 'bg-warning-bg text-warning-text border-warning/30',
                'action_title' => 'Xác nhận đơn hàng',
                'title' => "Xác nhận đơn hàng {$order->order_code}",
                'occurred_at' => $order->created_at,
            ],
            self::TYPE_ORDER_CONFIRMED => [
                'type_label' => 'Chờ đóng gói (đơn cũ)',
                'type_badge_class' => 'bg-accent/10 text-accent border-accent/30',
                'status_label' => $order->order_status->label(),
                'status_badge_class' => 'bg-accent/10 text-accent border-accent/30',
                'action_title' => 'Đóng gói đơn cũ',
                'title' => "Đóng gói đơn cũ {$order->order_code}",
                'occurred_at' => $order->confirmed_at ?? $order->created_at,
            ],
            default => [
                'type_label' => 'Đang đóng gói / Chờ giao',
                'type_badge_class' => 'bg-accent/10 text-accent border-accent/30',
                'status_label' => $order->order_status->label(),
                'status_badge_class' => 'bg-accent/10 text-accent border-accent/30',
                'action_title' => 'Xuất kho giao đơn',
                'title' => "Xuất kho giao đơn {$order->order_code}",
                'occurred_at' => $order->packed_at ?? $order->updated_at ?? $order->created_at,
            ],
        };

        $relatedInfo = "{$order->shipping_name} · {$order->shipping_phone} · {$totalPairs} đôi · ".number_format($order->total_amount, 0, ',', '.').' ₫';

        return [
            'queue_type' => $type,
            'type_label' => $typeMeta['type_label'],
            'type_badge_class' => $typeMeta['type_badge_class'],
            'status_label' => $typeMeta['status_label'],
            'status_badge_class' => $typeMeta['status_badge_class'],
            'action_title' => $typeMeta['action_title'],
            'title' => $typeMeta['title'],
            'related_info' => $relatedInfo,
            'description' => $relatedInfo,
            'reference' => $order->order_code,
            'reference_sub' => $order->shipping_name,
            'occurred_at' => $this->toCarbonImmutable($typeMeta['occurred_at']),
            'action_label' => 'Xem đơn hàng',
            'action_url' => route('admin.orders.show', $order->id),
        ];
    }

    /** @return array<string, mixed> */
    private function formatShipmentReturn(Shipment $shipment): array
    {
        $orderCode = $shipment->order?->order_code ?? "#{$shipment->order_id}";
        $tracking = $shipment->tracking_code ?: "Kiện #{$shipment->id}";
        $customer = trim(implode(' · ', array_filter([
            $shipment->order?->shipping_name,
            $shipment->order?->shipping_phone,
        ])));
        $reason = $shipment->note ?: $shipment->order?->cancel_reason ?: 'Giao thất bại, chờ nhận lại hàng hoàn.';
        $relatedInfo = trim(implode(' · ', array_filter([$customer, $tracking, $reason])));

        return [
            'queue_type' => self::TYPE_SHIPMENT_RETURN,
            'type_label' => 'Nhận hàng hoàn',
            'type_badge_class' => 'bg-warning-bg text-warning-text border-warning/30',
            'status_label' => $shipment->status->label(),
            'status_badge_class' => 'bg-warning-bg text-warning-text border-warning/30',
            'action_title' => 'Nhận hàng hoàn',
            'title' => "Nhận hàng hoàn đơn {$orderCode}",
            'related_info' => $relatedInfo,
            'description' => "{$tracking} · {$reason}",
            'reference' => $orderCode,
            'reference_sub' => $tracking,
            'occurred_at' => $this->toCarbonImmutable($shipment->failed_at ?? $shipment->updated_at ?? $shipment->created_at),
            'action_label' => 'Nhận hàng hoàn',
            'action_url' => route('admin.orders.show', $shipment->order_id).'#xu-ly-hang-hoan',
        ];
    }

    /** @return array<string, mixed> */
    private function formatReturnItem(ShipmentReturnItem $item, string $type): array
    {
        $orderCode = $item->shipment?->order?->order_code ?? "#{$item->shipment_id}";
        $sku = $item->variant?->sku ?? "Biến thể #{$item->variant_id}";
        $isWriteOff = $type === self::TYPE_WRITE_OFF_APPROVAL;
        $relatedInfo = "SKU: {$sku} · Kiện #{$item->shipment_id}";

        return [
            'queue_type' => $type,
            'type_label' => $isWriteOff ? 'Hàng hỏng cần xử lý' : 'Kiểm tra cách ly',
            'type_badge_class' => $isWriteOff ? 'bg-error-bg text-error border-error/30' : 'bg-accent/10 text-accent border-accent/30',
            'status_label' => $isWriteOff ? 'Hàng hỏng cần xử lý' : 'Chờ kiểm tra',
            'status_badge_class' => $isWriteOff ? 'bg-error-bg text-error border-error/30' : 'bg-warning-bg text-warning-text border-warning/30',
            'action_title' => $isWriteOff ? 'Ghi hỏng' : 'Kiểm tra hàng cách ly',
            'title' => $isWriteOff
                ? "Ghi hỏng hàng hoàn đơn {$orderCode}"
                : "Kiểm tra hàng hoàn cách ly đơn {$orderCode}",
            'related_info' => $relatedInfo,
            'description' => $relatedInfo,
            'reference' => $orderCode,
            'reference_sub' => "Kiện #{$item->shipment_id}",
            'occurred_at' => $this->toCarbonImmutable($item->received_at ?? $item->created_at),
            'action_label' => $isWriteOff ? 'Ghi hỏng' : 'Kiểm tra cách ly',
            'action_url' => route('admin.orders.show', $item->shipment->order_id).'#xu-ly-hang-hoan',
        ];
    }

    /** @return array<string, mixed> */
    private function formatRefund(Refund $refund): array
    {
        $orderCode = $refund->order?->order_code ?? "#{$refund->order_id}";
        $relatedInfo = trim(implode(' · ', array_filter([
            $refund->order?->shipping_name,
            $refund->order?->shipping_phone,
            number_format($refund->amount, 0, ',', '.').' ₫',
        ])));

        return [
            'queue_type' => self::TYPE_REFUND_APPROVAL,
            'type_label' => 'Duyệt hoàn tiền',
            'type_badge_class' => 'bg-error-bg text-error border-error/30',
            'status_label' => $refund->status->label(),
            'status_badge_class' => 'bg-error-bg text-error border-error/30',
            'action_title' => 'Duyệt hoàn tiền',
            'title' => "Duyệt hoàn tiền đơn {$orderCode}",
            'related_info' => $relatedInfo,
            'description' => number_format($refund->amount, 0, ',', '.').' ₫ · '.($refund->order?->shipping_name ?? ''),
            'reference' => $orderCode,
            'reference_sub' => number_format($refund->amount, 0, ',', '.').' ₫',
            'occurred_at' => $this->toCarbonImmutable($refund->created_at),
            'action_label' => 'Duyệt hoàn tiền',
            'action_url' => route('admin.orders.show', $refund->order_id),
        ];
    }

    /** @return array<string, mixed> */
    private function formatWorkItem(OperationsWorkItem $item): array
    {
        $statusClass = match ($item->status) {
            OperationsWorkItemStatus::Completed => 'bg-success-bg text-success border-success/30',
            OperationsWorkItemStatus::InProgress => 'bg-accent/10 text-accent border-accent/30',
            OperationsWorkItemStatus::Pending => 'bg-warning-bg text-warning-text border-warning/30',
        };

        return [
            'queue_type' => self::TYPE_INCIDENT,
            'type_label' => $item->source_label ?: 'Sự cố & việc khác',
            'type_badge_class' => 'bg-surface text-main border-border',
            'status_label' => $item->status->label(),
            'status_badge_class' => $statusClass,
            'action_title' => $item->title,
            'title' => $item->title,
            'related_info' => $item->content,
            'description' => $item->content,
            'reference' => $item->source_reference,
            'reference_sub' => $item->source_type->label(),
            'occurred_at' => $this->toCarbonImmutable($item->occurred_at),
            'action_label' => 'Xem chi tiết',
            'action_url' => route('admin.operations-work-items.show', $item->id),
        ];
    }

    private function shipmentReturnBaseQuery(): QueryBuilder
    {
        return DB::table('shipments')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->where('shipments.status', ShipmentStatus::Returning->value)
            ->where('orders.order_status', OrderStatus::Failed->value);
    }

    private function quarantineInspectionBaseQuery(): QueryBuilder
    {
        return DB::table('shipment_return_items')
            ->join('shipments', 'shipments.id', '=', 'shipment_return_items.shipment_id')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'shipment_return_items.variant_id')
            ->where('shipment_return_items.disposition', ReturnDisposition::Pending->value)
            ->whereNotExists(function (QueryBuilder $events): void {
                $events->selectRaw('1')
                    ->from('shipment_return_events')
                    ->whereColumn('shipment_return_events.shipment_return_item_id', 'shipment_return_items.id')
                    ->where('shipment_return_events.event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED);
            });
    }

    private function writeOffApprovalBaseQuery(): QueryBuilder
    {
        return DB::table('shipment_return_items')
            ->join('shipments', 'shipments.id', '=', 'shipment_return_items.shipment_id')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'shipment_return_items.variant_id')
            ->where('shipment_return_items.disposition', ReturnDisposition::Pending->value)
            ->whereExists(function (QueryBuilder $events): void {
                $events->selectRaw('1')
                    ->from('shipment_return_events')
                    ->whereColumn('shipment_return_events.shipment_return_item_id', 'shipment_return_items.id')
                    ->where('shipment_return_events.event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED);
            });
    }

    private function refundApprovalBaseQuery(): QueryBuilder
    {
        return DB::table('refunds')
            ->join('orders', 'orders.id', '=', 'refunds.order_id')
            ->where('refunds.status', RefundStatus::Requested->value);
    }

    /** @return Builder<OperationsWorkItem> */
    private function incidentBaseQuery(bool $isAdmin): Builder
    {
        $query = OperationsWorkItem::query()
            ->whereIn('status', [
                OperationsWorkItemStatus::Pending->value,
                OperationsWorkItemStatus::InProgress->value,
            ])
            ->where('source_event_key', 'not like', 'shipment:%:failed-return')
            ->where('source_event_key', 'not like', 'shipment-return:%:write-off-approval')
            ->where('source_event_key', 'not like', 'refund:%:approval');

        if (! $isAdmin) {
            $query->where('source_type', '!=', OperationsSourceType::Payment->value);
        }

        return $query;
    }

    /** @param Builder<OperationsWorkItem> $query */
    private function applyWorkItemSearch(Builder $query, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }
        $pattern = $this->likePattern($search);
        $query->where(function (Builder $inner) use ($pattern): void {
            $inner->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("content LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("source_reference LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("source_label LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    /** @param Builder<OperationsWorkItem> $query */
    private function applyCompletedTypeFilter(Builder $query, string $type): void
    {
        match ($type) {
            self::TYPE_SHIPMENT_RETURN => $query->where('source_event_key', 'like', 'shipment:%:failed-return'),
            self::TYPE_WRITE_OFF_APPROVAL => $query->where('source_event_key', 'like', 'shipment-return:%:write-off-approval'),
            self::TYPE_REFUND_APPROVAL => $query->where('source_event_key', 'like', 'refund:%:approval'),
            self::TYPE_INCIDENT => $query->where('source_event_key', 'not like', 'shipment:%:failed-return')
                ->where('source_event_key', 'not like', 'shipment-return:%:write-off-approval')
                ->where('source_event_key', 'not like', 'refund:%:approval'),
            self::TYPE_ORDER_PENDING, self::TYPE_ORDER_CONFIRMED, self::TYPE_ORDER_PACKING => $query->where('source_type', OperationsSourceType::Order->value),
            self::TYPE_QUARANTINE_INSPECTION => $query->where('source_type', OperationsSourceType::Inventory->value)
                ->where('source_event_key', 'not like', 'shipment-return:%:write-off-approval'),
            default => null,
        };
    }

    private function matchesSourceType(string $queueType, OperationsSourceType $sourceType): bool
    {
        return match ($queueType) {
            self::TYPE_ORDER_PENDING, self::TYPE_ORDER_CONFIRMED, self::TYPE_ORDER_PACKING => $sourceType === OperationsSourceType::Order,
            self::TYPE_SHIPMENT_RETURN => $sourceType === OperationsSourceType::Shipment,
            self::TYPE_QUARANTINE_INSPECTION, self::TYPE_WRITE_OFF_APPROVAL => $sourceType === OperationsSourceType::Inventory,
            self::TYPE_REFUND_APPROVAL => $sourceType === OperationsSourceType::Payment,
            self::TYPE_INCIDENT => true,
            default => false,
        };
    }

    private function priorityTier(string $type): int
    {
        return match ($type) {
            self::TYPE_REFUND_APPROVAL => 1,
            self::TYPE_WRITE_OFF_APPROVAL => 2,
            self::TYPE_ORDER_PENDING => 3,
            self::TYPE_ORDER_CONFIRMED => 4,
            self::TYPE_ORDER_PACKING => 5,
            self::TYPE_SHIPMENT_RETURN => 6,
            self::TYPE_QUARANTINE_INSPECTION => 7,
            self::TYPE_INCIDENT => 8,
            default => 99,
        };
    }

    private function toCarbonImmutable(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function likePattern(string $search): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search)).'%';
    }
}
