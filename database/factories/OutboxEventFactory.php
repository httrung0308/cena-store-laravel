<?php

namespace Database\Factories;

use App\Enums\OutboxAggregateType;
use App\Models\Order;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OutboxEvent> */
class OutboxEventFactory extends Factory
{
    protected $model = OutboxEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_type' => 'ORDER_CREATED',
            'aggregate_type' => OutboxAggregateType::Order,
            'aggregate_id' => Order::factory(),
            'dedup_key' => (string) Str::uuid(),
            'payload' => fn (array $attributes) => ['order_id' => $attributes['aggregate_id']],
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
