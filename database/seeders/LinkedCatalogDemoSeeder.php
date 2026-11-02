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
 *   men | Air Runner 1 | https://example.com/shoe.jpg | white | 2390000
 *
 * Group is men|women|kids|unisex; colour (palette key) and base price are optional. Images stay external
 * HTTPS links (nothing is downloaded or stored). Stock comes from a real posted stock import per product.
 * Lines whose product slug already exists are skipped, so the seeder can be re-run after adding lines.
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
        $skipped = 0;
        foreach ($this->entries($file) as $entry) {
            if (Product::withTrashed()->where('slug', $entry['slug'])->exists()) {
                $skipped++;

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
                    'images' => [['external_url' => $entry['url'], 'alt_text' => $entry['name'], 'is_thumbnail' => true]],
                ]],
            ]);
            $variants = ProductVariant::query()->whereHas('color', fn ($query) => $query->where('product_id', $product->id))->orderBy('id')->get();
            $import = $imports->saveDraft($actor, null, [
                'command_key' => (string) Str::uuid(),
                'supplier_id' => $supplier->id,
                'note' => 'Nhập kho demo cho '.$entry['name'],
                'items' => $variants->map(fn (ProductVariant $variant): array => ['variant_id' => $variant->id, 'quantity' => 8, 'unit_cost_amount' => intdiv($entry['base_price'] * 45, 100)])->all(),
            ]);
            $imports->post($actor, $import);
            $created++;
        }
        $this->command?->info("Đã tạo {$created} sản phẩm, bỏ qua {$skipped} sản phẩm đã có.");
    }

    /** @return list<array{group: string, name: string, slug: string, url: string, color: string, base_price: int, promotion_price: ?int, code: string}> */
    private function entries(string $file): array
    {
        $entries = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            [$group, $name, $url] = array_pad($parts, 3, '');
            $group = strtolower($group);
            $group = $group === 'kid' ? 'kids' : $group;
            if (! in_array($group, array_map(fn (GenderGroup $gender): string => strtolower($gender->value), GenderGroup::cases()), true)
                || $name === '' || ! str_starts_with($url, 'https://')) {
                throw new LogicException('Dòng '.($number + 1).' không hợp lệ. Định dạng: nhóm | tên | https://link-ảnh | màu? | giá?');
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
                'url' => $url,
                'color' => $color,
                'base_price' => $basePrice,
                'promotion_price' => $hash % 3 === 0 ? (int) (round($basePrice * 0.8 / 10_000) * 10_000) : null,
                'code' => 'LNK-'.strtoupper(substr(hash('sha256', $slug), 0, 10)),
            ];
        }

        return $entries;
    }
}
