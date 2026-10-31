<?php

namespace App\Http\Resources\Customer;

use App\Enums\HistoryVisibility;
use Illuminate\Http\Request;

class AfterSalesHistoryResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        abort_unless($this->visibility === HistoryVisibility::Customer, 404);

        return [
            'id' => $this->id,
            'actor_name_snapshot' => $this->actor_name_snapshot,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'event_type' => $this->event_type?->value,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('afterSalesRequest.unit.item.order');

        return $this->resource->afterSalesRequest?->unit?->item?->order?->user_id;
    }
}
