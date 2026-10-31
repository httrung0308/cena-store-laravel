<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        ];
    }

    protected function ownerId(): ?int
    {
        return $this->id;
    }
}
