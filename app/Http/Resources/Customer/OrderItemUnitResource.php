<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class OrderItemUnitResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_no' => $this->unit_no,
            'current_variant_id' => $this->current_variant_id,
            'state' => $this->state?->value,
            'gross_amount' => $this->gross_amount,
            'discount_amount' => $this->discount_amount,
            'net_amount' => $this->net_amount,
            'exchange_completed_count' => $this->exchange_completed_count,
            'exchange_deadline_at' => $this->exchange_deadline_at?->toISOString(),
            'warranty_deadline_at' => $this->warranty_deadline_at?->toISOString(),
            'refunded_merchandise_amount' => $this->refunded_merchandise_amount,
            'afterSalesRequests' => AfterSalesRequestResource::collection($this->whenLoaded('afterSalesRequests')),
            'review' => $this->whenLoaded('review', fn () => $this->review?->is_visible ? ReviewResource::make($this->review) : null),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('item.order');

        return $this->resource->item?->order?->user_id;
    }
}
