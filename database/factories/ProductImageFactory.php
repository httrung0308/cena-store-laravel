<?php

namespace Database\Factories;

use App\Enums\ProductImageSourceType;
use App\Models\ProductColor;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductImage> */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_color_id' => ProductColor::factory(),
            'source_type' => ProductImageSourceType::Upload,
            'alt_text' => 'Test shoe image',
            'created_at' => now(),
            'updated_at' => now(),
            'storage_path' => 'products/'.Str::uuid().'.jpg',
        ];
    }

    public function thumbnail(): static
    {
        return $this->state(['is_thumbnail' => true]);
    }
}
