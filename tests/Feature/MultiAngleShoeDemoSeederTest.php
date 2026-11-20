<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\User;
use Database\Seeders\MultiAngleShoeDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class MultiAngleShoeDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->administrator()->create();
        config(['store.demo.enabled' => true]);
    }

    public function test_licensed_web_albums_display_and_reseeding_does_not_multiply_images_or_stock(): void
    {
        $this->seed(MultiAngleShoeDemoSeeder::class);

        foreach (['bape-roadsta-photo-demo' => 4, 'converse-white-low-photo-demo' => 3] as $slug => $count) {
            $product = Product::where('slug', $slug)->sole();
            $color = $product->colors()->sole();
            $images = ProductImage::where('product_color_id', $color->id)->orderBy('display_order')->get();
            $this->assertCount($count, $images);
            $this->assertSame([true, ...array_fill(0, $count - 1, false)], $images->pluck('is_thumbnail')->all());
            $this->assertTrue($images->every(fn (ProductImage $image): bool => str_starts_with($image->external_url, 'https://upload.wikimedia.org/')));

            $this->get(route('shoes.show', $slug))->assertOk()
                ->assertSee('data-gallery-zoom', false)
                ->assertSee('aria-label="Chọn ảnh"', false)
                ->assertViewHas('albums', fn (array $albums): bool => count($albums[(string) $color->id]) === $count);

            $imageIds = $images->pluck('id')->all();
            $stock = ProductVariant::where('product_color_id', $color->id)->orderBy('id')->pluck('stock_qty')->all();
            $this->seed(MultiAngleShoeDemoSeeder::class);
            $this->assertSame($imageIds, ProductImage::where('product_color_id', $color->id)->orderBy('display_order')->pluck('id')->all());
            $this->assertSame($stock, ProductVariant::where('product_color_id', $color->id)->orderBy('id')->pluck('stock_qty')->all());
        }

        $this->assertSame(2, StockImport::whereIn('command_key', [
            '72000000-0000-4000-8000-000000000001',
            '72000000-0000-4000-8000-000000000002',
        ])->count());
    }

    public function test_demo_seed_requires_explicit_opt_in(): void
    {
        config(['store.demo.enabled' => false]);
        $this->expectException(LogicException::class);
        $this->seed(MultiAngleShoeDemoSeeder::class);
    }
}
