<?php

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['checkout_key', 'checkout_fingerprint'])]
class Order extends DomainModel
{
    protected $table = 'orders';

    protected $attributes = [
        'merchandise_discount_amount' => '0',
        'shipping_discount_amount' => '0',
        'order_status' => 'PENDING',
        'payment_status' => 'UNPAID',
        'paid_amount' => '0',
        'refunded_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'coupon_id' => 'integer',
            'subtotal_amount' => 'integer',
            'merchandise_discount_amount' => 'integer',
            'shipping_base_fee' => 'integer',
            'shipping_discount_amount' => 'integer',
            'shipping_fee' => 'integer',
            'total_amount' => 'integer',
            'order_status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => OrderPaymentStatus::class,
            'paid_amount' => 'integer',
            'refunded_amount' => 'integer',
            'shipping_address_snapshot' => 'array',
            'source_cart_version' => 'integer',
            'payment_expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'packed_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id')->chaperone('order');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->chaperone('order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'order_id');
    }

    public function salesLedgerEntries(): HasMany
    {
        return $this->hasMany(SalesLedgerEntry::class, 'order_id');
    }
}
