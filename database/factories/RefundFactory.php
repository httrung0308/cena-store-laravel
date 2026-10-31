<?php

namespace Database\Factories;

use App\Enums\RefundDestinationType;
use App\Enums\RefundReasonType;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Refund> */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->succeeded(),
            'order_id' => fn (array $attributes) => Payment::findOrFail($attributes['payment_id'])->order_id,
            'reason_type' => RefundReasonType::OrderCancelled,
            'amount' => 100,
            'operation_key' => (string) Str::uuid(),
            'destination_type' => RefundDestinationType::Cash,
            'created_at' => now(),
            'updated_at' => now(),
            'merchandise_amount' => 100,
        ];
    }
}
