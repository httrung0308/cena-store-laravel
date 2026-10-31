<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'phone', 'email', 'address'])]
#[Hidden([])]
class Supplier extends DomainModel
{
    use SoftDeletes;

    protected $table = 'suppliers';

    protected $attributes = [
        'is_active' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function stockImports(): HasMany
    {
        return $this->hasMany(StockImport::class, 'supplier_id');
    }
}
