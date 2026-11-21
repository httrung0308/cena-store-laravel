<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductManagementService;
use App\Services\StockImportService;
use Illuminate\Database\Seeder;
use LogicException;

/** Fill the 36–46 size range for the local/testing catalog without touching historical stock. */
class DemoSizeRangeSeeder extends Seeder
{
    /** @var list<string> */
    private const SIZES = ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'];

    public function run(ProductManagementService $products, StockImportService $imports): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }

        $this->call(ProductDemoTaxonomySeeder::class);
        $actor = User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->orderBy('id')->firstOrFail();
        $supplier = Supplier::where('code', 'DEMO-NCC-1')->firstOrFail();

        Product::where('is_active', true)->whereHas('colors', fn ($query) => $query->where('is_active', true))
            ->with(['colors.variants', 'colors.images'])->orderBy('id')
            ->chunkById(20, function ($catalog) use ($actor, $supplier, $products, $imports): void {
                foreach ($catalog as $product) {
                    $this->fillProduct($actor, $supplier, $products, $imports, $product);
                }
            });
    }

    private function fillProduct(User $actor, Supplier $supplier, ProductManagementService $products, StockImportService $imports, Product $product): void
    {
        $colors = [];
        $needsNewSizes = false;

        foreach ($product->colors as $color) {
            $variants = $color->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'size_value' => $variant->size_value,
                'sku' => $variant->sku,
                'price_override' => $variant->price_override,
                'is_active' => $variant->is_active ? 1 : 0,
            ])->all();

            if ($color->is_active) {
                $existingSizes = array_column($variants, 'size_value');
                foreach (self::SIZES as $size) {
                    if (! in_array($size, $existingSizes, true)) {
                        $variants[] = [
                            'size_value' => $size,
                            'sku' => ProductManagementService::generateSku($product->slug, $color->style_color_code, $size),
                            'price_override' => null,
                            'is_active' => 1,
                        ];
                        $needsNewSizes = true;
                    }
                }
            }

            $colors[] = [
                'id' => $color->id,
                'style_color_code' => $color->style_color_code,
                'color_name' => $color->color_name,
                'color_key' => $color->color_key,
                'primary_hex' => $color->primary_hex,
                'is_active' => $color->is_active ? 1 : 0,
                'variants' => $variants,
                'images' => $color->images->map(fn (ProductImage $image): array => [
                    'id' => $image->id,
                    'external_url' => $image->external_url,
                    'alt_text' => $image->alt_text,
                    'is_thumbnail' => $image->is_thumbnail ? 1 : 0,
                ])->all(),
            ];
        }

        if ($needsNewSizes) {
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
                'is_active' => 1,
                'is_featured' => $product->is_featured ? 1 : 0,
                'colors' => $colors,
            ]);
        }

        $newStock = ProductVariant::whereHas('color', fn ($query) => $query
            ->where('product_id', $product->id)->where('is_active', true))
            ->whereIn('size_value', self::SIZES)->where('is_active', true)
            ->where('stock_qty', 0)->whereDoesntHave('stockImportItems')
            ->whereDoesntHave('inventoryMovements')->orderBy('id')->get();

        foreach ($newStock->chunk(100) as $batch) {
            $variantIds = $batch->pluck('id')->all();
            $importKey = '75000000-0000-4000-8000-'.substr(hash('sha256', implode(',', $variantIds)), 0, 12);
            $import = StockImport::where('command_key', $importKey)->first();

            if ($import === null) {
                $import = $imports->saveDraft($actor, null, [
                    'command_key' => $importKey,
                    'supplier_id' => $supplier->id,
                    'note' => 'Nhập kho demo size 36–46: '.$product->name,
                    'items' => $batch->map(fn (ProductVariant $variant): array => [
                        'variant_id' => $variant->id,
                        'quantity' => 4,
                        'unit_cost_amount' => max(1, intdiv($product->base_price * 60, 100)),
                    ])->all(),
                ]);
            }

            $imports->post($actor, $import);
        }
    }
}
