<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductManagementService;
use App\Services\StockImportService;
use Illuminate\Database\Seeder;
use LogicException;

/** Local/testing Nike product albums: four views of each exact style-colour code. */
class MultiAngleShoeDemoSeeder extends Seeder
{
    /** @var list<string> */
    private const PREVIOUS_DEMO_SLUGS = ['bape-roadsta-photo-demo', 'converse-white-low-photo-demo'];

    /** @var array<string, list<string>> */
    private const SIZES = [
        'men' => ['40', '41', '42'],
        'women' => ['37', '38', '39'],
        'kids' => ['32', '33', '34'],
    ];

    public function run(ProductManagementService $products, StockImportService $imports): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }

        $entries = json_decode(file_get_contents(database_path('data/nike-multiview-demo.json')), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($entries) || count($entries) < 11) {
            throw new LogicException('Nike demo catalog must contain more than ten products.');
        }
        foreach ($entries as $entry) {
            if (! isset(self::SIZES[$entry['group']]) || count($entry['images'] ?? []) !== 4
                || count(array_unique($entry['images'])) !== 4
                || ! str_ends_with($entry['source_url'], '/'.$entry['style_color_code'])) {
                throw new LogicException('Invalid Nike demo album: expected four distinct photos from one style-colour code.');
            }
            foreach ($entry['images'] as $url) {
                if (! str_starts_with($url, 'https://static.nike.com/a/images/')) {
                    throw new LogicException('Nike demo album contains an unexpected image source.');
                }
            }
        }

        $this->call(ProductDemoTaxonomySeeder::class);
        $actor = User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->orderBy('id')->firstOrFail();
        $supplier = Supplier::where('code', 'DEMO-NCC-1')->firstOrFail();

        foreach ($entries as $index => $entry) {
            $this->seedProduct($actor, $supplier, $products, $imports, $entry, $index);
        }

        foreach (self::PREVIOUS_DEMO_SLUGS as $slug) {
            $old = Product::where('slug', $slug)->where('is_active', true)->first();
            if ($old !== null && $old->colors()->whereIn('style_color_code', ['BAPE-ROADSTA-PHOTO-DEMO', 'CONVERSE-WHITE-LOW-PHOTO-DEMO'])->exists()) {
                $products->setActive($actor, $old, false);
            }
        }
    }

    /** @param array<string, mixed> $entry */
    private function seedProduct(User $actor, Supplier $supplier, ProductManagementService $products, StockImportService $imports, array $entry, int $index): void
    {
        $product = Product::withTrashed()->where('slug', $entry['slug'])->first();
        $code = 'NIKE-DEMO-'.$entry['style_color_code'];

        if ($product === null) {
            $product = $products->save($actor, null, [
                'name' => $entry['name'].' · ảnh tham khảo',
                'slug' => $entry['slug'],
                'category_id' => Category::where('slug', $entry['group'])->firstOrFail()->id,
                'brand_line' => 'Nike · Sản phẩm minh họa',
                'description' => 'Ảnh lấy từ trang Nike Việt Nam cho demo đồ án. Giá, size và tồn kho là dữ liệu mô phỏng; cửa hàng không tự nhận là nhà phân phối chính hãng.',
                'base_price' => $entry['demo_price_vnd'],
                'promotion_price' => null,
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'colors' => [[
                    'style_color_code' => $code,
                    'color_name' => $entry['color_name'],
                    'color_key' => $entry['color_key'],
                    'primary_hex' => (string) config('store.palette.'.$entry['color_key'].'.hex'),
                    'is_active' => 1,
                    'variants' => array_map(fn (string $size): array => [
                        'size_value' => $size,
                        'sku' => $code.'-'.$size,
                        'is_active' => 1,
                    ], self::SIZES[$entry['group']]),
                    'images' => array_map(fn (string $url, int $position): array => [
                        'external_url' => $url,
                        'alt_text' => $entry['name'].' ('.$entry['style_color_code'].') — ảnh '.($position + 1),
                        'is_thumbnail' => $position === 0,
                    ], $entry['images'], array_keys($entry['images'])),
                ]],
            ]);
        }

        if ($product->trashed() || $product->colors()->where('style_color_code', $code)->count() !== 1) {
            throw new LogicException('Demo product changed or removed; refusing to overwrite it.');
        }

        $variants = ProductVariant::whereHas('color', fn ($query) => $query
            ->where('product_id', $product->id)
            ->where('style_color_code', $code))
            ->orderBy('id')->get();
        if ($variants->count() !== 3) {
            throw new LogicException('Demo variants changed; review before importing stock.');
        }

        $importKey = sprintf('73000000-0000-4000-8000-%012d', $index + 1);
        $import = StockImport::where('command_key', $importKey)->first();
        if ($import === null) {
            $import = $imports->saveDraft($actor, null, [
                'command_key' => $importKey,
                'supplier_id' => $supplier->id,
                'note' => 'Nhập kho mẫu ảnh Nike: '.$entry['name'],
                'items' => $variants->map(fn (ProductVariant $variant): array => [
                    'variant_id' => $variant->id,
                    'quantity' => 8,
                    'unit_cost_amount' => intdiv($entry['demo_price_vnd'] * 60, 100),
                ])->all(),
            ]);
        }
        $imports->post($actor, $import);
        $this->command?->info('Album Nike: /shoes/'.$entry['slug']);
    }
}
