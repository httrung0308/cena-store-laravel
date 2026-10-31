<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['style_color_code', 'color_name', 'color_key', 'primary_hex', 'display_order'])]
#[Hidden([])]
class ProductColor extends DomainModel
{
    use SoftDeletes;

    protected $table = 'product_colors';

    protected $attributes = [
        'display_order' => '1',
        'is_active' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_id' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_color_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_color_id');
    }

    public function thumbnail(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_color_id')->where('is_thumbnail', true);
    }

    public function historicalProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }
}
