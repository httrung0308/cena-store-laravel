<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\GenderGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\HomeQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontHomeTest extends TestCase
{
    use DatabaseTransactions;

    private function createPublicShoe(string $name, array $attributes = []): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'men-test'],
            ['name' => 'Giày Nam Test', 'gender_group' => GenderGroup::Men, 'is_active' => true]
        );

        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name.'-'.uniqid())->slug(),
            'base_price' => 1_000_000,
            'promotion_price' => null,
            'is_featured' => false,
            'is_active' => true,
        ], $attributes));

        $color = $product->colors()->create([
            'style_color_code' => 'STYLE-'.uniqid(),
            'color_name' => 'Trắng',
            'color_key' => 'white',
            'primary_hex' => '#FFFFFF',
            'is_active' => true,
            'display_order' => 1,
        ]);

        ProductImage::factory()->thumbnail()->create([
            'product_color_id' => $color->id,
            'source_type' => ProductImageSourceType::ExternalUrl,
            'external_url' => 'https://example.com/shoe.jpg',
            'storage_path' => null,
            'alt_text' => $name,
        ]);

        ProductVariant::factory()->stocked(10)->create([
            'product_color_id' => $color->id,
            'size_value' => '40',
            'size_numeric' => '40.0',
        ]);

        return $product;
    }

    public function test_home_renders_successfully_with_fallback_when_no_banners(): void
    {
        Banner::query()->delete();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('storefront.home')
            ->assertSee('Thể thao là')
            ->assertSee('không ngừng bỏ cuộc.')
            ->assertSee('Khám phá ngay');
    }

    public function test_home_renders_single_banner_statically_when_one_active_banner_exists(): void
    {
        Banner::query()->delete();

        $banner = Banner::factory()->create([
            'title' => 'Single Hero Promotion '.Str::random(5),
            'subtitle' => 'Exclusive running shoes collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($banner->title)
            ->assertSee('Exclusive running shoes collection')
            ->assertDontSee('Banner trước');
    }

    public function test_home_renders_carousel_when_multiple_active_banners_exist(): void
    {
        Banner::query()->delete();

        $banner1 = Banner::factory()->create([
            'title' => 'Banner First '.Str::random(5),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $banner2 = Banner::factory()->create([
            'title' => 'Banner Second '.Str::random(5),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $inactiveBanner = Banner::factory()->inactive()->create([
            'title' => 'Hidden Inactive Banner '.Str::random(5),
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($banner1->title)
            ->assertSee($banner2->title)
            ->assertDontSee($inactiveBanner->title)
            ->assertSee('Banner trước')
            ->assertSee('Banner kế tiếp');
    }

    public function test_home_renders_gender_categories_and_active_classifications(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Khám phá theo đối tượng')
            ->assertSee('Giày Nam')
            ->assertSee('Giày Nữ')
            ->assertSee('Trẻ Em')
            ->assertSee('Unisex');

        $activeClass = Classification::active()->first();
        if ($activeClass) {
            $response->assertSee($activeClass->name);
        }
    }

    public function test_home_renders_three_separate_product_sections(): void
    {
        // Setup at least 1 featured, 1 sale, 1 regular product
        $this->createPublicShoe('Shoe Featured Alpha', ['is_featured' => true]);
        $this->createPublicShoe('Shoe Super Deal', ['base_price' => 2_000_000, 'promotion_price' => 1_500_000]);
        $this->createPublicShoe('Shoe Regular Fresh', []);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Sản phẩm nổi bật')
            ->assertSee('Ưu đãi hấp dẫn')
            ->assertSee('Mới đăng bán')
            ->assertSee('Xem sản phẩm giảm giá trước')
            ->assertSee('Xem sản phẩm giảm giá kế tiếp')
            ->assertSee('Tư vấn size giày chuẩn');
    }

    public function test_home_query_service_prioritizes_deduplication(): void
    {
        // Tạo 1 featured, 1 sale và đủ 4 sản phẩm thường mới để không kích hoạt fallback bù
        $pFeatured = $this->createPublicShoe('Deduplication Featured Shoe', ['is_featured' => true]);
        $pSale = $this->createPublicShoe('Deduplication Sale Shoe', ['base_price' => 2_000_000, 'promotion_price' => 1_200_000]);
        for ($i = 1; $i <= 4; $i++) {
            $this->createPublicShoe("Deduplication New Shoe {$i}", []);
        }

        /** @var HomeQueryService $service */
        $service = app(HomeQueryService::class);
        $data = $service->data();

        $featuredIds = array_map(fn (array $p): int => (int) $p['product']['id'], $data['featuredProducts']);
        $saleIds = array_map(fn (array $p): int => (int) $p['product']['id'], $data['saleProducts']);
        $newIds = array_map(fn (array $p): int => (int) $p['product']['id'], $data['newProducts']);

        $this->assertNotEmpty($featuredIds);
        $this->assertContains($pFeatured->id, $featuredIds);

        $this->assertNotEmpty($saleIds);
        $this->assertContains($pSale->id, $saleIds);

        $this->assertNotEmpty($newIds);

        // Khi có đủ sản phẩm mới, danh sách mới đăng bán ưu tiên hoàn toàn các sản phẩm chưa xuất hiện
        $intersect = array_intersect($featuredIds, $newIds);
        $this->assertEmpty($intersect, 'Sản phẩm mới đăng bán không nên trùng lặp với sản phẩm nổi bật khi có đủ dữ liệu');
    }

    public function test_home_article_sidebar_displays_only_published_articles(): void
    {
        $author = User::factory()->create();

        $published = Article::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'Published Running Tips '.Str::random(6),
        ]);

        $draft = Article::factory()->create([
            'author_id' => $author->id,
            'title' => 'Unpublished Secret Article '.Str::random(6),
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title);
    }
}
