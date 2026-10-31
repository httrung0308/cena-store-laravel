<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['recipient_name', 'phone', 'street_address', 'legacy_district_text'])]
#[Hidden(['default_owner_id'])]
class Address extends DomainModel
{
    use SoftDeletes;

    protected $table = 'addresses';

    protected $attributes = [
        'is_default' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'province_id' => 'integer',
            'ward_id' => 'integer',
            'is_default' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'default_owner_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'province_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'ward_id');
    }
}
