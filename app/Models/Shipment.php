<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['proof_path'])]
class Shipment extends DomainModel
{
    protected $table = 'shipments';

    protected $attributes = [
        'status' => 'READY',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'status' => ShipmentStatus::class,
            'created_by' => 'integer',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'return_received_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function shipmentReturnItems(): HasMany
    {
        return $this->hasMany(ShipmentReturnItem::class, 'shipment_id');
    }

    public function returnEvents(): HasMany
    {
        return $this->hasMany(ShipmentReturnEvent::class, 'shipment_id')->orderBy('occurred_at')->orderBy('id');
    }
}
