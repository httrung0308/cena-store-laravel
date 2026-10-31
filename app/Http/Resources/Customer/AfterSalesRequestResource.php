<?php

namespace App\Http\Resources\Customer;

use App\Enums\HistoryVisibility;
use Illuminate\Http\Request;

class AfterSalesRequestResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'order_item_unit_id' => $this->order_item_unit_id,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'source_variant_id' => $this->source_variant_id,
            'target_variant_id' => $this->target_variant_id,
            'customer_reason' => $this->customer_reason,
            'receive_by_at' => $this->receive_by_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'ready_at' => $this->ready_at?->toISOString(),
            'customer_handover_at' => $this->customer_handover_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'histories' => AfterSalesHistoryResource::collection($this->whenLoaded('histories', fn () => $this->histories->where('visibility', HistoryVisibility::Customer))),
            'attachments' => AfterSalesAttachmentResource::collection($this->whenLoaded('attachments')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('unit.item.order');

        return $this->resource->unit?->item?->order?->user_id;
    }
}
