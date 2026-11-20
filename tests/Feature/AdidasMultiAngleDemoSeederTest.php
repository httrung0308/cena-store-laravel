<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\User;
use Database\Seeders\AdidasMultiAngleDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class AdidasMultiAngleDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->administrator()->create();
        config(['store.demo.enabled' => true]);
    }

    public function test_adidas_albums_show_four_distinct_high_resolution_views_and_reseed_without_duplicate_stock(): void
    {
        $entries = json_decode(file_get_contents(database_path('data/adidas-multiview-demo.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(10, count($entries));
        $this->assertCount(count($entries), array_unique(array_column($entries, 'style_color_code')));

        $this->seed(AdidasMultiAngleDemoSeeder::class);
        $snapshots = [];

        foreach ($entries as $entry) {
            $this->assertCount(4, $entry['images']);
            $this->assertCount(4, array_unique($entry['images']));
            $this->assertTrue(collect($entry['images'])->every(fn (string $url): bool => str_starts_with($url, 'https://assets.adidas.com/images/w_1200%2Cf_auto%2Cq_auto/')
                && str_contains($url, '_'.$entry['style_color_code'].'_')));
            $this->assertStringEndsWith('/'.$entry['style_color_code'].'.html', $entry['source_url']);

            $product = Product::where('slug', $entry['slug'])->sole();
            $this->assertSame($entry['name'], $product->name);
            $this->assertSame('adidas', $product->brand_line);
            $color = $product->colors()->sole();
            $images = ProductImage::where('product_color_id', $color->id)->orderBy('display_order')->get();
            $this->assertSame($entry['images'], $images->pluck('external_url')->all());
            $this->assertSame([true, false, false, false], $images->pluck('is_thumbnail')->all());

            $this->get(route('shoes.show', $entry['slug']))->assertOk()
                ->assertSee('data-gallery-zoom', false)
                ->assertSee('aria-label="Chọn ảnh"', false)
                ->assertViewHas('albums', fn (array $albums): bool => count($albums[(string) $color->id]) === 4);

            $snapshots[$color->id] = [
                $images->pluck('id')->all(),
                ProductVariant::where('product_color_id', $color->id)->orderBy('id')->pluck('stock_qty')->all(),
            ];
        }

        $custom = Product::where('slug', $entries[0]['slug'])->sole();
        $custom->update(['name' => 'Tên quản trị viên chỉnh sửa']);
        $customImage = $custom->colors()->sole()->images()->orderBy('display_order')->firstOrFail();
        $customImage->forceFill(['external_url' => 'https://example.test/custom.jpg'])->save();

        $this->seed(AdidasMultiAngleDemoSeeder::class);
        $this->assertSame('Tên quản trị viên chỉnh sửa', $custom->fresh()->name);
        $this->assertSame('https://example.test/custom.jpg', $customImage->fresh()->external_url);

        foreach ($snapshots as $colorId => [$imageIds, $stock]) {
            $this->assertSame($imageIds, ProductImage::where('product_color_id', $colorId)->orderBy('display_order')->pluck('id')->all());
            $this->assertSame($stock, ProductVariant::where('product_color_id', $colorId)->orderBy('id')->pluck('stock_qty')->all());
        }

        $this->assertSame(count($entries), StockImport::where('command_key', 'like', '74000000-0000-4000-8000-%')->count());
    }

    public function test_adidas_demo_requires_explicit_opt_in(): void
    {
        config(['store.demo.enabled' => false]);
        $this->expectException(LogicException::class);
        $this->seed(AdidasMultiAngleDemoSeeder::class);
    }
}
