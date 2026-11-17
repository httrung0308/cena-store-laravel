<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class StoreNotificationResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        $this->resource->loadMissing('order');

        return [
            'id' => $this->id,
            'order_code' => $this->resource->order?->order_code,
            'order_status' => $this->order_status->value,
            'order_status_label' => $this->order_status->label(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'url' => $this->resource->order === null ? null : route('orders.show', $this->resource->order->order_code),
        ];
    }

    protected function ownerId(): ?int
    {
        return $this->user_id;
    }
}
