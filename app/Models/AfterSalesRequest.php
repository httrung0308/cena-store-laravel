<?php

namespace App\Models;

use App\Enums\AfterSalesStatus;
use App\Enums\AfterSalesType;
use App\Enums\ReturnDisposition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_reason'])]
#[Hidden(['staff_note', 'returned_cogs_amount', 'replacement_cogs_amount', 'active_unit_id'])]
class AfterSalesRequest extends DomainModel
{
    protected $table = 'after_sales_requests';

    protected $attributes = [
        'status' => 'NEW',
        'target_reservation_active' => '0',
        'source_in_quarantine' => '0',
        'return_disposition' => 'PENDING',
        'repair_expense_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_item_unit_id' => 'integer',
            'type' => AfterSalesType::class,
            'status' => AfterSalesStatus::class,
            'source_variant_id' => 'integer',
            'target_variant_id' => 'integer',
            'target_reservation_active' => 'boolean',
            'source_in_quarantine' => 'boolean',
            'return_disposition' => ReturnDisposition::class,
            'disposition_by' => 'integer',
            'disposition_at' => 'immutable_datetime',
            'handled_by' => 'integer',
            'receive_by_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'customer_handover_at' => 'immutable_datetime',
            'returned_cogs_amount' => 'integer',
            'replacement_cogs_amount' => 'integer',
            'repair_expense_amount' => 'integer',
            'repair_expense_approved_by' => 'integer',
            'repair_expense_approved_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'active_unit_id' => 'integer',
        ];
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'after_sales_request_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrderItemUnit::class, 'order_item_unit_id');
    }

    public function sourceVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'source_variant_id')->withTrashed();
    }

    public function targetVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'target_variant_id')->withTrashed();
    }

    public function dispositionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_by')->withTrashed();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function repairExpenseApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repair_expense_approved_by')->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AfterSalesHistory::class, 'after_sales_request_id')->chaperone('afterSalesRequest');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AfterSalesAttachment::class, 'after_sales_request_id')->chaperone('afterSalesRequest');
    }

    public function salesLedgerEntries(): HasMany
    {
        return $this->hasMany(SalesLedgerEntry::class, 'after_sales_request_id');
    }
}
