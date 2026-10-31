<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentDisposition;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'attempt_ref' => 'PAY-'.Str::ulid(),
            'method' => PaymentMethod::Cod,
            'expected_amount' => fn (array $attributes) => Order::findOrFail($attributes['order_id'])->total_amount,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentAttemptStatus::Succeeded,
            'disposition' => PaymentDisposition::Applied,
            'received_amount' => $attributes['expected_amount'],
            'applied_amount' => $attributes['expected_amount'],
            'succeeded_at' => now(),
        ]);
    }
}
