<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ShipmentReturnItemResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'order_item_unit_id' => $this->order_item_unit_id,
            'variant_id' => $this->variant_id,
            'received_by' => $this->received_by,
            'received_at' => $this->received_at?->toISOString(),
            'disposition' => $this->disposition?->value,
            'cost_basis' => $this->cost_basis,
            'inspected_by' => $this->inspected_by,
            'inspected_at' => $this->inspected_at?->toISOString(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
