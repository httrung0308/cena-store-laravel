<?php

namespace App\Models;

use App\Enums\AdministrativeUnitLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden([])]
class AdministrativeUnit extends DomainModel
{
    protected $table = 'administrative_units';

    protected $attributes = [
        'is_active' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'level' => AdministrativeUnitLevel::class,
            'parent_id' => 'integer',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AdministrativeUnit::class, 'parent_id');
    }

    public function provinceAddresses(): HasMany
    {
        return $this->hasMany(Address::class, 'province_id');
    }

    public function wardAddresses(): HasMany
    {
        return $this->hasMany(Address::class, 'ward_id');
    }
}
