<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class OrderStatusHistoryResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'actor_id' => $this->actor_id,
            'actor_name_snapshot' => $this->actor_name_snapshot,
            'actor_roles_snapshot' => $this->actor_roles_snapshot,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'reason_code' => $this->reason_code,
            'note' => $this->note,
            'command_key' => $this->command_key,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
