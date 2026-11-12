<?php

namespace App\Services;

use App\Enums\ClassificationGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only public catalogue (PR-X4). Every filter is applied to one variant row at a time, so a product
 * only matches when a single SKU satisfies colour + size + price + promotion + stock together.
 * Results are plain arrays with whitelisted fields; models are never handed to the browser.
 */
final class CatalogQueryService
{
    public const PER_PAGE = 9;

    public const FALLBACK_IMAGE = 'legacy/img/men.jpg';

    private const PRICE = 'COALESCE(v.price_override, products.promotion_price, products.base_price)';

    /**
     * @param  array{search?: string, category?: string, gender?: string, colors?: list<string>, size?: string, min_price?: int, max_price?: int, promotion?: bool, in_stock?: bool, sort?: string, page?: int}  $filters
     * @return LengthAwarePaginator<int, array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>
     */
    public function listing(array $filters): LengthAwarePaginator
    {
        $matches = $this->matchingVariants($filters);
        $query = $this->publicProducts()
            ->whereExists((clone $matches)->selectRaw('1'))
            ->select('products.*')
            ->selectSub((clone $matches)->selectRaw('MIN('.self::PRICE.')'), 'matching_price');

        if (! empty($filters['gender'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('gender_group', strtoupper($filters['gender'])));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where(fn (Builder $slug) => $slug
                ->where('slug', $filters['category'])
                ->orWhereHas('parent', fn (Builder $parent) => $parent->where('slug', $filters['category']))));
        }
        if (! empty($filters['silhouette'])) {
            $query->whereHas('classifications', fn (Builder $q) => $q
                ->where('classifications.classification_group', ClassificationGroup::Silhouette->value)
                ->where('classifications.is_active', true)
                ->whereIn('classifications.slug', (array) $filters['silhouette'])
            );
        }
        if (! empty($filters['activity'])) {
            $query->whereHas('classifications', fn (Builder $q) => $q
                ->where('classifications.classification_group', ClassificationGroup::Activity->value)
                ->where('classifications.is_active', true)
                ->whereIn('classifications.slug', (array) $filters['activity'])
            );
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('matching_price'),
            'price_desc' => $query->orderByDesc('matching_price'),
            'name_asc' => $query->orderBy('products.name'),
            'name_desc' => $query->orderByDesc('products.name'),
            default => $query->orderByDesc('products.created_at'),
        };

        $page = $query->orderBy('products.id')
            ->with($this->relations())
            ->paginate(self::PER_PAGE, ['*'], 'page', $filters['page'] ?? 1)
            ->withQueryString();

        $ids = $page->getCollection()->modelKeys();
        $matchedVariantIds = $ids === [] ? [] : (clone $matches)
            ->join('products', 'products.id', '=', 'c.product_id')
            ->whereIn('products.id', $ids)
            ->pluck('v.id')->flip()->all();

        return $page->through(fn (Product $product): array => $this->card($product, $matchedVariantIds));
    }

