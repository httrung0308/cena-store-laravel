<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'variant_id' => ProductVariant::factory(),
            'product_name_snapshot' => fn (array $attributes) => ProductVariant::findOrFail($attributes['variant_id'])->color->product->name,
            'style_color_snapshot' => fn (array $attributes) => ProductVariant::findOrFail($attributes['variant_id'])->color->style_color_code,
            'color_name_snapshot' => fn (array $attributes) => ProductVariant::findOrFail($attributes['variant_id'])->color->color_name,
            'size_value_snapshot' => fn (array $attributes) => ProductVariant::findOrFail($attributes['variant_id'])->size_value,
            'sku_snapshot' => fn (array $attributes) => ProductVariant::findOrFail($attributes['variant_id'])->sku,
            'quantity' => 1,
            'original_unit_price' => 100,
            'unit_price' => 100,
            'subtotal_amount' => 100,
            'net_amount' => 100,
            'exchange_days_snapshot' => 7,
            'warranty_months_snapshot' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
