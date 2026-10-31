<?php

namespace Database\Factories;

use App\Models\OrderItemUnit;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_item_unit_id' => OrderItemUnit::factory(),
            'user_id' => fn (array $attributes) => OrderItemUnit::findOrFail($attributes['order_item_unit_id'])->item->order->user_id,
            'rating' => '4.5',
            'comment' => 'A structurally valid test review.',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
