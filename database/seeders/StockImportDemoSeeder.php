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
use Illuminate\Http\UploadedFile;
use LogicException;

class StockImportDemoSeeder extends Seeder
{
    public function run(ProductManagementService $products, StockImportService $imports): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }
        $this->call(ProductDemoTaxonomySeeder::class);
        $actor = User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->orderBy('id')->firstOrFail();
        $skus = [];
        foreach (GenderGroup::cases() as $gender) {
            $key = strtolower($gender->value);
            $slug = 'cena-demo-'.$key;
            $colors = [];
            foreach (['black', 'white'] as $colorKey) {
                $prefix = 'CENA-DEMO-'.$gender->value.'-'.strtoupper($colorKey);
                foreach (['40', '40_5', '41'] as $size) {
                    $skus[] = $prefix.'-'.$size;
                }
                $path = public_path('legacy/img/'.($key === 'kids' ? 'kid' : $key).'.jpg');
                $colors[] = ['style_color_code' => $prefix, 'color_name' => ucfirst($colorKey), 'color_key' => $colorKey, 'primary_hex' => $colorKey === 'black' ? '#000000' : '#ffffff', 'is_active' => 1,
                    'range_start' => '40', 'range_end' => '41', 'sku_prefix' => $prefix, 'images' => [['file' => new UploadedFile($path, basename($path), 'image/jpeg', null, true), 'alt_text' => 'Ảnh Cena gốc minh họa '.$gender->label(), 'is_thumbnail' => true]]];
            }
            if (! Product::withTrashed()->where('slug', $slug)->exists()) {
                $products->save($actor, null, ['name' => 'Cena Runner Demo — '.$gender->label(), 'slug' => $slug, 'category_id' => Category::where('slug', $key)->firstOrFail()->id,
                    'brand_line' => 'Cena · Sản phẩm minh họa', 'description' => 'Sản phẩm phục vụ demo; dùng ảnh Cena gốc minh họa.', 'base_price' => 2000000, 'promotion_price' => 1500000, 'warranty_months' => 6, 'exchange_days' => 7, 'is_active' => 1, 'colors' => $colors]);
            }
        }
        $variants = ProductVariant::whereIn('sku', $skus)->orderBy('id')->get();
        if ($variants->count() !== count($skus)) {
            throw new LogicException('Demo catalog changed; review instead of silently repairing.');
        }
        foreach ([[1, 5, 300000], [2, 2, 500000]] as [$number,$quantity,$cost]) {
            $import = $imports->saveDraft($actor, null, ['command_key' => '70000000-0000-4000-8000-'.str_pad((string) $number, 12, '0', STR_PAD_LEFT),
                'supplier_id' => Supplier::where('code', 'DEMO-NCC-'.$number)->firstOrFail()->id, 'note' => 'Phiếu nhập mẫu qua service — lần '.$number,
                'items' => $variants->map(fn ($v) => ['variant_id' => $v->id, 'quantity' => $quantity, 'unit_cost_amount' => $cost])->all()]);
            $imports->post($actor, $import);
        }
    }
}
