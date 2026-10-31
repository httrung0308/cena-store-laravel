<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ProductImageResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_color_id' => $this->product_color_id,
            'source_type' => $this->source_type?->value,
            'storage_path' => $this->storage_path,
            'external_url' => $this->external_url,
            'alt_text' => $this->alt_text,
            'is_thumbnail' => $this->is_thumbnail,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
