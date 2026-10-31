<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class OutboxEventResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'aggregate_type' => $this->aggregate_type?->value,
            'aggregate_id' => $this->aggregate_id,
            'dedup_key' => $this->dedup_key,
            'status' => $this->status?->value,
            'attempts' => $this->attempts,
            'available_at' => $this->available_at?->toISOString(),
            'lease_until' => $this->lease_until?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
