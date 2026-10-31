<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class InventoryMovementResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'actor_id' => $this->actor_id,
            'actor_name_snapshot' => $this->actor_name_snapshot,
            'action' => $this->action?->value,
            'stock_delta' => $this->stock_delta,
            'reserved_delta' => $this->reserved_delta,
            'quarantine_delta' => $this->quarantine_delta,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reserved_before' => $this->reserved_before,
            'reserved_after' => $this->reserved_after,
            'quarantine_before' => $this->quarantine_before,
            'quarantine_after' => $this->quarantine_after,
            'average_cost_before' => $this->average_cost_before,
            'average_cost_after' => $this->average_cost_after,
            'unit_cost_basis' => $this->unit_cost_basis,
            'reference_type' => $this->reference_type?->value,
            'reference_id' => $this->reference_id,
            'operation_key' => $this->operation_key,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
