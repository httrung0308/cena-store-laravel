<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'carrier_name' => 'DEMO_MANUAL',
            'created_by' => User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
