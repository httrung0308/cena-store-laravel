<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class ShipmentReturnEvent extends DomainModel
{
    public const RECEIVED = 'RECEIVED';

    public const RESTOCKED = 'RESTOCKED';

    public const WRITE_OFF_PROPOSED = 'WRITE_OFF_PROPOSED';

    public const WRITE_OFF_APPROVED = 'WRITE_OFF_APPROVED';

    public const MISSING_UNIT = 'MISSING_UNIT';

    public const SKU_MISMATCH = 'SKU_MISMATCH';

    public const DAMAGED_UNIT = 'DAMAGED_UNIT';

    public const NO_EVIDENCE = 'NO_EVIDENCE';

    protected $table = 'shipment_return_events';

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'shipment_id' => 'integer',
            'shipment_return_item_id' => 'integer',
            'order_item_unit_id' => 'integer',
            'actor_id' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentReturnItem::class, 'shipment_return_item_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrderItemUnit::class, 'order_item_unit_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
