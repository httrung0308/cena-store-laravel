<?php

namespace App\Models;

use App\Enums\ClassificationGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'classification_group', 'description', 'sort_order', 'is_active'])]
#[Hidden([])]
class Classification extends DomainModel
{
    protected $table = 'classifications';

    protected $attributes = [
        'sort_order' => 1,
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'classification_group' => ClassificationGroup::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_classifications');
    }

    /** @param Builder<Classification> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Classification> $query */
    public function scopeGroup(Builder $query, ClassificationGroup|string $group): void
    {
        $value = $group instanceof ClassificationGroup ? $group->value : $group;
        $query->where('classification_group', $value);
    }
}
