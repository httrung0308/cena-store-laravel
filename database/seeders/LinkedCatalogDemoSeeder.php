<?php

namespace Database\Seeders;

use App\Enums\GenderGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductManagementService;
use App\Services\StockImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LogicException;

/**
 * Local/testing only: builds demo products from a text file of image links, one product per line:
 *
 *   men | Air Runner 1 | https://example.com/side.jpg https://example.com/top.jpg | white | 2390000
 *
 * Group is men|women|kids|unisex; colour (palette key) and base price are optional. The third field holds one
 * or more HTTPS links separated by spaces (links may contain commas): the first is the thumbnail, the rest
 * complete the album. Images stay external links (nothing is downloaded or stored). Stock comes from a real
 * posted stock import per product. Re-running adds new lines and re-syncs the album of products this seeder
 * created; prices, names and stock of existing products are left alone.
 */
class LinkedCatalogDemoSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const SIZES = [
        'men' => ['39', '40', '41', '42', '43', '44'],
        'women' => ['36', '37', '38', '39', '40'],
        'kids' => ['30', '31', '32', '33', '34', '35'],
        'unisex' => ['37', '38', '39', '40', '41', '42'],
    ];

    public function run(ProductManagementService $products, StockImportService $imports): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }
        $file = (string) config('store.demo.catalog_file');
        if ($file === '' || ! is_file($file)) {
            throw new LogicException('Set STORE_DEMO_CATALOG_FILE to the image-link list before running this seeder.');
        }
        $this->call(ProductDemoTaxonomySeeder::class);
        $actor = User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->orderBy('id')->firstOrFail();
        $supplier = Supplier::where('code', 'DEMO-NCC-1')->firstOrFail();

        $created = 0;
        $synced = 0;
        $skipped = 0;
        foreach ($this->entries($file) as $entry) {
            $existing = Product::withTrashed()->where('slug', $entry['slug'])->first();
            if ($existing !== null) {
                $this->syncAlbum($products, $actor, $existing, $entry) ? $synced++ : $skipped++;

                continue;
            }
            $product = $products->save($actor, null, [
                'name' => $entry['name'],
                'slug' => $entry['slug'],
                'category_id' => Category::where('slug', $entry['group'])->firstOrFail()->id,
                'brand_line' => 'Nike · Sản phẩm minh họa',
                'description' => 'Sản phẩm phục vụ demo đồ án; ảnh hiển thị bằng liên kết ngoài.',
                'base_price' => $entry['base_price'],
                'promotion_price' => $entry['promotion_price'],
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'colors' => [[
                    'style_color_code' => $entry['code'],
                    'color_name' => (string) config('store.palette.'.$entry['color'].'.label'),
                    'color_key' => $entry['color'],
                    'primary_hex' => (string) config('store.palette.'.$entry['color'].'.hex'),
                    'is_active' => 1,
                    'variants' => array_map(fn (string $size): array => ['size_value' => $size, 'sku' => $entry['code'].'-'.$size, 'price_override' => null, 'is_active' => 1], self::SIZES[$entry['group']]),
                    'images' => $this->images($entry, []),
                ]],
            ]);
            $variants = ProductVariant::query()->whereHas('color', fn ($query) => $query->where('product_id', $product->id))->orderBy('id')->get();
            $import = $imports->saveDraft($actor, null, [
                'command_key' => (string) Str::uuid(),
                'supplier_id' => $supplier->id,
                'note' => 'Nhập kho demo cho '.$entry['name'],
                'items' => $variants->map(fn (ProductVariant $variant): array => ['variant_id' => $variant->id, 'quantity' => 8, 'unit_cost_amount' => intdiv($entry['base_price'] * 68, 100_000) * 1_000])->all(),
            ]);
            $imports->post($actor, $import);
            $created++;
        }
        $this->command?->info("Đã tạo {$created} sản phẩm, cập nhật album {$synced} sản phẩm, giữ nguyên {$skipped} sản phẩm.");
    }

    /** Replace the album of a product this seeder created when the listed links differ; keeps everything else. */
    private function syncAlbum(ProductManagementService $products, User $actor, Product $product, array $entry): bool
    {
        $color = $product->colors()->with(['variants', 'images'])->orderBy('display_order')->first();
        if ($product->trashed() || $color === null || $color->style_color_code !== $entry['code']) {
            return false;
        }
        $current = $color->images->sortBy('display_order')->pluck('external_url')->values()->all();
        if ($current === $entry['urls']) {
            return false;
        }
        $products->save($actor, $product, [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'brand_line' => $product->brand_line,
            'description' => $product->description,
            'base_price' => $product->base_price,
            'promotion_price' => $product->promotion_price,
            'warranty_months' => $product->warranty_months,
            'exchange_days' => $product->exchange_days,
            'is_active' => $product->is_active ? 1 : 0,
            'colors' => [[
                'id' => $color->id,
                'style_color_code' => $color->style_color_code,
                'color_name' => $color->color_name,
                'color_key' => $color->color_key,
                'primary_hex' => $color->primary_hex,
                'is_active' => $color->is_active ? 1 : 0,
                'variants' => $color->variants->sortBy('id')->map(fn (ProductVariant $variant): array => ['id' => $variant->id, 'size_value' => $variant->size_value, 'sku' => $variant->sku, 'price_override' => $variant->price_override, 'is_active' => $variant->is_active ? 1 : 0])->values()->all(),
                'images' => $this->images($entry, $color->images->pluck('id', 'external_url')->all()),
            ]],
        ]);

        return true;
    }

    /**
     * @param  array<string, int>  $existingIds  external URL => image id already stored
     * @return list<array<string, mixed>>
     */
    private function images(array $entry, array $existingIds): array
    {
        $images = [];
        foreach ($entry['urls'] as $index => $url) {
            $images[] = array_filter(['id' => $existingIds[$url] ?? null], fn ($value) => $value !== null) + [
                'external_url' => $url,
                'alt_text' => $index === 0 ? $entry['name'] : $entry['name'].' — góc '.($index + 1),
                'is_thumbnail' => $index === 0,
            ];
        }

        return $images;
    }

    /** @return list<array{group: string, name: string, slug: string, urls: list<string>, color: string, base_price: int, promotion_price: ?int, code: string}> */
    private function entries(string $file): array
    {
        $entries = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            [$group, $name, $links] = array_pad($parts, 3, '');
            $urls = array_values(array_unique(preg_split('/\s+/', $links, -1, PREG_SPLIT_NO_EMPTY) ?: []));
            $group = strtolower($group);
            $group = $group === 'kid' ? 'kids' : $group;
            if (! in_array($group, array_map(fn (GenderGroup $gender): string => strtolower($gender->value), GenderGroup::cases()), true)
                || $name === '' || $urls === [] || count($urls) > 20
                || array_filter($urls, fn (string $url): bool => ! str_starts_with($url, 'https://') || mb_strlen($url) > 1000) !== []) {
                throw new LogicException('Dòng '.($number + 1).' không hợp lệ. Định dạng: nhóm | tên | https://ảnh-1 https://ảnh-2 ... | màu? | giá?');
            }
            $slug = 'demo-'.Str::slug($name);
            $color = strtolower($parts[3] ?? '');
            $color = config('store.palette.'.$color) !== null ? $color : 'black';
            $hash = crc32($slug);
            $basePrice = isset($parts[4]) && ctype_digit($parts[4]) ? (int) $parts[4] : 1_490_000 + ($hash % 26) * 100_000;
            $entries[] = [
                'group' => $group,
                'name' => Str::limit($name, 150, ''),
                'slug' => Str::limit($slug, 180, ''),
                'urls' => $urls,
                'color' => $color,
                'base_price' => $basePrice,
                'promotion_price' => $hash % 3 === 0 ? (int) (round($basePrice * 0.8 / 10_000) * 10_000) : null,
                'code' => 'LNK-'.strtoupper(substr(hash('sha256', $slug), 0, 10)),
            ];
        }

        return $entries;
    }
}
