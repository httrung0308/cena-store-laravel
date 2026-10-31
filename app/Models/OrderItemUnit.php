<?php

namespace App\Models;

use App\Enums\OrderUnitState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['original_cogs_amount', 'current_cogs_amount'])]
class OrderItemUnit extends DomainModel
{
    protected $table = 'order_item_units';

    protected $attributes = [
        'state' => 'ALLOCATED',
        'discount_amount' => '0',
        'exchange_completed_count' => '0',
        'refunded_merchandise_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_item_id' => 'integer',
            'unit_no' => 'integer',
            'current_variant_id' => 'integer',
            'state' => OrderUnitState::class,
            'gross_amount' => 'integer',
            'discount_amount' => 'integer',
            'net_amount' => 'integer',
            'original_cogs_amount' => 'integer',
            'current_cogs_amount' => 'integer',
            'exchange_completed_count' => 'integer',
            'exchange_deadline_at' => 'immutable_datetime',
            'warranty_deadline_at' => 'immutable_datetime',
            'refunded_merchandise_amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function currentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'current_variant_id')->withTrashed();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_item_unit_id');
    }

    public function shipmentReturnItem(): HasOne
    {
        return $this->hasOne(ShipmentReturnItem::class, 'order_item_unit_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'order_item_unit_id');
    }

    public function afterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class, 'order_item_unit_id')->chaperone('unit');
    }

    public function salesLedgerEntries(): HasMany
    {
        return $this->hasMany(SalesLedgerEntry::class, 'order_item_unit_id');
    }
}
