<?php

namespace Tests\Feature;

use App\Enums\GenderGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UxPolishAndTitleEncodingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gender_group_unisex_label_is_standardized_everywhere(): void
    {
        $this->assertSame('Unisex', GenderGroup::Unisex->label());

        $staff = User::factory()->staff()->create();
        Category::factory()->create([
            'name' => 'Giày Unisex Test',
            'gender_group' => GenderGroup::Unisex,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Unisex')
            ->assertDontSee('Mọi giới');
    }

    public function test_storefront_auth_and_admin_titles_do_not_double_encode_special_characters(): void
    {
        $this->get('/categories')
            ->assertOk()
            ->assertSee('<title>Danh mục &amp; Phân loại — Cena</title>', false)
            ->assertDontSee('&amp;amp;', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Đăng nhập — Cena</title>', false)
            ->assertDontSee('&amp;amp;', false);

        $specialName = 'Giày Nike & Jordan "Air" <Pro> Đỏ';
        $category = Category::factory()->create([
            'gender_group' => GenderGroup::Unisex,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => $specialName,
            'slug' => 'giay-nike-jordan-air-pro-do-test',
            'is_active' => true,
        ]);
        $color = ProductColor::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        ProductImage::factory()->create([
            'product_color_id' => $color->id,
            'is_thumbnail' => true,
        ]);
        ProductVariant::factory()->create([
            'product_color_id' => $color->id,
            'is_active' => true,
        ]);

        $expectedEncoded = 'Giày Nike &amp; Jordan &quot;Air&quot; &lt;Pro&gt; Đỏ';

        $this->get('/shoes/'.$product->slug)
            ->assertOk()
            ->assertSee('<title>'.$expectedEncoded.' — Cena</title>', false)
            ->assertDontSee('&amp;amp;', false);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->get('/admin/products/'.$product->id)
            ->assertOk()
            ->assertSee('<title>'.$expectedEncoded.' — Quản trị Cena</title>', false)
            ->assertDontSee('&amp;amp;', false);
    }

    public function test_admin_products_index_distributes_contextual_actions_and_preserves_ajax_toggle(): void
    {
        $staff = User::factory()->staff()->create();
        $category = Category::factory()->create([
            'gender_group' => GenderGroup::Unisex,
            'is_active' => true,
        ]);

        $activeProduct = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Sản Phẩm Đang Bán UX',
            'slug' => 'san-pham-dang-ban-ux',
            'is_active' => true,
        ]);
        $activeColor = ProductColor::factory()->create([
            'product_id' => $activeProduct->id,
            'is_active' => true,
        ]);
        ProductImage::factory()->create([
            'product_color_id' => $activeColor->id,
            'is_thumbnail' => true,
        ]);

        $inactiveProduct = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Sản Phẩm Đã Ẩn UX',
            'slug' => 'san-pham-da-an-ux',
            'is_active' => false,
        ]);

        $this->actingAs($staff)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('Chi tiết')
            ->assertSee('Sửa')
            ->assertSee('Nhân bản')
            ->assertSee('Ngừng bán')
            ->assertSee('Mở bán');

        $this->actingAs($staff)
            ->patchJson('/admin/products/'.$activeProduct->id.'/deactivate')
            ->assertOk()
            ->assertJson(['is_active' => false]);

        $this->actingAs($staff)
            ->patchJson('/admin/products/'.$inactiveProduct->id.'/activate')
            ->assertOk()
            ->assertJson(['is_active' => true]);
    }
}
