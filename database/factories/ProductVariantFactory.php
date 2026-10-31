<?php

namespace Database\Factories;

use App\Models\ProductColor;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_color_id' => ProductColor::factory(),
            'size_value' => '42',
            'size_numeric' => '42.0',
            'sku' => 'SKU-'.Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function stocked(int $quantity = 5, string $cost = '10.0000'): static
    {
        return $this->state(['stock_qty' => $quantity, 'reserved_qty' => 0, 'quarantine_qty' => 0, 'average_cost' => $cost]);
    }
}
