<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ReviewResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_unit_id' => $this->order_item_unit_id,
            'user_id' => $this->user_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'admin_reply' => $this->admin_reply,
            'is_visible' => $this->is_visible,
            'replied_by' => $this->replied_by,
            'replied_at' => $this->replied_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
