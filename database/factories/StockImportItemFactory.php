<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\StockImportItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockImportItem> */
class StockImportItemFactory extends Factory
{
    protected $model = StockImportItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stock_import_id' => StockImport::factory(),
            'variant_id' => ProductVariant::factory(),
            'sku_snapshot' => fn (array $attributes) => ProductVariant::findOrFail($attributes['variant_id'])->sku,
            'quantity' => 1,
            'unit_cost_amount' => 100,
            'subtotal_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
