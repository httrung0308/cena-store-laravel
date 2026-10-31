<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\StoreResource;
use Illuminate\Http\Request;

class ProductResource extends StoreResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand_line' => $this->brand_line,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'base_price' => $this->base_price,
            'promotion_price' => $this->promotion_price,
            'warranty_months' => $this->warranty_months,
            'exchange_days' => $this->exchange_days,
            'rating_avg' => $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'colors' => ProductColorResource::collection($this->whenLoaded('colors')),
        ];
    }
}