    /**
     * @return array{product: array<string, mixed>, colors: list<array<string, mixed>>, variants: list<array<string, mixed>>, albums: array<string, list<array{id: int, url: string, thumb_url: string, alt: string}>>, initialColorId: int}
     */
    public function detail(string $slug, ?string $styleColor = null): array
    {
        $product = $this->publicProducts()
            ->where('slug', $slug)
            ->with($this->relations())
            ->with(['activeClassifications' => fn ($q) => $q->orderBy('classification_group')->orderBy('sort_order')->orderBy('name')])
            ->firstOrFail();

        $colors = [];
        $variants = [];
        $albums = [];
        $initialColorId = null;
        $maxPerVariant = (int) config('store.limits.variant_quantity', 10);

        foreach ($product->colors as $color) {
            $colorData = $this->color($product, $color);
            $colorAvailable = 0;

            $albums[(string) $color->id] = $color->images
                ->map(fn (ProductImage $image): array => ['id' => $image->id, 'url' => $this->imageUrl($image), 'thumb_url' => $this->imageUrl($image, 400), 'alt' => $image->alt_text ?? $product->name])
                ->all();

            foreach ($color->variants as $variant) {
                $available = max(0, $variant->stock_qty - $variant->reserved_qty);
                $colorAvailable += $available;
                if ($initialColorId === null && $available > 0) {
                    $initialColorId = $color->id;
                }
                $price = $this->price($product, $variant);
                $hasDiscount = $price < $product->base_price && $product->base_price > 0;
                $discountPercent = $hasDiscount ? (int) round((($product->base_price - $price) / $product->base_price) * 100) : 0;

                $variants[] = [
                    'id' => $variant->id,
                    'product_color_id' => $color->id,
                    'size_value' => $variant->size_value,
                    'is_active' => true,
                    'available_qty' => $available,
                    'max_cart_qty' => min($available, $maxPerVariant),
                    'effective_price' => $price,
                    'price_label' => $this->formatVnd($price),
                    'base_price_label' => $hasDiscount ? $this->formatVnd($product->base_price) : null,
                    'discount_percent' => $discountPercent,
                ];
            }

            $colorData['has_available'] = $colorAvailable > 0;
            $colors[] = $colorData;
        }

        if ($styleColor !== null && $styleColor !== '') {
            $chosen = collect($colors)->firstWhere('style_color_code', $styleColor);
            abort_if($chosen === null, 404);
            $initialColorId = $chosen['id'];
        }

        $prices = array_column($variants, 'effective_price');
        $minPrice = $prices !== [] ? min($prices) : Money::integer($product->base_price);
        $maxPrice = $prices !== [] ? max($prices) : $minPrice;
        $genderSlug = strtolower($product->category->gender_group?->value ?? '');

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand_line' => $product->brand_line,
                'description' => $product->description,
                'specifications' => $product->specifications,
                'category_id' => $product->category_id,
                'category' => $product->category->name,
                'category_slug' => $product->category->slug,
                'gender_slug' => $genderSlug,
                'category_url' => $product->category->parent_id !== null
                    ? route('shoes.index', ['category' => $product->category->slug])
                    : ($genderSlug !== '' ? route('shoes.index', ['gender' => $genderSlug]) : route('shoes.index')),
                'classifications' => $product->activeClassifications->map(fn (Classification $c): array => [
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'group' => $c->classification_group->value,
                    'url' => $c->classification_group === ClassificationGroup::Silhouette
                        ? route('shoes.index', ['silhouette' => [$c->slug]])
                        : route('shoes.index', ['activity' => [$c->slug]]),
                ])->values()->all(),
                'warranty_months' => $product->warranty_months,
                'exchange_days' => $product->exchange_days,
                'rating_avg' => (float) $product->rating_avg,
                'reviews_count' => (int) $product->reviews_count,
                'price_label' => $this->formatVnd($minPrice),
                'max_price_label' => $this->formatVnd($maxPrice),
                'price_varies' => $minPrice !== $maxPrice,
                'free_shipping_threshold' => config('store.shipping.free_threshold'),
            ],
            'colors' => $colors,
            'variants' => $variants,
            'albums' => $albums,
            'initialColorId' => $initialColorId ?? $colors[0]['id'],
        ];
    }

    /**
     * Public active products in the same category/gender for cross-discovery below the detail fold.
     *
     * @return list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>
     */
    public function relatedProducts(int $productId, int $categoryId, string $genderGroup = '', int $limit = 4): array
    {
        $matches = $this->matchingVariants([]);
        $query = $this->publicProducts()
            ->where('products.id', '!=', $productId)
            ->whereExists((clone $matches)->selectRaw('1'))
            ->select('products.*')
            ->selectSub((clone $matches)->selectRaw('MIN('.self::PRICE.')'), 'matching_price')
            ->with($this->relations())
            ->orderByRaw('CASE WHEN products.category_id = ? THEN 0 ELSE 1 END', [$categoryId])
            ->when($genderGroup !== '', fn (Builder $q) => $q->whereHas('category', fn (Builder $c) => $c->where('gender_group', strtoupper($genderGroup))))
            ->orderByDesc('products.is_featured')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get();

        $allVariantIds = $query
            ->flatMap(fn (Product $p) => $p->colors->flatMap(fn (ProductColor $c) => $c->variants->pluck('id')))
            ->flip()
            ->all();

        return $query->map(fn (Product $p): array => $this->card($p, $allVariantIds))->all();
    }

    /**
     * @return array{genderOptions: list<array{value: string, label: string}>, colorOptions: list<array{value: string, label: string}>, categoryOptions: list<array{slug: string, name: string}>, silhouetteOptions: list<array{value: string, label: string}>, activityOptions: list<array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->where(fn (Builder $root) => $root->whereNull('parent_id')->orWhereHas('parent', fn (Builder $parent) => $parent->where('is_active', true)))
            ->orderBy('display_order')->orderBy('id')
            ->get(['id', 'slug', 'name', 'parent_id']);

        return [
            'genderOptions' => [
                ['value' => '', 'label' => 'Tất cả'],
                ['value' => 'men', 'label' => 'Nam'],
                ['value' => 'women', 'label' => 'Nữ'],
                ['value' => 'kids', 'label' => 'Trẻ em'],
                ['value' => 'unisex', 'label' => 'Unisex'],
            ],
            'colorOptions' => collect(config('store.palette'))
                ->map(fn (array $palette, string $key): array => [
                    'value' => $key,
                    'label' => $this->colorLabel($key, $palette['label']),
                    'hex' => $palette['hex'],
                ])
                ->values()->all(),
            'categoryOptions' => $categories
                ->map(fn (Category $category): array => [
                    'slug' => $category->slug,
                    'name' => ($category->parent_id ? '— ' : '').$category->name,
                    'is_root_gender' => $category->parent_id === null && in_array($category->slug, ['men', 'women', 'kids', 'unisex'], true),
                ])
                ->all(),
            'silhouetteOptions' => Classification::query()
                ->where('classification_group', ClassificationGroup::Silhouette->value)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (Classification $c): array => ['value' => $c->slug, 'label' => $c->name])
                ->all(),
            'activityOptions' => Classification::query()
                ->where('classification_group', ClassificationGroup::Activity->value)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (Classification $c): array => ['value' => $c->slug, 'label' => $c->name])
                ->all(),
        ];
    }

    /**
     * @param  array<int, int>  $matchedVariantIds  variant id => index, from the same SQL that filtered the page
     * @return array{product: array<string, mixed>, detailUrl: string, imageUrl: string}
     */
    private function card(Product $product, array $matchedVariantIds): array
    {
        $colors = [];
        $available = false;
        foreach ($product->colors as $color) {
            $variants = $color->variants->filter(fn (ProductVariant $variant): bool => isset($matchedVariantIds[$variant->id]));
            if ($variants->isEmpty()) {
                continue;
            }
            $available = $available || $variants->contains(fn (ProductVariant $variant): bool => $variant->stock_qty > $variant->reserved_qty);
            $colors[] = $this->color($product, $color);
        }

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'brand_line' => $product->brand_line,
                'base_price' => $product->base_price,
                'effective_price' => Money::integer($product->matching_price),
                'sold_out' => ! $available,
                'from_price' => true,
                'colors' => $colors,
            ],
            'detailUrl' => $colors[0]['url'] ?? route('shoes.show', $product->slug),
            'imageUrl' => $colors[0]['card_image_url'] ?? $this->imageUrl($product->colors->first()?->thumbnail, 400),
        ];
    }

    /** @return Builder<Product> */
    private function publicProducts(): Builder
    {
        return Product::query()
            ->where('products.is_active', true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true)
                ->where(fn (Builder $root) => $root->whereNull('parent_id')->orWhereHas('parent', fn (Builder $parent) => $parent->where('is_active', true))))
            ->whereHas('colors', fn (Builder $color) => $this->publicColor($color));
    }

    /**
     * @template TQuery of Builder<ProductColor>|HasMany<ProductColor, Product>
     *
     * @param  TQuery  $color
     * @return TQuery
     */
    private function publicColor(Builder|HasMany $color): Builder|HasMany
    {
        return $color->where('is_active', true)
            ->whereHas('thumbnail')
            ->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true));
    }

    /** @return array<array-key, mixed> */
    private function relations(): array
    {
        return [
            'category',
            'colors' => fn ($color) => $this->publicColor($color)->orderBy('display_order')->orderBy('id'),
            'colors.thumbnail',
            'colors.images' => fn ($image) => $image->orderBy('display_order')->orderBy('id'),
            'colors.variants' => fn ($variant) => $variant->where('is_active', true)
                ->orderBy('size_numeric')->orderBy('id')
                ->select(['id', 'product_color_id', 'size_value', 'price_override', 'is_active', 'stock_qty', 'reserved_qty']),
        ];
    }

    /**
     * Correlated sub-query over public variants of the outer `products` row.
     *
     * @param  array<string, mixed>  $filters
     */
    private function matchingVariants(array $filters): QueryBuilder
    {
        $query = DB::table('product_variants as v')
            ->join('product_colors as c', 'c.id', '=', 'v.product_color_id')
            ->whereColumn('c.product_id', 'products.id')
            ->whereNull('v.deleted_at')->whereNull('c.deleted_at')
            ->where('v.is_active', true)->where('c.is_active', true)
            ->whereExists(fn (QueryBuilder $image) => $image->selectRaw('1')->from('product_images as i')
                ->whereColumn('i.product_color_id', 'c.id')->where('i.is_thumbnail', true));

        if (! empty($filters['search'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $query->where(fn (QueryBuilder $search) => $search
                ->whereRaw("products.name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("c.style_color_code LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (! empty($filters['colors'])) {
            $query->whereIn('c.color_key', $filters['colors']);
        }
        if (! empty($filters['size'])) {
            $query->where('v.size_value', $filters['size']);
        }
        if (! empty($filters['in_stock'])) {
            $query->whereColumn('v.stock_qty', '>', 'v.reserved_qty');
        }
        if (! empty($filters['promotion'])) {
            $query->whereRaw(self::PRICE.' < products.base_price');
        }
        if (isset($filters['min_price'])) {
            $query->whereRaw(self::PRICE.' >= ?', [$filters['min_price']]);
        }
        if (isset($filters['max_price'])) {
            $query->whereRaw(self::PRICE.' <= ?', [$filters['max_price']]);
        }

        return $query;
    }

    private function price(Product $product, ProductVariant $variant): int
    {
        return Money::integer($variant->price_override ?? $product->promotion_price ?? $product->base_price);
    }

    /** Server-formatted so prices above Number.MAX_SAFE_INTEGER never pass through JS arithmetic. */
    private function formatVnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' ₫';
    }

    /** @return array{id: int, name: string, hex: ?string, style_color_code: string, image_url: string, card_image_url: string, url: string} */
    private function color(Product $product, ProductColor $color): array
    {
        return [
            'id' => $color->id,
            'name' => $color->color_name,
            'hex' => $color->primary_hex,
            'style_color_code' => $color->style_color_code,
            'image_url' => $this->imageUrl($color->thumbnail, 64),
            'card_image_url' => $this->imageUrl($color->thumbnail, 400),
            'url' => route('shoes.show', ['slug' => $product->slug, 'style_color' => $color->style_color_code]),
        ];
    }

    /** Safe public URL of a stored or HTTPS image, else the local fallback asset. */
    public function imageUrl(?ProductImage $image, ?int $size = null): string
    {
        if ($image === null) {
            return asset(self::FALLBACK_IMAGE);
        }

        if ($image->source_type === ProductImageSourceType::ExternalUrl) {
            if (is_string($image->external_url)
                && filter_var($image->external_url, FILTER_VALIDATE_URL) && parse_url($image->external_url, PHP_URL_SCHEME) === 'https'
                && parse_url($image->external_url, PHP_URL_USER) === null && parse_url($image->external_url, PHP_URL_PASS) === null) {
                return $image->external_url;
            }

            return asset(self::FALLBACK_IMAGE);
        }

        if ($size !== null) {
            return $image->thumbnailUrl($size);
        }

        if ($image->source_type === ProductImageSourceType::Upload && is_string($image->storage_path)
            && preg_match('~^products/[A-Za-z0-9]{40}\.(?:jpe?g|png|webp)$~D', $image->storage_path)) {
            return Storage::disk('public')->url($image->storage_path);
        }

        return asset(self::FALLBACK_IMAGE);
    }

    /** Vietnamese display label for canonical color palette keys. */
    private function colorLabel(string $key, string $fallback): string
    {
        return match ($key) {
            'red' => 'Đỏ',
            'blue' => 'Xanh dương',
            'green' => 'Xanh lá',
            'yellow' => 'Vàng',
            'black' => 'Đen',
            'white' => 'Trắng',
            'gray' => 'Xám',
            'pink' => 'Hồng',
            'purple' => 'Tím',
            'orange' => 'Cam',
            'brown' => 'Nâu',
            'lime' => 'Xanh chanh',
            default => $fallback,
        };
    }
}
