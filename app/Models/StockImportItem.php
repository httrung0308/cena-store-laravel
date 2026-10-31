<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['unit_cost_amount'])]
class StockImportItem extends DomainModel
{
    protected $table = 'stock_import_items';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'stock_import_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'unit_cost_amount' => 'integer',
            'subtotal_amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function stockImport(): BelongsTo
    {
        return $this->belongsTo(StockImport::class, 'stock_import_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }
}
