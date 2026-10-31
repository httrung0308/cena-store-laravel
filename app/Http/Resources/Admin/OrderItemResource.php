<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class OrderItemResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
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
            'cost_unit_snapshot' => $this->cost_unit_snapshot,
            'cogs_amount' => $this->cogs_amount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
