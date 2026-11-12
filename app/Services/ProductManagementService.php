<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Money;
use App\Support\ShoeSize;
use App\Support\ShoeSizeSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProductManagementService
{
    public function __construct(private readonly MediaService $media) {}

    /** Preserve numeric form indexes through multipart merging and validated-data reconstruction. @param array<string,mixed> $data @return array<string,mixed> */
    public function orderedInput(array $data): array
    {
        if (is_array($data['colors'] ?? null)) {
            ksort($data['colors'], SORT_NUMERIC);
            foreach ($data['colors'] as &$color) {
                if (! is_array($color)) {
                    continue;
                }
                foreach (['variants', 'images'] as $field) {
                    if (is_array($color[$field] ?? null)) {
                        ksort($color[$field], SORT_NUMERIC);
                    }
                }
            }
            unset($color);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function rules(?Product $product = null): array
    {
        $rules = ['name' => ['required', 'string', 'max:150'], 'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('products', 'slug')->ignore($product?->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_line' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:20000'],
            'base_price' => ['required', 'integer', 'min:1'], 'promotion_price' => ['nullable', 'integer', 'min:1', 'lte:base_price'],
            'warranty_months' => ['required', 'integer', 'between:0,120'], 'exchange_days' => ['required', 'integer', 'between:0,365'],
            'is_active' => ['required', 'boolean'], 'is_featured' => ['sometimes', 'boolean'],
            'classification_ids' => ['nullable', 'array'], 'classification_ids.*' => ['integer', Rule::exists('classifications', 'id')],
            'colors' => ['required', 'array', 'list', 'min:1', 'max:20'], 'colors.*.id' => ['nullable', 'integer', 'min:1'],
            'colors.*.style_color_code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'colors.*.color_name' => ['required', 'string', 'max:100'], 'colors.*.color_key' => ['required', Rule::in(array_keys(config('store.palette')))],
            'colors.*.primary_hex' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/D'], 'colors.*.is_active' => ['sometimes', 'boolean'],
            'colors.*.variants' => ['nullable', 'array', 'list', 'max:100'], 'colors.*.range_start' => ['nullable', 'string'], 'colors.*.range_end' => ['nullable', 'string'],
            'colors.*.sku_prefix' => ['nullable', 'string', 'max:85', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'colors.*.variants.*.id' => ['nullable', 'integer', 'min:1'], 'colors.*.variants.*.size_value' => ['required'],
            'colors.*.variants.*.sku' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'colors.*.variants.*.price_override' => ['nullable', 'integer', 'min:1'], 'colors.*.variants.*.is_active' => ['sometimes', 'boolean'],
            'colors.*.images' => ['required', 'array', 'list', 'min:1', 'max:20'], 'colors.*.images.*.id' => ['nullable', 'integer', 'min:1'],
            'colors.*.images.*.file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'colors.*.images.*.external_url' => ['nullable', 'string', 'max:1000', 'url:https'],
            'colors.*.images.*.alt_text' => ['required', 'string', 'max:255'], 'colors.*.images.*.is_thumbnail' => ['required', 'boolean']];
        foreach (['stock_qty', 'reserved_qty', 'quarantine_qty', 'average_cost', 'unit_cost_basis', 'size_numeric'] as $field) {
            $rules[$field] = ['prohibited'];
            $rules['colors.*.variants.*.'.$field] = ['prohibited'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $input */
    public function save(User $actor, ?Product $product, array $input): Product
    {
        DB::transaction(fn () => ProductManagementAccess::lock($actor));
        $data = Validator::make($this->orderedInput($input), $this->rules($product))->validate();
        $data['base_price'] = Money::integer($data['base_price']);
        $data['promotion_price'] = isset($data['promotion_price']) ? Money::integer($data['promotion_price']) : null;
        if (! empty($data['classification_ids'])) {
            $existingIds = $product?->exists ? $product->classifications()->pluck('classifications.id')->all() : [];
            $newIds = array_diff($data['classification_ids'], $existingIds);
            if (! empty($newIds)) {
                $inactiveNames = Classification::query()
                    ->whereIn('id', $newIds)
                    ->where('is_active', false)
                    ->pluck('name')
                    ->all();
                if (! empty($inactiveNames)) {
                    $this->invalid('classification_ids', 'Không thể gắn mới nhãn đang tạm ngưng: '.implode(', ', $inactiveNames).'.');
                }
            }
        }
        $paths = [];
        try {
            foreach ($data['colors'] as $ci => &$color) {
                $variants = $color['variants'] ?? [];
                if (! empty($color['range_start']) || ! empty($color['range_end'])) {
                    if ($variants || empty($color['range_start']) || empty($color['range_end']) || empty($color['sku_prefix'])) {
                        $this->invalid('colors.'.$ci.'.variants', 'Chọn dải size hoặc từng SKU, không nhập cả hai.');
                    }
                    try {
                        $sizes = ShoeSizeSequence::between($color['range_start'], $color['range_end']);
                    } catch (\InvalidArgumentException|DomainException) {
                        $this->invalid('colors.'.$ci.'.range_start', 'Dải size không hợp lệ.');
                    }
                    if (count($sizes) > 100) {
                        $this->invalid('colors.'.$ci.'.range_end', 'Tối đa100 size cho mỗi màu.');
                    }
                    $variants = array_map(fn ($size) => ['size_value' => $size, 'sku' => $color['sku_prefix'].'-'.str_replace('.', '_', $size), 'price_override' => null], $sizes);
                }
                if (! $variants) {
                    $this->invalid('colors.'.$ci.'.variants', 'Mỗi màu cần ít nhất một size.');
                }
                $seen = [];
                foreach ($variants as $vi => &$variant) {
                    try {
                        $variant['size_value'] = ShoeSize::normalize($variant['size_value']);
                    } catch (DomainException) {
                        $this->invalid("colors.$ci.variants.$vi.size_value", 'Size phải là số nguyên hoặc nửa size.');
                    }
                    if (isset($seen[$variant['size_value']])) {
                        $this->invalid("colors.$ci.variants.$vi.size_value", 'Size bị trùng sau chuẩn hóa.');
                    }
                    $seen[$variant['size_value']] = true;
                    $variant['price_override'] = isset($variant['price_override']) ? Money::integer($variant['price_override']) : null;
                }
                unset($variant);
                $color['variants'] = $variants;
                if (array_sum(array_column($color['images'], 'is_thumbnail')) !== 1) {
                    $this->invalid("colors.$ci.images", 'Mỗi màu phải có đúng một ảnh đại diện.');
                }
                foreach ($color['images'] as $ii => &$image) {
                    $file = $image['file'] ?? null;
                    $url = $image['external_url'] ?? null;
                    if ($file && $url) {
                        $this->invalid("colors.$ci.images.$ii", 'Chỉ chọn một nguồn ảnh.');
                    }
                    if (! $file && ! $url && empty($image['id'])) {
                        $this->invalid("colors.$ci.images.$ii", 'Ảnh cần tệp JPEG/PNG/WebP hoặc URL HTTPS.');
                    }
                    if ($url && (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
                        $this->invalid("colors.$ci.images.$ii.external_url", 'URL không được chứa tài khoản hoặc mật khẩu.');
                    }
                    if ($file instanceof UploadedFile) {
                        $image['_path'] = $this->media->stage($file);
                        $paths[] = $image['_path'];
                    }
                }
                unset($image);
            }
            unset($color);

            return DB::transaction(function () use ($actor, $product, $data): Product {
                ProductManagementAccess::lock($actor);
                $category = Category::whereKey($data['category_id'])->lockForUpdate()->firstOrFail();
                $current = $product ? Product::whereKey($product->id)->lockForUpdate()->firstOrFail() : new Product;
                $current->forceFill(array_intersect_key($data, array_flip(['name', 'slug', 'brand_line', 'description', 'base_price', 'promotion_price', 'warranty_months', 'exchange_days', 'is_active', 'is_featured'])));
                $current->category_id = $category->id;
                $current->save();
                if (array_key_exists('classification_ids', $data)) {
                    $current->classifications()->sync($data['classification_ids'] ?? []);
                }
                $colors = ProductColor::where('product_id', $current->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $allVariants = ProductVariant::whereIn('product_color_id', $colors->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $seenColors = [];
                foreach ($data['colors'] as $ci => $inputColor) {
                    $color = empty($inputColor['id']) ? new ProductColor : $colors->get((int) $inputColor['id']);
                    abort_unless($color, 404);
                    if ($color->exists && isset($seenColors[$color->id])) {
                        $this->invalid('colors', 'Mã phối màu bị lặp.');
                    }
                    if ($color->exists && $color->style_color_code !== $inputColor['style_color_code']) {
                        foreach ($allVariants->where('product_color_id', $color->id) as $variant) {
                            if ($this->documented($variant)) {
                                $this->invalid('colors', 'Không đổi mã màu đã có chứng từ.');
                            }
                        }
                    }
                    $color->forceFill(array_intersect_key($inputColor, array_flip(['style_color_code', 'color_name', 'color_key', 'primary_hex'])));
                    $color->product_id = $current->id;
                    $color->display_order = $ci + 1;
                    $color->is_active = $inputColor['is_active'] ?? true;
                    $color->save();
                    $seenColors[$color->id] = true;
                    $seenVariants = [];
                    foreach ($inputColor['variants'] as $inputVariant) {
                        $variant = empty($inputVariant['id']) ? new ProductVariant : $allVariants->get((int) $inputVariant['id']);
                        abort_unless($variant && (! $variant->exists || $variant->product_color_id === $color->id), 404);
                        if ($variant->exists && isset($seenVariants[$variant->id])) {
                            $this->invalid('colors', 'SKU ID bị lặp.');
                        }
                        if ($variant->exists && ($variant->sku !== $inputVariant['sku'] || $variant->size_value !== $inputVariant['size_value']) && $this->documented($variant)) {
                            $this->invalid('colors', 'Không đổi SKU/size đã có chứng từ.');
                        }
                        $variant->product_color_id = $color->id;
                        $variant->sku = $inputVariant['sku'];
                        $variant->size_value = $inputVariant['size_value'];
                        $variant->size_numeric = $inputVariant['size_value'];
                        $variant->price_override = $inputVariant['price_override'];
                        $variant->is_active = ($inputVariant['is_active'] ?? true) && $color->is_active;
                        $variant->save();
                        $seenVariants[$variant->id] = true;
                    }
                    ProductVariant::where('product_color_id', $color->id)->whereNotIn('id', array_keys($seenVariants))->update(['is_active' => false]);
                    $images = ProductImage::where('product_color_id', $color->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    ProductImage::where('product_color_id', $color->id)->update(['is_thumbnail' => false]);
                    foreach ($images as $storedImage) {
                        $storedImage->is_thumbnail = false;
                        $storedImage->syncOriginalAttribute('is_thumbnail');
                    }
                    $seenImages = [];
                    foreach ($inputColor['images'] as $ii => $inputImage) {
                        $image = empty($inputImage['id']) ? new ProductImage : $images->get((int) $inputImage['id']);
                        abort_unless($image, 404);
                        if ($image->exists && isset($seenImages[$image->id])) {
                            $this->invalid('colors', 'Ảnh ID bị lặp.');
                        }
                        $image->product_color_id = $color->id;
                        if (isset($inputImage['_path'])) {
                            $image->source_type = 'UPLOAD';
                            $image->storage_path = $inputImage['_path'];
                            $image->external_url = null;
                        } elseif (! empty($inputImage['external_url'])) {
                            $image->source_type = 'EXTERNAL_URL';
                            $image->external_url = $inputImage['external_url'];
                            $image->storage_path = null;
                        }
                        $image->alt_text = $inputImage['alt_text'];
                        $image->is_thumbnail = $inputImage['is_thumbnail'];
                        $image->display_order = $ii + 1;
                        $image->save();
                        $seenImages[$image->id] = true;
                    }
                    ProductImage::where('product_color_id', $color->id)->whereNotIn('id', array_keys($seenImages))->delete();
                }
                $removed = $colors->keys()->diff(array_keys($seenColors));
                ProductColor::whereIn('id', $removed)->update(['is_active' => false]);
                ProductVariant::whereIn('product_color_id', $removed)->update(['is_active' => false]);

                return $current->refresh();
            }, 3);
        } catch (Throwable $exception) {
            $this->media->discard($paths);
            if ($exception instanceof UniqueConstraintViolationException) {
                $this->invalid('colors', 'Slug, mã màu, SKU hoặc size đã được sử dụng.');
            }
            throw $exception;
        }
    }

    public function setActive(User $actor, Product $product, bool $active): Product
    {
        return DB::transaction(function () use ($actor, $product, $active): Product {
            ProductManagementAccess::lock($actor);
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['is_active' => $active])->save();

            return $locked->refresh();
        }, 3);
    }

    public function deactivate(User $actor, Product $product): void
    {
        $this->setActive($actor, $product, false);
    }

    private function documented(ProductVariant $variant): bool
    {
        return $variant->stockImportItems()->exists() || $variant->orderItems()->exists() || $variant->inventoryMovements()->exists();
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
