<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class OrderItemUnitResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'unit_no' => $this->unit_no,
            'current_variant_id' => $this->current_variant_id,
            'state' => $this->state?->value,
            'gross_amount' => $this->gross_amount,
            'discount_amount' => $this->discount_amount,
            'net_amount' => $this->net_amount,
            'original_cogs_amount' => $this->original_cogs_amount,
            'current_cogs_amount' => $this->current_cogs_amount,
            'exchange_completed_count' => $this->exchange_completed_count,
            'exchange_deadline_at' => $this->exchange_deadline_at?->toISOString(),
            'warranty_deadline_at' => $this->warranty_deadline_at?->toISOString(),
            'refunded_merchandise_amount' => $this->refunded_merchandise_amount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
