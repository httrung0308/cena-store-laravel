<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductFormEnhancementsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
    }

    public function test_sku_generator_creates_stable_uppercase_unique_sku(): void
    {
        $sku1 = ProductManagementService::generateSku('giay-chay-bo-pegasus-40', 'BLK', '40');
        $this->assertSame('GIAYCHAY-BLK-40', $sku1);

        $skuHalf = ProductManagementService::generateSku('giay-chay-bo-pegasus-40', 'BLK', '40.5');
        $this->assertSame('GIAYCHAY-BLK-40_5', $skuHalf);

        // Test collision adds suffix
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        ProductVariant::factory()->create(['product_color_id' => $color->id, 'sku' => 'GIAYCHAY-BLK-40']);

        $skuCollision = ProductManagementService::generateSku('giay-chay-bo-pegasus-40', 'BLK', '40');
        $this->assertSame('GIAYCHAY-BLK-40-01', $skuCollision);
    }

    public function test_admin_can_search_variants_via_api(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Air Pegasus Zoom']);
        $color = ProductColor::factory()->create(['product_id' => $product->id, 'color_name' => 'Xanh Neon', 'style_color_code' => 'BLU']);
        $variant = ProductVariant::factory()->create([
            'product_color_id' => $color->id,
            'sku' => 'PEG-BLU-42',
            'size_value' => '42',
            'stock_qty' => 15,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/api/variants/search?q=Pegasus');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $variant->id,
                'sku' => 'PEG-BLU-42',
                'size_value' => '42',
                'color_name' => 'Xanh Neon',
            ]);
    }

    public function test_admin_can_open_duplicate_product_form(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Giày Thể Thao']);
        $color = ProductColor::factory()->create(['product_id' => $product->id, 'color_name' => 'Đen']);
        ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'sku' => 'GTT-BLK-41']);

        $response = $this->actingAs($this->admin)->get(route('admin.products.duplicate', $product));

        $response->assertOk()
            ->assertSee('Nhân bản sản phẩm')
            ->assertSee('Giày Thể Thao (Bản sao)');
    }

    public function test_duplicate_product_creates_new_product_and_safely_copies_images(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Original Shoe']);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);

        $originalPath = 'products/sample_shoe_test.jpg';
        Storage::disk('public')->put($originalPath, 'image-binary-data');

        ProductImage::factory()->create([
            'product_color_id' => $color->id,
            'storage_path' => $originalPath,
            'is_thumbnail' => true,
        ]);

        $payload = [
            'name' => 'Original Shoe (Bản sao)',
            'slug' => 'original-shoe-ban-sao',
            'category_id' => $category->id,
            'base_price' => 1200000,
            'warranty_months' => 6,
            'exchange_days' => 7,
            'is_active' => 0,
            'colors' => [
                [
                    'color_name' => 'Đen',
                    'style_color_code' => 'BLK',
                    'color_key' => 'black',
                    'primary_hex' => '#000000',
                    'variants' => [
                        [
                            'size_value' => '42',
                            'sku' => '', // auto generate
                        ],
                    ],
                    'images' => [
                        [
                            'clone_source_path' => $originalPath,
                            'alt_text' => 'Ảnh sao chép',
                            'is_thumbnail' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $service = app(ProductManagementService::class);
        $newProduct = $service->save($this->admin, null, $payload);

        $this->assertNotSame($product->id, $newProduct->id);
        $this->assertSame('Original Shoe (Bản sao)', $newProduct->name);

        $newImage = $newProduct->colors->first()->images->first();
        $this->assertNotNull($newImage->storage_path);
        $this->assertNotSame($originalPath, $newImage->storage_path);
        Storage::disk('public')->assertExists($originalPath);
        Storage::disk('public')->assertExists($newImage->storage_path);
    }

    public function test_can_download_product_and_stock_import_templates(): void
    {
        $resCsv = $this->actingAs($this->admin)->get(route('admin.products.template', ['format' => 'csv']));
        $resCsv->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="mau_nhap_san_pham.csv"');

        $resXlsx = $this->actingAs($this->admin)->get(route('admin.products.template', ['format' => 'xlsx']));
        $resXlsx->assertOk();

        $resStockCsv = $this->actingAs($this->admin)->get(route('admin.stock-imports.template', ['format' => 'csv']));
        $resStockCsv->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="mau_nhap_kho.csv"');

        $resStockXlsx = $this->actingAs($this->admin)->get(route('admin.stock-imports.template', ['format' => 'xlsx']));
        $resStockXlsx->assertOk();
    }

    public function test_product_spreadsheet_preview_and_import_creates_inactive_products_without_images(): void
    {
        $category = Category::factory()->create(['name' => 'Giày Nam']);

        $csvContent = "\xEF\xBB\xBF".
            "Tên sản phẩm,Danh mục,Dòng thương hiệu,Giá gốc,Giá khuyến mãi,Bảo hành (tháng),Đổi size (ngày),Mô tả,Tên màu,Mã màu,Mã hex,Nhóm màu,Dải size,Giá riêng\n".
            "Giày Test Import,Giày Nam,Runner,1500000,,6,7,Giày chạy tốt,Đen Trắng,BLK,#000000,black,40-42,\n";

        $tempFile = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

        $previewRes = $this->actingAs($this->admin)->post(route('admin.products.import.preview'), [
            'file' => $tempFile,
        ], ['Accept' => 'application/json']);

        $previewRes->assertOk();
        $data = $previewRes->json();
        $this->assertCount(1, $data['products']);
        $this->assertEmpty($data['errors']);
        $this->assertSame('Giày Test Import', $data['products'][0]['name']);

        // Test store import
        $storeRes = $this->actingAs($this->admin)->post(route('admin.products.import.store'), [
            'products_json' => json_encode($data['products']),
        ]);

        $storeRes->assertRedirect(route('admin.products.index'));

        $created = Product::where('name', 'Giày Test Import')->first();
        $this->assertNotNull($created);
        $this->assertFalse((bool) $created->is_active); // Must be inactive because no images yet
        $this->assertSame(5, $created->variants()->count()); // sizes 40, 40.5, 41, 41.5, 42 (5 sizes)
    }

    public function test_coupon_validation_rules_for_different_types(): void
    {
        // Free shipping requires value = 0
        $resFreeShip = $this->actingAs($this->admin)->post('/admin/coupons', [
            'code' => 'FREESHIP100',
            'name' => 'Miễn phí vận chuyển',
            'type' => CouponType::FreeShipping->value,
            'value' => 0,
            'min_order_amount' => 500000,
            'per_user_limit' => 1,
        ]);
        $resFreeShip->assertRedirect(route('admin.coupons.index'));

        // Percentage discount
        $resPercent = $this->actingAs($this->admin)->post('/admin/coupons', [
            'code' => 'SALE20',
            'name' => 'Giảm 20%',
            'type' => CouponType::Percentage->value,
            'value' => 20,
            'min_order_amount' => 300000,
            'max_discount_amount' => 100000,
            'per_user_limit' => 1,
        ]);
        $resPercent->assertRedirect(route('admin.coupons.index'));
    }

    public function test_home_sidebar_limits_published_articles_to_four(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            Article::factory()->published()->create([
                'author_id' => $this->admin->id,
                'title' => "Sidebar Limit Article {$i}",
                'published_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $response = $this->get(route('home'));
        $response->assertOk()
            ->assertSee('Tất cả bài viết')
            ->assertViewHas('articles', fn ($articles) => $articles->count() === 4);
    }

    public function test_admin_and_staff_product_detail_page_respects_cost_permission_and_public_visibility(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Giày Chi Tiết Quản Trị',
            'is_active' => true,
        ]);
        $color = ProductColor::factory()->create([
            'product_id' => $product->id,
            'color_name' => 'Trắng Xanh',
            'style_color_code' => 'WHTBLU',
            'is_active' => true,
        ]);
        ProductImage::factory()->create([
            'product_color_id' => $color->id,
            'is_thumbnail' => true,
        ]);
        ProductVariant::factory()->create([
            'product_color_id' => $color->id,
            'sku' => 'CTQT-WHTBLU-41',
            'size_value' => '41',
            'stock_qty' => 12,
            'average_cost' => '850000.0000',
            'is_active' => true,
        ]);

        // Admin sees cost and public storefront link
        $adminRes = $this->actingAs($this->admin)->get(route('admin.products.show', $product));
        $adminRes->assertOk()
            ->assertSee('Giày Chi Tiết Quản Trị')
            ->assertSee('CTQT-WHTBLU-41')
            ->assertSee('Giá vốn BQ')
            ->assertSee('850.000 ₫')
            ->assertSee('Xem ngoài cửa hàng');

        // Staff sees details and stock, but NOT average cost
        $staff = User::factory()->staff()->create();
        $staffRes = $this->actingAs($staff)->get(route('admin.products.show', $product));
        $staffRes->assertOk()
            ->assertSee('Giày Chi Tiết Quản Trị')
            ->assertSee('CTQT-WHTBLU-41')
            ->assertDontSee('Giá vốn BQ')
            ->assertDontSee('850.000 ₫');

        // Deactivate product -> hides storefront link and shows blocking reason
        $product->forceFill(['is_active' => false])->save();
        $inactiveRes = $this->actingAs($this->admin)->get(route('admin.products.show', $product));
        $inactiveRes->assertOk()
            ->assertSee('Sản phẩm chưa hiển thị ngoài cửa hàng')
            ->assertDontSee('Xem ngoài cửa hàng');
    }

    public function test_ajax_product_store_and_update_autofill_alt_text_and_preserve_slug_sku(): void
    {
        $category = Category::factory()->create();

        $storeRes = $this->actingAs($this->admin)->postJson(route('admin.products.store'), [
            'name' => 'Giày Chạy Bộ Cena AJAX',
            'slug' => 'giay-chay-bo-cena-ajax',
            'category_id' => $category->id,
            'base_price' => 1500000,
            'warranty_months' => 6,
            'exchange_days' => 7,
            'is_active' => true,
            'colors' => [
                [
                    'color_name' => 'Đen Trắng',
                    'style_color_code' => 'BLK',
                    'color_key' => 'black',
                    'primary_hex' => '#111111',
                    'is_active' => true,
                    'variants' => [
                        ['size_value' => '41', 'sku' => '', 'is_active' => true],
                    ],
                    'images' => [
                        ['external_url' => 'https://example.com/shoe-blk.jpg', 'alt_text' => '', 'is_thumbnail' => true],
                    ],
                ],
            ],
        ]);

        $storeRes->assertCreated()
            ->assertJsonStructure(['message', 'redirect', 'product_id']);

        $product = Product::findOrFail($storeRes->json('product_id'));
        $color = $product->colors()->with(['variants', 'images'])->firstOrFail();
        $this->assertSame('Giày Chạy Bộ Cena AJAX - Đen Trắng', $color->images->firstOrFail()->alt_text);
        $originalSku = $color->variants->firstOrFail()->sku;
        $this->assertNotEmpty($originalSku);

        $updateRes = $this->actingAs($this->admin)->putJson(route('admin.products.update', $product), [
            'name' => 'Giày Chạy Bộ Cena AJAX Bản Mới',
            'slug' => 'giay-chay-bo-cena-ajax',
            'category_id' => $category->id,
            'base_price' => 1600000,
            'warranty_months' => 6,
            'exchange_days' => 7,
            'is_active' => true,
            'colors' => [
                [
                    'id' => $color->id,
                    'color_name' => 'Đen Trắng',
                    'style_color_code' => 'BLK',
                    'color_key' => 'black',
                    'primary_hex' => '#111111',
                    'is_active' => true,
                    'variants' => [
                        ['id' => $color->variants->firstOrFail()->id, 'size_value' => '41', 'sku' => $originalSku, 'is_active' => true],
                    ],
                    'images' => [
                        ['id' => $color->images->firstOrFail()->id, 'external_url' => 'https://example.com/shoe-blk.jpg', 'alt_text' => '', 'is_thumbnail' => true],
                    ],
                ],
            ],
        ]);

        $updateRes->assertOk()->assertJsonPath('product_id', $product->id);
        $product->refresh();
        $this->assertSame('giay-chay-bo-cena-ajax', $product->slug);
        $this->assertSame($originalSku, $product->colors()->firstOrFail()->variants()->firstOrFail()->sku);
    }

    public function test_ajax_validation_returns_422_with_nested_field_errors_without_saving(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['slug' => 'slug-da-ton-tai', 'category_id' => $category->id]);

        $res = $this->actingAs($this->admin)->postJson(route('admin.products.store'), [
            'name' => 'Giày Lỗi Validation',
            'slug' => 'slug-da-ton-tai',
            'category_id' => $category->id,
            'base_price' => 1000000,
            'warranty_months' => 6,
            'exchange_days' => 7,
            'is_active' => true,
            'colors' => [
                [
                    'color_name' => 'Đỏ',
                    'style_color_code' => 'RED',
                    'color_key' => 'red',
                    'primary_hex' => '#FF0000',
                    'variants' => [
                        ['size_value' => '40', 'price_override' => 0, 'is_active' => true],
                    ],
                    'images' => [
                        ['external_url' => 'https://example.com/red.jpg', 'alt_text' => '', 'is_thumbnail' => true],
                    ],
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['slug', 'colors.0.variants.0.price_override']);
        $this->assertDatabaseMissing('products', ['name' => 'Giày Lỗi Validation']);
    }
}
