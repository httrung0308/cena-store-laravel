<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'created_at' => now(),
            'updated_at' => now(),
            'user_id' => User::factory(),
        ];
    }

    public function guest(): static
    {
        return $this->state(['user_id' => null, 'guest_token_hash' => hash('sha256', (string) Str::uuid())]);
    }
}
