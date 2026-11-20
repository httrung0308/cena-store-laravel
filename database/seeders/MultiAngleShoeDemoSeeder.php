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

/** Adds real, credited multi-view shoe photographs to an existing demo shop. */
class MultiAngleShoeDemoSeeder extends Seeder
{
    private const COMMONS = 'https://upload.wikimedia.org/wikipedia/commons/';

    /** @var list<array<string, mixed>> */
    private const PRODUCTS = [
        [
            'slug' => 'bape-roadsta-photo-demo',
            'name' => 'Bape Roadsta · ảnh tham khảo',
            'category' => 'unisex',
            'color_code' => 'BAPE-ROADSTA-PHOTO-DEMO',
            'color_name' => 'Trắng · Vàng',
            'color_key' => 'white',
            'color_hex' => '#d6b96d',
            'price' => 1_790_000,
            'cost' => 1_050_000,
            'credit' => 'Ảnh: Christian H. · CC BY 2.0',
            'import_key' => '72000000-0000-4000-8000-000000000001',
            'photos' => [
                '6/65/Bape_Roadsta_%282009-03-21_00.19.01_by_Christian_H.%29.jpg',
                '9/9b/Bape_Roadsta_%282009-03-21_00.19.20_by_Christian_H.%29.jpg',
                '0/0d/Bape_Roadsta_%282009-03-21_00.20.04_by_Christian_H.%29.jpg',
                '5/5c/Bape_Roadsta_%282009-03-21_00.20.21_by_Christian_H.%29.jpg',
            ],
        ],
        [
            'slug' => 'converse-white-low-photo-demo',
            'name' => 'Converse All Star trắng · ảnh tham khảo',
            'category' => 'women',
            'color_code' => 'CONVERSE-WHITE-LOW-PHOTO-DEMO',
            'color_name' => 'Trắng',
            'color_key' => 'white',
            'color_hex' => '#f5f5f4',
            'price' => 1_390_000,
            'cost' => 800_000,
            'credit' => 'Ảnh: Thewildone85 · CC BY-SA 3.0',
            'import_key' => '72000000-0000-4000-8000-000000000002',
            'photos' => [
                'a/a3/Converse_side_view.JPG',
                'b/be/White_converse_front.JPG',
                '2/2d/White_converse_heel.JPG',
            ],
        ],
    ];

    public function run(ProductManagementService $products, StockImportService $imports): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }

        $this->call(ProductDemoTaxonomySeeder::class);
        $actor = User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->orderBy('id')->firstOrFail();
        $supplier = Supplier::where('code', 'DEMO-NCC-1')->firstOrFail();

        foreach (self::PRODUCTS as $entry) {
            $this->seedProduct($actor, $supplier, $products, $imports, $entry);
        }
    }

    /** @param array<string, mixed> $entry */
    private function seedProduct(User $actor, Supplier $supplier, ProductManagementService $products, StockImportService $imports, array $entry): void
    {
        $product = Product::withTrashed()->where('slug', $entry['slug'])->first();
        if ($product === null) {
            $product = $products->save($actor, null, [
                'name' => $entry['name'],
                'slug' => $entry['slug'],
                'category_id' => Category::where('slug', $entry['category'])->firstOrFail()->id,
                'brand_line' => $entry['credit'],
                'description' => 'Ảnh thật nhiều góc dùng để minh họa giao diện. Giá, size và tồn kho là dữ liệu mô phỏng; cửa hàng không tự nhận là nhà phân phối chính hãng.',
                'base_price' => $entry['price'],
                'promotion_price' => null,
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'colors' => [[
                    'style_color_code' => $entry['color_code'],
                    'color_name' => $entry['color_name'],
                    'color_key' => $entry['color_key'],
                    'primary_hex' => $entry['color_hex'],
                    'is_active' => 1,
                    'variants' => array_map(fn (string $size): array => [
                        'size_value' => $size,
                        'sku' => strtoupper($entry['slug']).'-'.$size,
                        'is_active' => 1,
                    ], ['40', '41', '42']),
                    'images' => $this->images($entry),
                ]],
            ]);
        }

        if ($product->trashed()) {
            throw new LogicException('Demo product was removed; refusing to restore or change it.');
        }

        $variants = ProductVariant::whereHas('color', fn ($query) => $query
            ->where('product_id', $product->id)
            ->where('style_color_code', $entry['color_code']))
            ->orderBy('id')->get();
        if ($variants->count() !== 3) {
            throw new LogicException('Demo variants changed; review before importing stock.');
        }

        $import = StockImport::where('command_key', $entry['import_key'])->first();
        if ($import === null) {
            $import = $imports->saveDraft($actor, null, [
                'command_key' => $entry['import_key'],
                'supplier_id' => $supplier->id,
                'note' => 'Nhập kho mẫu ảnh nhiều góc: '.$entry['name'],
                'items' => $variants->map(fn (ProductVariant $variant): array => [
                    'variant_id' => $variant->id,
                    'quantity' => 8,
                    'unit_cost_amount' => $entry['cost'],
                ])->all(),
            ]);
        }
        $imports->post($actor, $import);
        $this->command?->info('Album ảnh thật: /shoes/'.$entry['slug']);
    }

    /** @param array<string, mixed> $entry
     *  @return list<array{external_url: string, alt_text: string, is_thumbnail: bool}>
     */
    private function images(array $entry): array
    {
        return array_map(fn (string $filename, int $index): array => [
            'external_url' => self::COMMONS.$filename,
            'alt_text' => $entry['name'].' — góc chụp '.($index + 1),
            'is_thumbnail' => $index === 0,
        ], $entry['photos'], array_keys($entry['photos']));
    }
}
