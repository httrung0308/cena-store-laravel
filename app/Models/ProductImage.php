<?php

namespace App\Models;

use App\Enums\ProductImageSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['alt_text', 'display_order'])]
#[Hidden(['thumbnail_color_id'])]
class ProductImage extends DomainModel
{
    protected $table = 'product_images';

    protected $attributes = [
        'is_thumbnail' => '0',
        'display_order' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_color_id' => 'integer',
            'source_type' => ProductImageSourceType::class,
            'is_thumbnail' => 'boolean',
            'display_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'thumbnail_color_id' => 'integer',
        ];
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function thumbnailUrl(int $size = 400): string
    {
        return app(\App\Services\ProductImageThumbnailService::class)->getThumbnailUrl(
            $this->storage_path,
            $this->external_url,
            $size
        );
    }
}
