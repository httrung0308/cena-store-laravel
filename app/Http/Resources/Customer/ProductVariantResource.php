<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Request;

class ProductVariantResource extends StoreResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'size_value' => $this->size_value,
            'size_numeric' => $this->size_numeric,
            'sku' => $this->sku,
            'price_override' => $this->price_override,
            'is_active' => $this->is_active,
            'available_qty' => $this->stock_qty - $this->reserved_qty,
        ];
    }
}
