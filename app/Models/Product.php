<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'brand_line', 'description', 'specifications', 'base_price', 'promotion_price', 'warranty_months', 'exchange_days', 'is_featured'])]
#[Hidden([])]
class Product extends DomainModel
{
    use SoftDeletes;

    protected $table = 'products';

    protected $attributes = [
        'warranty_months' => '6',
        'exchange_days' => '7',
        'rating_avg' => '0.00',
        'reviews_count' => '0',
        'is_featured' => '0',
        'is_active' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'category_id' => 'integer',
            'specifications' => 'array',
            'base_price' => 'integer',
            'promotion_price' => 'integer',
            'warranty_months' => 'integer',
            'exchange_days' => 'integer',
            'rating_avg' => 'decimal:2',
            'reviews_count' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class, 'product_id');
    }

    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariant::class, ProductColor::class, 'product_id', 'product_color_id');
    }

    public function historicalCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id')->withTrashed();
    }

    public function classifications(): BelongsToMany
    {
        return $this->belongsToMany(Classification::class, 'product_classifications');
    }

    public function activeClassifications(): BelongsToMany
    {
        return $this->belongsToMany(Classification::class, 'product_classifications')
            ->where('classifications.is_active', true);
    }
}
