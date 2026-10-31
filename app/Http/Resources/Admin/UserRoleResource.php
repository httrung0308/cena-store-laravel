<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class UserRoleResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role_id' => $this->role_id,
            'assigned_by' => $this->assigned_by,
            'assigned_at' => $this->assigned_at?->toISOString(),
        ];
    }
}
