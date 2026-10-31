<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class CartItemResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'variant_id' => $this->variant_id,
            'quantity' => $this->quantity,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
