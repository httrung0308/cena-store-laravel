<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class InventoryHistoryQueryService
{
    /** @return Collection<int, ProductColor> */
    public function productColorsWithVariants(Product $product): Collection
    {
        return ProductColor::withTrashed()
            ->where('product_id', $product->id)
            ->with(['variants' => fn ($query) => $query->withTrashed()->orderBy('size_numeric')->orderBy('id')])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function movements(ProductVariant $variant, array $filters): LengthAwarePaginator
    {
        $query = InventoryMovement::query()
            ->with('actor:id,full_name')
            ->where('variant_id', $variant->id);

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

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
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
