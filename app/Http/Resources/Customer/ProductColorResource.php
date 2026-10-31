<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Request;

class ProductColorResource extends StoreResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'style_color_code' => $this->style_color_code,
            'color_name' => $this->color_name,
            'color_key' => $this->color_key,
            'primary_hex' => $this->primary_hex,
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'thumbnail' => ProductImageResource::make($this->whenLoaded('thumbnail')),
        ];
    }
}
