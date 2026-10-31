<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class ShipmentResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_code' => $this->tracking_code,
            'carrier_name' => $this->carrier_name,
            'status' => $this->status?->value,
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'return_received_at' => $this->return_received_at?->toISOString(),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('order');

        return $this->resource->order?->user_id;
    }
}
