<?php

namespace App\Http\Resources\Customer;

use App\Enums\ProductImageSourceType;
use App\Http\Resources\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends StoreResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alt_text' => $this->alt_text,
            'is_thumbnail' => $this->is_thumbnail,
            'display_order' => $this->display_order,
            'url' => $this->source_type === ProductImageSourceType::Upload ? Storage::disk('public')->url($this->storage_path) : $this->external_url,
        ];
    }
}
