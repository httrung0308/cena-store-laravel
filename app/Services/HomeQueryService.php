<?php

namespace App\Services;

use App\Enums\ClassificationGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class HomeQueryService
{
    public const FALLBACK_IMAGE = 'legacy/img/men.jpg';

    /**
     * @return array{
     *     banners: Collection<int, Banner>,
     *     categories: list<array<string, mixed>>,
     *     genderCategories: list<array<string, mixed>>,
     *     silhouettes: Collection<int, Classification>,
     *     activities: Collection<int, Classification>,
     *     featuredProducts: list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>,
     *     saleProducts: list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>,
     *     newProducts: list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>,
     *     articles: Collection<int, Article>
     * }
     */
    public function data(): array
    {
        $categories = $this->categories();

        // 1. Lấy 8 sản phẩm nổi bật
        $featured = $this->featuredProducts(8);
        $featuredIds = array_map(fn (array $item): int => (int) $item['product']['id'], $featured);

        // 2. Lấy 8-10 sản phẩm giảm giá, ưu tiên loại trừ sản phẩm đã xuất hiện ở Featured
        $sale = $this->saleProducts(10, $featuredIds);
        $saleIds = array_map(fn (array $item): int => (int) $item['product']['id'], $sale);

        // 3. Lấy 6 sản phẩm mới đăng bán, ưu tiên loại trừ sản phẩm đã xuất hiện ở Featured và Sale
        $excludedForNew = array_values(array_unique(array_merge($featuredIds, $saleIds)));
        $new = $this->newProducts(6, $excludedForNew);

        return [
            'banners' => $this->banners(),
            'categories' => $categories,
            'genderCategories' => $categories,
            'silhouettes' => $this->silhouettes(),
            'activities' => $this->activities(),
            'featuredProducts' => $featured,
            'saleProducts' => $sale,
            'newProducts' => $new,
            'articles' => $this->latestArticles(6),
        ];
    }

    /** @return Collection<int, Banner> */
    public function banners(): Collection
    {
        return Banner::query()->active()->ordered()->get();
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return [
            [
                'name' => 'Giày Nam',
                'slug' => 'men',
                'tagline' => 'Vững vàng & Tăng tốc',
                'image' => asset('legacy/img/men.jpg'),
                'url' => route('shoes.index', ['gender' => 'men']),
                'badge' => 'HOT',
            ],
            [
                'name' => 'Giày Nữ',
                'slug' => 'women',
                'tagline' => 'Thanh thoát & Êm dịu',
                'sr_label' => 'Fresh summer collection avaible now',
                'image' => asset('legacy/img/women.jpg'),
                'url' => route('shoes.index', ['gender' => 'women']),
                'badge' => '-25%',
            ],
            [
                'name' => 'Trẻ Em',
                'slug' => 'kids',
                'tagline' => 'Bảo vệ & Nâng niu',
                'image' => asset('legacy/img/kid.jpg'),
                'url' => route('shoes.index', ['gender' => 'kids']),
                'badge' => null,
            ],
            [
                'name' => 'Unisex',
                'slug' => 'unisex',
                'tagline' => 'Linh hoạt phố thị',
                'image' => asset('legacy/img/unisex.jpg'),
                'url' => route('shoes.index', ['gender' => 'unisex']),
                'badge' => null,
            ],
        ];
    }

    /** @return Collection<int, Classification> */
    public function silhouettes(): Collection
    {
        return Classification::query()
            ->group(ClassificationGroup::Silhouette)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Classification> */
    public function activities(): Collection
    {
        return Classification::query()
            ->group(ClassificationGroup::Activity)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** @return list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}> */
    public function featuredProducts(int $limit = 8): array
    {
        return $this->baseProductQuery()
            ->where('products.is_featured', true)
            ->orderByDesc('products.created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p): array => $this->transformProduct($p))
            ->all();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>
     */
    public function saleProducts(int $limit = 10, array $excludeIds = []): array
    {
        $query = $this->baseProductQuery()
            ->whereNotNull('products.promotion_price')
            ->whereRaw('products.promotion_price < products.base_price');

        if (! empty($excludeIds)) {
            $query->whereNotIn('products.id', $excludeIds);
        }

        $items = $query->orderByRaw('((products.base_price - products.promotion_price) / products.base_price) DESC')
            ->limit($limit)
            ->get();

        // Nếu sau khi loại trừ không đủ tối thiểu 4 sản phẩm, lấy thêm bù từ danh sách sale ban đầu
        if ($items->count() < 4 && ! empty($excludeIds)) {
            $needed = $limit - $items->count();
            $fallback = $this->baseProductQuery()
                ->whereNotNull('products.promotion_price')
                ->whereRaw('products.promotion_price < products.base_price')
                ->whereIn('products.id', $excludeIds)
                ->orderByRaw('((products.base_price - products.promotion_price) / products.base_price) DESC')
                ->limit($needed)
                ->get();
            $items = $items->merge($fallback);
        }

        return $items->map(fn (Product $p): array => $this->transformProduct($p))->all();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return list<array{product: array<string, mixed>, detailUrl: string, imageUrl: string}>
     */
    public function newProducts(int $limit = 6, array $excludeIds = []): array
    {
        $query = $this->baseProductQuery();

        if (! empty($excludeIds)) {
            $query->whereNotIn('products.id', $excludeIds);
        }

        $items = $query->orderByDesc('products.created_at')
            ->limit($limit)
            ->get();

        // Nếu sau khi loại trừ không đủ tối thiểu 4 sản phẩm, lấy bù các sản phẩm mới khác
        if ($items->count() < 4 && ! empty($excludeIds)) {
            $needed = $limit - $items->count();
            $fallback = $this->baseProductQuery()
                ->whereIn('products.id', $excludeIds)
                ->orderByDesc('products.created_at')
                ->limit($needed)
                ->get();
            $items = $items->merge($fallback);
        }

        return $items->map(fn (Product $p): array => $this->transformProduct($p))->all();
    }

    /** @return Collection<int, Article> */
    public function latestArticles(int $limit = 6): Collection
    {
        return Article::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'published_at']);
    }

    /** @return Builder<Product> */
    private function baseProductQuery(): Builder
    {
        return Product::query()
            ->where('products.is_active', true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true)
                ->where(fn (Builder $root) => $root->whereNull('parent_id')->orWhereHas('parent', fn (Builder $parent) => $parent->where('is_active', true))))
            ->whereHas('colors', fn (Builder $color) => $color
                ->where('is_active', true)
                ->whereHas('thumbnail')
                ->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)))
            ->with([
                'category',
                'colors' => fn ($color) => $color->where('is_active', true)->whereHas('thumbnail')->orderBy('display_order')->orderBy('id'),
                'colors.thumbnail',
                'colors.variants' => fn ($variant) => $variant->where('is_active', true)->select(['id', 'product_color_id', 'size_value', 'price_override', 'is_active', 'stock_qty', 'reserved_qty']),
            ]);
    }

    /** @return array{product: array<string, mixed>, detailUrl: string, imageUrl: string} */
    private function transformProduct(Product $product): array
    {
        $colors = [];
        $available = false;

        foreach ($product->colors as $color) {
            $hasStock = $color->variants->contains(fn (ProductVariant $v): bool => $v->stock_qty > $v->reserved_qty);
            $available = $available || $hasStock;

            $colors[] = [
                'id' => $color->id,
                'name' => $color->color_name,
                'hex' => $color->primary_hex,
                'style_color_code' => $color->style_color_code,
                'image_url' => $this->imageUrl($color->thumbnail, 64),
                'card_image_url' => $this->imageUrl($color->thumbnail, 400),
                'url' => route('shoes.show', ['slug' => $product->slug, 'style_color' => $color->style_color_code]),
            ];
        }

        $basePrice = (int) $product->base_price;
        $effectivePrice = Money::integer($product->promotion_price ?? $basePrice);
        $hasDiscount = is_numeric($product->promotion_price) && ((int) $product->promotion_price < $basePrice);
        $discountPercent = $hasDiscount && $basePrice > 0 ? (int) round((($basePrice - (int) $product->promotion_price) / $basePrice) * 100) : 0;

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'brand_line' => $product->brand_line,
                'base_price' => $basePrice,
                'effective_price' => $effectivePrice,
                'sold_out' => ! $available,
                'from_price' => false,
                'has_discount' => $hasDiscount,
                'discount_percent' => $discountPercent,
                'colors' => $colors,
            ],
            'detailUrl' => $colors[0]['url'] ?? route('shoes.show', $product->slug),
            'imageUrl' => $colors[0]['card_image_url'] ?? $this->imageUrl($product->colors->first()?->thumbnail, 400),
        ];
    }

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
}
