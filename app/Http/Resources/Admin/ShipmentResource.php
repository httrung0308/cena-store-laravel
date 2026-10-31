<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ShipmentResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'tracking_code' => $this->tracking_code,
            'carrier_name' => $this->carrier_name,
            'status' => $this->status?->value,
            'created_by' => $this->created_by,
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'return_received_at' => $this->return_received_at?->toISOString(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
