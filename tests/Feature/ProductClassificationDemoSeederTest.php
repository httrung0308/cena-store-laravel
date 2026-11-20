<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Models\Classification;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductClassificationDemoSeeder;
use Database\Seeders\ProductDemoTaxonomySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductClassificationDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure admin user and basic categories exist before seeding
        $admin = User::factory()->administrator()->create();
        $this->seed(ProductDemoTaxonomySeeder::class);
    }

    public function test_seeder_creates_classifications_and_demo_products(): void
    {
        $this->seed(ProductClassificationDemoSeeder::class);

        // Verify Classifications
        $this->assertTrue(Classification::query()->where('slug', 'sneaker')->exists());
        $this->assertTrue(Classification::query()->where('slug', 'running')->exists());
        $this->assertTrue(Classification::query()->where('slug', 'snow-boots')->where('is_active', false)->exists());
        $this->assertSame(6, Classification::query()->where('classification_group', ClassificationGroup::Silhouette->value)->count());
        $this->assertSame(5, Classification::query()->where('classification_group', ClassificationGroup::Activity->value)->count());

        // Verify Products
        $totalProducts = Product::query()->count();
        $this->assertGreaterThanOrEqual(28, $totalProducts);

        // Verify Featured
        $featuredCount = Product::query()->where('is_featured', true)->count();
        $this->assertGreaterThanOrEqual(6, $featuredCount);

        // Verify Promotion
        $promoCount = Product::query()->whereNotNull('promotion_price')->count();
        $this->assertGreaterThanOrEqual(6, $promoCount);

        // Verify Out-of-Stock test products exist
        $outOfStock = Product::query()->whereDoesntHave('variants', fn ($q) => $q->where('stock_qty', '>', 0))->count();
        $this->assertGreaterThanOrEqual(1, $outOfStock);

        // Verify Unclassified products exist
        $unclassified = Product::query()->whereDoesntHave('classifications')->count();
        $this->assertGreaterThanOrEqual(1, $unclassified);

        // Verify Products with multiple classifications
        $multiClassified = Product::query()->has('classifications', '>=', 2)->count();
        $this->assertGreaterThanOrEqual(10, $multiClassified);
    }

    public function test_seeder_is_strictly_idempotent(): void
    {
        $this->seed(ProductClassificationDemoSeeder::class);

        $initialClassificationCount = Classification::query()->count();
        $initialProductCount = Product::query()->count();

        // Run seeder second time
        $this->seed(ProductClassificationDemoSeeder::class);

        $this->assertSame($initialClassificationCount, Classification::query()->count());
        $this->assertSame($initialProductCount, Product::query()->count());
    }
}
