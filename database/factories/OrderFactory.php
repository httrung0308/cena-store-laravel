<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_code' => 'ORD-'.Str::ulid(),
            'user_id' => User::factory(),
            'subtotal_amount' => 100,
            'shipping_base_fee' => 0,
            'shipping_fee' => 0,
            'total_amount' => 100,
            'payment_method' => PaymentMethod::Cod,
            'shipping_name' => fake()->name(),
            'shipping_phone' => '0900000000',
            'shipping_address_snapshot' => ['province' => 'Test province', 'ward' => 'Test ward', 'street' => '1 Test Street', 'dataset_version' => 'factory-v1'],
            'checkout_key' => (string) Str::uuid(),
            'checkout_fingerprint' => hash('sha256', (string) Str::uuid()),
            'source_cart_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
