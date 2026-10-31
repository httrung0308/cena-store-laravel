<?php

namespace Database\Factories;

use App\Models\OrderItemUnit;
use App\Models\Shipment;
use App\Models\ShipmentReturnItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShipmentReturnItem> */
class ShipmentReturnItemFactory extends Factory
{
    protected $model = ShipmentReturnItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_item_unit_id' => OrderItemUnit::factory(),
            'shipment_id' => fn (array $attributes) => Shipment::factory()->for(OrderItemUnit::findOrFail($attributes['order_item_unit_id'])->item->order)->create()->id,
            'variant_id' => fn (array $attributes) => OrderItemUnit::findOrFail($attributes['order_item_unit_id'])->current_variant_id,
            'received_by' => User::factory(),
            'received_at' => now(),
            'cost_basis' => '0.0000',
            'note' => 'Test return receipt',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
