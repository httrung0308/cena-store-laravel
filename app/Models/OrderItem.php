<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['cost_unit_snapshot', 'cogs_amount'])]
class OrderItem extends DomainModel
{
    protected $table = 'order_items';

    protected $attributes = [
        'discount_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'original_unit_price' => 'integer',
            'unit_price' => 'integer',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'net_amount' => 'integer',
            'exchange_days_snapshot' => 'integer',
            'warranty_months_snapshot' => 'integer',
            'cost_unit_snapshot' => 'decimal:4',
            'cogs_amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function units(): HasMany
    {
        return $this->hasMany(OrderItemUnit::class, 'order_item_id')->chaperone('item');
    }
}
