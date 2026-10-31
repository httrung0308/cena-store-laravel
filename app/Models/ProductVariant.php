<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['size_value', 'sku', 'price_override'])]
#[Hidden(['average_cost'])]
class ProductVariant extends DomainModel
{
    use SoftDeletes;

    protected $table = 'product_variants';

    protected $attributes = [
        'stock_qty' => '0',
        'reserved_qty' => '0',
        'quarantine_qty' => '0',
        'average_cost' => '0.0000',
        'is_active' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_color_id' => 'integer',
            'size_numeric' => 'decimal:1',
            'price_override' => 'integer',
            'stock_qty' => 'integer',
            'reserved_qty' => 'integer',
            'quarantine_qty' => 'integer',
            'average_cost' => 'decimal:4',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function stockImportItems(): HasMany
    {
        return $this->hasMany(StockImportItem::class, 'variant_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function orderItemUnits(): HasMany
    {
        return $this->hasMany(OrderItemUnit::class, 'current_variant_id');
    }

    public function shipmentReturnItems(): HasMany
    {
        return $this->hasMany(ShipmentReturnItem::class, 'variant_id');
    }

    public function sourceVariantAfterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class, 'source_variant_id');
    }

    public function targetVariantAfterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class, 'target_variant_id');
    }

    public function historicalColor(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id')->withTrashed();
    }
}
