<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class RefundResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason_type' => $this->reason_type?->value,
            'status' => $this->status?->value,
            'merchandise_amount' => $this->merchandise_amount,
            'shipping_amount' => $this->shipping_amount,
            'amount' => $this->amount,
            'destination_type' => $this->destination_type?->value,
            'succeeded_at' => $this->succeeded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('order');

        return $this->resource->order?->user_id;
    }
}
