<?php

namespace App\Models;

use App\Enums\ReturnDisposition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['cost_basis'])]
class ShipmentReturnItem extends DomainModel
{
    protected $table = 'shipment_return_items';

    protected $attributes = [
        'disposition' => 'PENDING',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'shipment_id' => 'integer',
            'order_item_unit_id' => 'integer',
            'variant_id' => 'integer',
            'received_by' => 'integer',
            'received_at' => 'immutable_datetime',
            'disposition' => ReturnDisposition::class,
            'cost_basis' => 'decimal:4',
            'inspected_by' => 'integer',
            'inspected_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrderItemUnit::class, 'order_item_unit_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by')->withTrashed();
    }
}
