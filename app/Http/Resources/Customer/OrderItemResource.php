<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class OrderItemResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'product_name_snapshot' => $this->product_name_snapshot,
            'style_color_snapshot' => $this->style_color_snapshot,
            'color_name_snapshot' => $this->color_name_snapshot,
            'size_value_snapshot' => $this->size_value_snapshot,
            'sku_snapshot' => $this->sku_snapshot,
            'thumbnail_snapshot' => $this->thumbnail_snapshot,
            'quantity' => $this->quantity,
            'original_unit_price' => $this->original_unit_price,
            'unit_price' => $this->unit_price,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'net_amount' => $this->net_amount,
            'exchange_days_snapshot' => $this->exchange_days_snapshot,
            'warranty_months_snapshot' => $this->warranty_months_snapshot,
            'units' => OrderItemUnitResource::collection($this->whenLoaded('units')),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('order');

        return $this->resource->order?->user_id;
    }
}
