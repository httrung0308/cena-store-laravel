<?php

namespace App\Services;

use App\Enums\InventoryReferenceType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\ShipmentReturnItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class InventoryHistoryQueryService
{
    /** @return Collection<int, ProductColor> */
    public function productColorsWithVariants(Product $product, bool $includeCost = false): Collection
    {
        $colors = ProductColor::withTrashed()
            ->where('product_id', $product->id)
            ->with(['variants' => function ($query) use ($includeCost): void {
                $query->withTrashed();
                if (! $includeCost) {
                    $query->select([
                        'id', 'product_color_id', 'size_value', 'size_numeric', 'sku',
                        'price_override', 'stock_qty', 'reserved_qty', 'quarantine_qty',
                        'is_active', 'created_at', 'updated_at', 'deleted_at',
                    ]);
                }
                $query->orderBy('size_numeric')->orderBy('id');
            }])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        if (! $includeCost) {
            $colors->each(fn (ProductColor $color) => $color->variants->each->makeHidden('average_cost'));
        }

        return $colors;
    }

    /** @param array<string, mixed> $filters */
    public function movements(ProductVariant $variant, array $filters, bool $includeCost = false): LengthAwarePaginator
    {
        $query = InventoryMovement::query()
            ->with('actor:id,full_name')
            ->where('variant_id', $variant->id);

        if (! $includeCost) {
            $query->select([
                'id', 'variant_id', 'action', 'reference_type', 'reference_id',
                'stock_before', 'stock_delta', 'stock_after',
                'reserved_before', 'reserved_delta', 'reserved_after',
                'quarantine_before', 'quarantine_delta', 'quarantine_after',
                'actor_id', 'actor_name_snapshot', 'created_at',
            ]);
        }

        if (isset($filters['from'])) {
            $from = CarbonImmutable::parse($filters['from'], config('store.business_timezone'))->startOfDay()->utc();
            $query->where('created_at', '>=', $from);
        }

        if (isset($filters['to'])) {
            $to = CarbonImmutable::parse($filters['to'], config('store.business_timezone'))->startOfDay()->addDay()->utc();
            $query->where('created_at', '<', $to);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (isset($filters['reference_id'])) {
            $query->where('reference_id', $filters['reference_id']);
        }

        $paginator = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();

        if (! $includeCost) {
            $paginator->getCollection()->each->makeHidden([
                'unit_cost_basis',
                'average_cost_before',
                'average_cost_after',
            ]);
        }

        return $paginator;
    }

    /**
     * Resolve safe document URLs for a page of movements based on role permission.
     *
     * @param  iterable<int, InventoryMovement>  $movements
     * @return array<int, ?string>
     */
    public function documentUrls(iterable $movements, bool $canViewCost): array
    {
        $returnItemIds = [];
        foreach ($movements as $movement) {
            if ($movement->reference_type === InventoryReferenceType::ShipmentReturnItem && $movement->reference_id !== null) {
                $returnItemIds[] = $movement->reference_id;
            }
        }

        $returnOrderIds = $returnItemIds === []
            ? collect()
            : ShipmentReturnItem::query()
                ->with('shipment:id,order_id')
                ->whereIn('id', array_values(array_unique($returnItemIds)))
                ->get(['id', 'shipment_id'])
                ->mapWithKeys(fn (ShipmentReturnItem $item): array => [
                    $item->id => $item->shipment?->order_id,
                ]);

        $urls = [];
        foreach ($movements as $movement) {
            $urls[$movement->id] = $this->resolveDocumentUrl($movement, $canViewCost, $returnOrderIds);
        }

        return $urls;
    }

    /** @param Collection<int, ?int> $returnOrderIds */
    private function resolveDocumentUrl(InventoryMovement $movement, bool $canViewCost, Collection $returnOrderIds): ?string
    {
        if ($movement->reference_id === null || $movement->reference_type === null) {
            return null;
        }

        return match ($movement->reference_type) {
            InventoryReferenceType::StockImport => $canViewCost
                ? route('admin.stock-imports.show', $movement->reference_id)
                : null,
            InventoryReferenceType::Order => route('admin.orders.show', $movement->reference_id),
            InventoryReferenceType::ShipmentReturnItem => ($orderId = $returnOrderIds->get($movement->reference_id)) !== null
                ? route('admin.orders.show', $orderId).'#xu-ly-hang-hoan'
                : null,
            default => null,
        };
    }

    /** @return array{history_incomplete: bool, observed_balance_diff: bool} */
    public function reconciliation(ProductVariant $variant): array
    {
        $current = [
            'stock' => $variant->stock_qty,
            'reserved' => $variant->reserved_qty,
            'quarantine' => $variant->quarantine_qty,
        ];
        $first = true;
        $historyIncomplete = false;
        $previousAfter = null;
        $lastAfter = null;

        foreach (InventoryMovement::query()
            ->where('variant_id', $variant->id)
            ->select([
                'id', 'stock_before', 'stock_after', 'reserved_before', 'reserved_after',
                'quarantine_before', 'quarantine_after',
            ])
            ->orderBy('id')
            ->cursor() as $movement) {
            $before = [
                'stock' => $movement->stock_before,
                'reserved' => $movement->reserved_before,
                'quarantine' => $movement->quarantine_before,
            ];
            $after = [
                'stock' => $movement->stock_after,
                'reserved' => $movement->reserved_after,
                'quarantine' => $movement->quarantine_after,
            ];

            if (($first && $before !== ['stock' => 0, 'reserved' => 0, 'quarantine' => 0])
                || (! $first && $before !== $previousAfter)) {
                $historyIncomplete = true;
            }

            $first = false;
            $previousAfter = $after;
            $lastAfter = $after;
        }

        if ($first) {
            $historyIncomplete = true;
        }

        return [
            'history_incomplete' => $historyIncomplete,
            'observed_balance_diff' => $lastAfter !== null && $lastAfter !== $current,
        ];
    }
}
