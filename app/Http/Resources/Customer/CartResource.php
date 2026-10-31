<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class CartResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'expires_at' => $this->expires_at?->toISOString(),
            'items' => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }

    protected function ownerId(): ?int
    {
        return $this->resource->user_id;
    }
}
