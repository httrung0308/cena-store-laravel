<?php

namespace Tests\Feature;

use App\Enums\ProductImageSourceType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\User;
use Database\Seeders\LinkedCatalogDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class LinkedCatalogDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->administrator()->create();
        $this->file = tempnam(sys_get_temp_dir(), 'catalog');
        config(['store.demo.enabled' => true, 'store.demo.catalog_file' => $this->file]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_each_line_becomes_a_public_product_with_a_linked_image_and_posted_stock(): void
    {
        file_put_contents($this->file, implode("\n", [
            '# nhóm | tên | link ảnh | màu | giá',
            'men | Air Runner Một | https://images.example.test/men-1.jpg',
            'women | Court Glide <Hai> | https://images.example.test/women-2.webp | white | 2390000',
            'kid | Little Dash | https://images.example.test/kids-3.png | pink',
            '',
        ]));

        $this->seed(LinkedCatalogDemoSeeder::class);

        $this->assertSame(3, Product::query()->where('slug', 'like', 'demo-%')->count());
        $women = Product::query()->where('slug', 'demo-court-glide-hai')->sole();
        $this->assertSame(2_390_000, $women->base_price);
        $this->assertSame('white', $women->colors->sole()->color_key);
        $image = ProductImage::query()->where('product_color_id', $women->colors->sole()->id)->sole();
        $this->assertSame([ProductImageSourceType::ExternalUrl, 'https://images.example.test/women-2.webp', null, true],
            [$image->source_type, $image->external_url, $image->storage_path, $image->is_thumbnail]);
        $this->assertSame(['36', '37', '38', '39', '40'], $women->colors->sole()->variants()->orderBy('id')->pluck('size_value')->all());
        $this->assertTrue(ProductVariant::query()->whereHas('color', fn ($q) => $q->where('product_id', $women->id))->get()->every(fn ($v) => $v->stock_qty === 8 && $v->reserved_qty === 0));
        $this->assertSame(3, StockImport::query()->where('status', 'POSTED')->count());

        $this->get(route('shoes.index', ['gender' => 'women']))->assertOk()
            ->assertSee('Court Glide &lt;Hai&gt;', false)->assertSee('https://images.example.test/women-2.webp', false);
        $this->get(route('shoes.index', ['gender' => 'kids']))->assertSee('Little Dash');
    }

    public function test_rerun_only_adds_new_lines(): void
    {
        file_put_contents($this->file, "men | Air Runner Một | https://images.example.test/men-1.jpg\n");
        $this->seed(LinkedCatalogDemoSeeder::class);
        file_put_contents($this->file, "men | Air Runner Một | https://images.example.test/men-1.jpg\nunisex | Street Flex | https://images.example.test/u-1.jpg\n");
        $this->seed(LinkedCatalogDemoSeeder::class);

        $this->assertSame(2, Product::query()->where('slug', 'like', 'demo-%')->count());
        $this->assertSame(2, StockImport::query()->count());
    }

    public function test_bad_lines_and_missing_opt_in_are_rejected(): void
    {
        file_put_contents($this->file, "men | Air Runner | http://insecure.example.test/a.jpg\n");
        try {
            $this->seed(LinkedCatalogDemoSeeder::class);
            $this->fail('A non-HTTPS image link was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Dòng 1', $exception->getMessage());
        }

        config(['store.demo.enabled' => false]);
        $this->expectException(LogicException::class);
        $this->seed(LinkedCatalogDemoSeeder::class);
    }
}
