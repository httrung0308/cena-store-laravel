<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Request;

class ReviewResource extends StoreResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        abort_unless($this->is_visible, 404);

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'admin_reply' => $this->admin_reply,
            'replied_at' => $this->replied_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
