<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class CartItemResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'quantity' => $this->quantity,
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('cart');

        return $this->resource->cart?->user_id;
    }
}
