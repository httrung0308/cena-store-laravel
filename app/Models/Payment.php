<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentDisposition;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['customer_reported_evidence_path', 'confirmation_evidence_path', 'pending_order_id'])]
class Payment extends DomainModel
{
    protected $table = 'payments';

    protected $attributes = [
        'status' => 'PENDING',
        'disposition' => 'NONE',
        'received_amount' => '0',
        'applied_amount' => '0',
        'currency' => 'VND',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'origin_payment_id' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentAttemptStatus::class,
            'disposition' => PaymentDisposition::class,
            'expected_amount' => 'integer',
            'received_amount' => 'integer',
            'applied_amount' => 'integer',
            'currency' => Currency::class,
            'customer_reported_at' => 'immutable_datetime',
            'confirmed_by' => 'integer',
            'expires_at' => 'immutable_datetime',
            'succeeded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'pending_order_id' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function originPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'origin_payment_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'origin_payment_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class, 'payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'payment_id');
    }
}
