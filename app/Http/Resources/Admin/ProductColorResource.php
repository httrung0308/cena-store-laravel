<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ProductColorResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'style_color_code' => $this->style_color_code,
            'color_name' => $this->color_name,
            'color_key' => $this->color_key,
            'primary_hex' => $this->primary_hex,
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
