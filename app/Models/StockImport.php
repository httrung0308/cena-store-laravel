<?php

namespace App\Models;

use App\Enums\StockImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['total_cost_amount'])]
class StockImport extends DomainModel
{
    protected $table = 'stock_imports';

    protected $attributes = [
        'status' => 'DRAFT',
        'total_quantity' => '0',
        'total_cost_amount' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'supplier_id' => 'integer',
            'created_by' => 'integer',
            'posted_by' => 'integer',
            'status' => StockImportStatus::class,
            'total_quantity' => 'integer',
            'total_cost_amount' => 'integer',
            'posted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockImportItem::class, 'stock_import_id');
    }
}
