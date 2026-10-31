<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class PasswordResetChallengeResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'attempts' => $this->attempts,
            'expires_at' => $this->expires_at?->toISOString(),
            'last_sent_at' => $this->last_sent_at?->toISOString(),
            'verified_at' => $this->verified_at?->toISOString(),
            'used_at' => $this->used_at?->toISOString(),
            'invalidated_at' => $this->invalidated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
