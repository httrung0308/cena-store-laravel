<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\User;
use Database\Seeders\DemoSizeRangeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class DemoSizeRangeSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->administrator()->create();
        config(['store.demo.enabled' => true]);
    }

    public function test_all_active_colors_get_sizes_36_to_46_and_replay_preserves_stock_and_images(): void
    {
        $product = Product::factory()->create(['base_price' => 2_000_000]);
        $first = ProductColor::factory()->create(['product_id' => $product->id]);
        $second = ProductColor::factory()->create(['product_id' => $product->id]);
        $oldVariant = ProductVariant::factory()->stocked(5)->create(['product_color_id' => $first->id, 'size_value' => '40', 'size_numeric' => '40.0']);
        $oldImage = ProductImage::factory()->thumbnail()->create(['product_color_id' => $first->id]);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $second->id]);

        $this->seed(DemoSizeRangeSeeder::class);

        foreach ([$first, $second] as $color) {
            $this->assertSame(range(36, 46), $color->variants()->orderBy('size_numeric')->get()->pluck('size_value')->map(fn (string $size): int => (int) $size)->all());
            $expectedStock = array_fill(0, 11, 4);
            if ($color->id === $first->id) {
                $expectedStock[4] = 5;
            }
            $this->assertSame($expectedStock, $color->variants()->orderBy('size_numeric')->pluck('stock_qty')->all());
        }
        $this->assertSame($oldVariant->id, $first->variants()->where('size_value', '40')->sole()->id);
        $this->assertSame($oldImage->id, $first->images()->where('is_thumbnail', true)->sole()->id);
        $this->assertTrue($product->fresh()->is_active);

        $stockImports = StockImport::where('command_key', 'like', '75000000-0000-4000-8000-%')->get();
        $this->assertCount(1, $stockImports);
        $this->assertCount(21, $stockImports->first()->items);
        $this->seed(DemoSizeRangeSeeder::class);
        $this->assertSame(22, ProductVariant::whereIn('product_color_id', [$first->id, $second->id])->count());
        $this->assertSame(1, StockImport::where('command_key', 'like', '75000000-0000-4000-8000-%')->count());
        $this->assertSame(5, $oldVariant->fresh()->stock_qty);
    }

    public function test_inactive_products_are_left_unchanged(): void
    {
        $product = Product::factory()->create(['is_active' => false]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        ProductVariant::factory()->create(['product_color_id' => $color->id]);

        $this->seed(DemoSizeRangeSeeder::class);

        $this->assertSame(1, $color->variants()->count());
    }

    public function test_demo_size_range_requires_explicit_opt_in(): void
    {
        config(['store.demo.enabled' => false]);
        $this->expectException(LogicException::class);
        $this->seed(DemoSizeRangeSeeder::class);
    }
}
