<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'subtitle', 'image_path', 'mobile_image_path', 'link_url', 'cta_text', 'sort_order'])]
#[Hidden([])]
class Banner extends DomainModel
{
    use HasFactory;

    protected $table = 'banners';

    protected $attributes = [
        'sort_order' => 1,
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function mobileImageUrl(): ?string
    {
        return $this->mobile_image_path
            ? Storage::disk('public')->url($this->mobile_image_path)
            : null;
    }

    /** @param Builder<Banner> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Banner> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
