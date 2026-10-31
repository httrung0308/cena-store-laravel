<?php

namespace App\Models;

use App\Enums\RefundDestinationType;
use App\Enums\RefundReasonType;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['destination_ciphertext', 'evidence_path'])]
class Refund extends DomainModel
{
    protected $table = 'refunds';

    protected $attributes = [
        'status' => 'REQUESTED',
        'merchandise_amount' => '0',
        'shipping_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'payment_id' => 'integer',
            'after_sales_request_id' => 'integer',
            'order_item_unit_id' => 'integer',
            'reason_type' => RefundReasonType::class,
            'status' => RefundStatus::class,
            'merchandise_amount' => 'integer',
            'shipping_amount' => 'integer',
            'amount' => 'integer',
            'destination_type' => RefundDestinationType::class,
            'destination_ciphertext' => 'encrypted:array',
            'requested_by' => 'integer',
            'approved_by' => 'integer',
            'paid_by' => 'integer',
            'succeeded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function afterSalesRequest(): BelongsTo
    {
        return $this->belongsTo(AfterSalesRequest::class, 'after_sales_request_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrderItemUnit::class, 'order_item_unit_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by')->withTrashed();
    }

    public function salesLedgerEntries(): HasMany
    {
        return $this->hasMany(SalesLedgerEntry::class, 'refund_id');
    }
}
