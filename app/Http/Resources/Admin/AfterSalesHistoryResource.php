<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class AfterSalesHistoryResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'after_sales_request_id' => $this->after_sales_request_id,
            'actor_id' => $this->actor_id,
            'actor_name_snapshot' => $this->actor_name_snapshot,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'event_type' => $this->event_type?->value,
            'visibility' => $this->visibility?->value,
            'note' => $this->note,
            'command_key' => $this->command_key,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
