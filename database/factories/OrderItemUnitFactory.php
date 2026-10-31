<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItemUnit> */
class OrderItemUnitFactory extends Factory
{
    protected $model = OrderItemUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'unit_no' => 1,
            'current_variant_id' => fn (array $attributes) => OrderItem::findOrFail($attributes['order_item_id'])->variant_id,
            'gross_amount' => 100,
            'net_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
