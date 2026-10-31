<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductColor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductColor> */
class ProductColorFactory extends Factory
{
    protected $model = ProductColor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'style_color_code' => 'STYLE-'.Str::uuid(),
            'color_name' => 'Black',
            'color_key' => 'black',
            'primary_hex' => '#000000',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
