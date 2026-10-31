<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class OrderStatusHistoryResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_name_snapshot' => $this->actor_name_snapshot,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'reason_code' => $this->reason_code,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('order');

        return $this->resource->order?->user_id;
    }
}
