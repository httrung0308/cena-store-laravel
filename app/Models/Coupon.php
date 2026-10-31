<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([])]
#[Hidden([])]
class Coupon extends DomainModel
{
    use SoftDeletes;

    protected $table = 'coupons';

    protected $attributes = [
        'min_order_amount' => '0',
        'per_user_limit' => '1',
        'is_active' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'type' => CouponType::class,
            'value' => 'integer',
            'min_order_amount' => 'integer',
            'max_discount_amount' => 'integer',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'coupon_id');
    }
}
