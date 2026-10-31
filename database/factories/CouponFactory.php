<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'COUPON-'.Str::uuid(),
            'name' => fake()->words(3, true),
            'type' => CouponType::Percentage,
            'value' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
