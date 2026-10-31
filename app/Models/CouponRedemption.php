<?php

namespace App\Models;

use App\Enums\CouponRedemptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden([])]
class CouponRedemption extends DomainModel
{
    protected $table = 'coupon_redemptions';

    protected $attributes = [
        'status' => 'RESERVED',
        'merchandise_discount_amount' => '0',
        'shipping_discount_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'coupon_id' => 'integer',
            'user_id' => 'integer',
            'order_id' => 'integer',
            'status' => CouponRedemptionStatus::class,
            'merchandise_discount_amount' => 'integer',
            'shipping_discount_amount' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
