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
use Tests\TestCase;

class CatalogRedesignTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalog_page_uses_unified_vietnamese_labels_without_decorative_shoes_badge(): void
    {
        $response = $this->get('/shoes');

        $response->assertOk();
        $response->assertSee('Tất Cả Sản Phẩm');
        $response->assertDontSee('Shoes</span>', false);
        $response->assertSee('Đối tượng');
        $response->assertSee('Nam');
        $response->assertSee('Nữ');
        $response->assertSee('Trẻ em');
        $response->assertSee('Unisex');
        $response->assertSee('Xanh dương');
        $response->assertDontSee('catalog-category-select');
    }

    public function test_catalog_shows_all_active_classifications_beyond_header_home_cap(): void
    {
        $silhouettes = [];
        $activities = [];

        for ($i = 1; $i <= 10; $i++) {
            $silhouettes[] = Classification::forceCreate([
                'name' => "Kiểu Dáng Uncapped {$i}",
                'slug' => 'kieu-dang-uncapped-'.uniqid().'-'.$i,
                'classification_group' => ClassificationGroup::Silhouette,
                'is_active' => true,
                'show_in_home_nav' => false,
                'sort_order' => $i,
            ]);

            $activities[] = Classification::forceCreate([
                'name' => "Mục Đích Uncapped {$i}",
                'slug' => 'muc-dich-uncapped-'.uniqid().'-'.$i,
                'classification_group' => ClassificationGroup::Activity,
                'is_active' => true,
                'show_in_home_nav' => false,
                'sort_order' => $i,
            ]);
        }

        $response = $this->get('/shoes');
        $response->assertOk();

        foreach ($silhouettes as $item) {
            $response->assertSee($item->name);
        }
        foreach ($activities as $item) {
            $response->assertSee($item->name);
        }
    }

    public function test_subcategory_filter_renders_when_subcategories_exist(): void
    {
        $menCat = Category::query()->where('slug', 'men')->first()
            ?? Category::factory()->create(['slug' => 'men', 'gender_group' => GenderGroup::Men, 'is_active' => true]);

        $child = Category::factory()->create([
            'parent_id' => $menCat->id,
            'name' => 'Giày Chạy Địa Hình Con',
            'slug' => 'child-trail-'.uniqid(),
            'gender_group' => GenderGroup::Men,
            'is_active' => true,
        ]);

        $response = $this->get('/shoes');
        $response->assertOk();
        $response->assertSee('catalog-category-select');
        $response->assertSee('— '.$child->name);
    }

    public function test_active_filter_chips_render_with_individual_remove_urls_and_clear_all(): void
    {
        $silhouette = Classification::forceCreate([
            'name' => 'Low Top Test',
            'slug' => 'low-top-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $activity = Classification::forceCreate([
            'name' => 'Tập Gym Test',
            'slug' => 'tap-gym-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $url = '/shoes?'.http_build_query([
            'search' => 'Runner',
            'gender' => 'men',
            'silhouette' => [$silhouette->slug],
            'activity' => [$activity->slug],
            'colors' => ['red', 'black'],
            'size' => '40.5',
            'min_price' => 500000,
            'max_price' => 2000000,
            'promotion' => 1,
            'in_stock' => 1,
        ]);

        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee('Từ khóa: “Runner”');
        $response->assertSee('Đối tượng: Nam');
        $response->assertSee('Kiểu dáng: '.$silhouette->name);
        $response->assertSee('Mục đích: '.$activity->name);
        $response->assertSee('Màu: Đỏ');
        $response->assertSee('Màu: Đen');
        $response->assertSee('Size: 40.5');
        $response->assertSee('Giá từ: 500.000 ₫');
        $response->assertSee('Giá đến: 2.000.000 ₫');
        $response->assertSee('Đang giảm giá');
        $response->assertSee('Chỉ còn hàng');
        $response->assertSee('Xóa tất cả');
    }

    public function test_partial_catalog_request_returns_listing_fragment_with_matching_results(): void
    {
        $menCat = Category::query()->where('slug', 'men')->first()
            ?? Category::factory()->create(['slug' => 'men', 'gender_group' => GenderGroup::Men, 'is_active' => true]);

        $womenCat = Category::query()->where('slug', 'women')->first()
            ?? Category::factory()->create(['slug' => 'women', 'gender_group' => GenderGroup::Women, 'is_active' => true]);

        $menShoe = Product::factory()->create([
            'category_id' => $menCat->id,
            'name' => 'Cena Ajax Men Shoe',
            'slug' => 'cena-ajax-men-'.uniqid(),
            'base_price' => 1_200_000,
            'is_active' => true,
        ]);
        $menColor = $menShoe->colors()->create([
            'style_color_code' => 'MEN-'.uniqid(),
            'color_name' => 'Đen',
            'color_key' => 'black',
            'primary_hex' => '#111111',
            'is_active' => true,
            'display_order' => 1,
        ]);
        ProductImage::factory()->thumbnail()->create([
            'product_color_id' => $menColor->id,
            'source_type' => ProductImageSourceType::ExternalUrl,
            'external_url' => 'https://example.com/men.jpg',
            'storage_path' => null,
            'alt_text' => 'Cena Ajax Men Shoe',
        ]);
        ProductVariant::factory()->stocked(4)->create([
            'product_color_id' => $menColor->id,
            'size_value' => '40',
            'size_numeric' => '40.0',
        ]);

        $womenShoe = Product::factory()->create([
            'category_id' => $womenCat->id,
            'name' => 'Cena Ajax Women Shoe',
            'slug' => 'cena-ajax-women-'.uniqid(),
            'base_price' => 1_200_000,
            'is_active' => true,
        ]);
        $womenColor = $womenShoe->colors()->create([
            'style_color_code' => 'WOMEN-'.uniqid(),
            'color_name' => 'Trắng',
            'color_key' => 'white',
            'primary_hex' => '#FFFFFF',
            'is_active' => true,
            'display_order' => 1,
        ]);
        ProductImage::factory()->thumbnail()->create([
            'product_color_id' => $womenColor->id,
            'source_type' => ProductImageSourceType::ExternalUrl,
            'external_url' => 'https://example.com/women.jpg',
            'storage_path' => null,
            'alt_text' => 'Cena Ajax Women Shoe',
        ]);
        ProductVariant::factory()->stocked(4)->create([
            'product_color_id' => $womenColor->id,
            'size_value' => '38',
            'size_numeric' => '38.0',
        ]);

        $response = $this->withHeaders(['X-Catalog-Partial' => '1'])
            ->get('/shoes?gender=men');

        $response->assertOk();
        $response->assertSee('id="catalog-listing-root"', false);
        $response->assertSee('id="catalog-chips-region"', false);
        $response->assertSee('id="catalog-results-region"', false);
        $response->assertSee('Đối tượng: Nam');
        $response->assertSee($menShoe->name);
        $response->assertDontSee($womenShoe->name);
        $response->assertDontSee('<html lang="vi"', false);
    }
}
