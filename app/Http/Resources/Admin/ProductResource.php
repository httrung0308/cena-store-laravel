<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class ProductResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
