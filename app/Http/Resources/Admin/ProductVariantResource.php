<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ProductVariantResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_color_id' => $this->product_color_id,
            'size_value' => $this->size_value,
            'size_numeric' => $this->size_numeric,
            'sku' => $this->sku,
            'price_override' => $this->price_override,
            'stock_qty' => $this->stock_qty,
            'reserved_qty' => $this->reserved_qty,
            'quarantine_qty' => $this->quarantine_qty,
            'average_cost' => $this->average_cost,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
