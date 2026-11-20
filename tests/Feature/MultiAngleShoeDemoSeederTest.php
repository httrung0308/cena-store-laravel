<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductColor;
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

    public function test_nike_albums_have_four_views_per_product_and_reseeding_is_idempotent(): void
    {
        $entries = json_decode(file_get_contents(database_path('data/nike-multiview-demo.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(10, count($entries));
        foreach ($entries as $entry) {
            $this->assertCount(4, $entry['images']);
            $this->assertCount(4, array_unique($entry['images']));
            $this->assertTrue(collect($entry['images'])->every(fn (string $url): bool => str_starts_with($url, 'https://static.nike.com/a/images/')));
        }
        $this->seed(MultiAngleShoeDemoSeeder::class);

        $snapshots = [];
        foreach ($entries as $entry) {
            $product = Product::where('slug', $entry['slug'])->sole();
            $color = $product->colors()->sole();
            $images = ProductImage::where('product_color_id', $color->id)->orderBy('display_order')->get();
            $this->assertCount(4, $images);
            $this->assertSame([true, false, false, false], $images->pluck('is_thumbnail')->all());
            $this->assertSame($entry['images'], $images->pluck('external_url')->all());
            $this->assertTrue($images->every(fn (ProductImage $image): bool => str_contains($image->alt_text, $entry['style_color_code'])));

            $this->get(route('shoes.show', $entry['slug']))->assertOk()
                ->assertSee('data-gallery-zoom', false)
                ->assertSee('aria-label="Chọn ảnh"', false)
                ->assertViewHas('albums', fn (array $albums): bool => count($albums[(string) $color->id]) === 4);

            $snapshots[$color->id] = [
                $images->pluck('id')->all(),
                ProductVariant::where('product_color_id', $color->id)->orderBy('id')->pluck('stock_qty')->all(),
            ];
        }

        $this->seed(MultiAngleShoeDemoSeeder::class);
        foreach ($snapshots as $colorId => [$imageIds, $stock]) {
            $this->assertSame($imageIds, ProductImage::where('product_color_id', $colorId)->orderBy('display_order')->pluck('id')->all());
            $this->assertSame($stock, ProductVariant::where('product_color_id', $colorId)->orderBy('id')->pluck('stock_qty')->all());
        }

        $this->assertSame(count($entries), StockImport::where('command_key', 'like', '73000000-0000-4000-8000-%')->count());
    }

    public function test_old_demo_products_are_hidden_without_deleting_order_history(): void
    {
        foreach (['bape-roadsta-photo-demo' => 'BAPE-ROADSTA-PHOTO-DEMO', 'converse-white-low-photo-demo' => 'CONVERSE-WHITE-LOW-PHOTO-DEMO'] as $slug => $code) {
            $product = Product::factory()->create(['slug' => $slug, 'is_active' => true]);
            ProductColor::factory()->create(['product_id' => $product->id, 'style_color_code' => $code]);
        }
        $this->seed(MultiAngleShoeDemoSeeder::class);
        foreach (['bape-roadsta-photo-demo', 'converse-white-low-photo-demo'] as $slug) {
            $product = Product::where('slug', $slug)->sole();
            $this->assertFalse($product->is_active);
        }
    }

    public function test_demo_seed_requires_explicit_opt_in(): void
    {
        config(['store.demo.enabled' => false]);
        $this->expectException(LogicException::class);
        $this->seed(MultiAngleShoeDemoSeeder::class);
    }
}
