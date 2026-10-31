<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class AfterSalesAttachmentResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'mime_type' => $this->mime_type?->value,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('afterSalesRequest.unit.item.order');

        return $this->resource->afterSalesRequest?->unit?->item?->order?->user_id;
    }
}
