<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class OrderResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'user_id' => $this->user_id,
            'coupon_id' => $this->coupon_id,
            'coupon_code_snapshot' => $this->coupon_code_snapshot,
            'subtotal_amount' => $this->subtotal_amount,
            'merchandise_discount_amount' => $this->merchandise_discount_amount,
            'shipping_base_fee' => $this->shipping_base_fee,
            'shipping_discount_amount' => $this->shipping_discount_amount,
            'shipping_fee' => $this->shipping_fee,
            'total_amount' => $this->total_amount,
            'order_status' => $this->order_status?->value,
            'payment_method' => $this->payment_method?->value,
            'payment_status' => $this->payment_status?->value,
            'paid_amount' => $this->paid_amount,
            'refunded_amount' => $this->refunded_amount,
            'shipping_name' => $this->shipping_name,
            'shipping_phone' => $this->shipping_phone,
            'shipping_address_snapshot' => $this->shipping_address_snapshot,
            'customer_note' => $this->customer_note,
            'cancel_reason' => $this->cancel_reason,
            'source_cart_version' => $this->source_cart_version,
            'payment_expires_at' => $this->payment_expires_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'packed_at' => $this->packed_at?->toISOString(),
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
