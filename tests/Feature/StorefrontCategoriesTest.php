<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Enums\GenderGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCategoriesTest extends TestCase
{
    use DatabaseTransactions;

    private function createPublicShoe(Category $category, string $name, array $classificationIds = []): Product
    {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name.'-'.uniqid()),
            'base_price' => 1_200_000,
            'is_active' => true,
        ]);

        $color = $product->colors()->create([
            'style_color_code' => 'STYLE-'.uniqid(),
            'color_name' => 'Đen',
            'color_key' => 'black',
            'primary_hex' => '#111827',
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

        ProductVariant::factory()->stocked(5)->create([
            'product_color_id' => $color->id,
            'size_value' => '41',
            'size_numeric' => '41.0',
        ]);

        if ($classificationIds !== []) {
            $product->classifications()->attach($classificationIds);
        }

        return $product;
    }

    public function test_categories_page_displays_gender_and_only_active_classifications(): void
    {
        $activeSilhouette = Classification::forceCreate([
            'name' => 'Giày Đế Độn Test '.Str::random(4),
            'slug' => 'platform-test-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'description' => 'Mô tả kiểu dáng đang hoạt động',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $activeActivity = Classification::forceCreate([
            'name' => 'Chạy Địa Hình Test '.Str::random(4),
            'slug' => 'trail-test-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'description' => 'Mô tả mục đích đang hoạt động',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $inactiveSilhouette = Classification::forceCreate([
            'name' => 'Kiểu Dáng Ẩn '.Str::random(4),
            'slug' => 'hidden-sil-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $inactiveActivity = Classification::forceCreate([
            'name' => 'Mục Đích Ẩn '.Str::random(4),
            'slug' => 'hidden-act-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->get(route('categories.index'));

        $response->assertOk()
            ->assertViewIs('storefront.categories')
            ->assertSee('Danh mục &amp; Phân loại', false)
            ->assertSee('Đối tượng')
            ->assertSee('Giày Nam')
            ->assertSee('Giày Nữ')
            ->assertSee('Trẻ Em')
            ->assertSee('Unisex')
            ->assertSee($activeSilhouette->name)
            ->assertSee($activeSilhouette->description)
            ->assertSee($activeActivity->name)
            ->assertSee($activeActivity->description)
            ->assertDontSee($inactiveSilhouette->name)
            ->assertDontSee($inactiveActivity->name)
            ->assertSee('Tìm nhanh kiểu dáng, mục đích...')
            ->assertSee('Không tìm thấy danh mục hoặc phân loại phù hợp')
            ->assertSee(route('shoes.index', ['gender' => 'men']), false)
            ->assertSee(route('shoes.index', ['silhouette' => [$activeSilhouette->slug]]), false)
            ->assertSee(route('shoes.index', ['activity' => [$activeActivity->slug]]), false);
    }

    public function test_categories_page_renders_large_lists_with_25_items_per_group(): void
    {
        $createdNames = [];

        for ($i = 1; $i <= 25; $i++) {
            $silName = sprintf('Kiểu Dáng Mở Rộng Số %02d Dành Cho Phối Đồ Hàng Ngày %s', $i, Str::random(3));
            $actName = sprintf('Mục Đích Chuyên Biệt Số %02d Cho Tập Luyện Bền Bỉ %s', $i, Str::random(3));

            Classification::forceCreate([
                'name' => $silName,
                'slug' => 'sil-scale-'.$i.'-'.uniqid(),
                'classification_group' => ClassificationGroup::Silhouette,
                'sort_order' => 100 + $i,
                'is_active' => true,
            ]);

            Classification::forceCreate([
                'name' => $actName,
                'slug' => 'act-scale-'.$i.'-'.uniqid(),
                'classification_group' => ClassificationGroup::Activity,
                'sort_order' => 100 + $i,
                'is_active' => true,
            ]);

            $createdNames[] = $silName;
            $createdNames[] = $actName;
        }

        $response = $this->get(route('categories.index'));

        $response->assertOk();
        foreach ($createdNames as $name) {
            $response->assertSee($name);
        }
    }

    public function test_category_and_classification_links_open_catalog_with_matching_products_and_preselected_filters(): void
    {
        $menCategory = Category::factory()->create(['gender_group' => GenderGroup::Men, 'is_active' => true]);
        $womenCategory = Category::factory()->create(['gender_group' => GenderGroup::Women, 'is_active' => true]);

        $silhouette = Classification::forceCreate([
            'name' => 'Retro Runner '.Str::random(4),
            'slug' => 'retro-runner-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $activity = Classification::forceCreate([
            'name' => 'Marathon '.Str::random(4),
            'slug' => 'marathon-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $menTaggedShoe = $this->createPublicShoe($menCategory, 'Cena Men Marathon Alpha', [$silhouette->id, $activity->id]);
        $womenOtherShoe = $this->createPublicShoe($womenCategory, 'Cena Women Casual Beta');

        // 1. Chọn Đối tượng trên trang Danh mục -> mở /shoes?gender=men
        $genderRes = $this->get(route('shoes.index', ['gender' => 'men']));
        $genderRes->assertOk()
            ->assertSee($menTaggedShoe->name)
            ->assertDontSee($womenOtherShoe->name)
            ->assertSee('value="men" checked', false);

        // 2. Chọn Kiểu dáng trên trang Danh mục -> mở /shoes?silhouette[]=slug
        $silRes = $this->get(route('shoes.index', ['silhouette' => [$silhouette->slug]]));
        $silRes->assertOk()
            ->assertSee($menTaggedShoe->name)
            ->assertDontSee($womenOtherShoe->name)
            ->assertSee('value="'.$silhouette->slug.'" checked', false);

        // 3. Chọn Mục đích trên trang Danh mục -> mở /shoes?activity[]=slug
        $actRes = $this->get(route('shoes.index', ['activity' => [$activity->slug]]));
        $actRes->assertOk()
            ->assertSee($menTaggedShoe->name)
            ->assertDontSee($womenOtherShoe->name)
            ->assertSee('value="'.$activity->slug.'" checked', false);
    }
}
