<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CouponRedemption> */
class CouponRedemptionFactory extends Factory
{
    protected $model = CouponRedemption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'reserved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
