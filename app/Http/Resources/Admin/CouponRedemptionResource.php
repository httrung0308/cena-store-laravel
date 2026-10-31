<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class CouponRedemptionResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'coupon_id' => $this->coupon_id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'status' => $this->status?->value,
            'merchandise_discount_amount' => $this->merchandise_discount_amount,
            'shipping_discount_amount' => $this->shipping_discount_amount,
            'reserved_at' => $this->reserved_at?->toISOString(),
            'consumed_at' => $this->consumed_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
