<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrderStatusHistory> */
class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'actor_name_snapshot' => 'System',
            'to_status' => OrderStatus::Pending,
            'reason_code' => 'CREATED',
            'command_key' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}
