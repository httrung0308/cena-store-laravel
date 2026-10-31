<?php

namespace App\Models;

use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['average_cost_before', 'average_cost_after', 'unit_cost_basis'])]
class InventoryMovement extends DomainModel
{
    protected $table = 'inventory_movements';

    protected $attributes = [
        'stock_delta' => '0',
        'reserved_delta' => '0',
        'quarantine_delta' => '0',
    ];

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'variant_id' => 'integer',
            'actor_id' => 'integer',
            'action' => InventoryAction::class,
            'stock_delta' => 'integer',
            'reserved_delta' => 'integer',
            'quarantine_delta' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'reserved_before' => 'integer',
            'reserved_after' => 'integer',
            'quarantine_before' => 'integer',
            'quarantine_after' => 'integer',
            'average_cost_before' => 'decimal:4',
            'average_cost_after' => 'decimal:4',
            'unit_cost_basis' => 'decimal:4',
            'reference_type' => InventoryReferenceType::class,
            'reference_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function salesLedgerEntries(): HasMany
    {
        return $this->hasMany(SalesLedgerEntry::class, 'inventory_movement_id');
    }
}
