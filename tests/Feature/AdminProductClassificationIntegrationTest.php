<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Models\Category;
use App\Models\Classification;
use App\Models\User;
use App\Services\ProductManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminProductClassificationIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
        $this->category = Category::factory()->create();
    }

    private function validProductPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Giày Demo Phân Loại',
            'slug' => 'giay-demo-phan-loai-'.uniqid(),
            'category_id' => $this->category->id,
            'base_price' => 1_500_000,
            'promotion_price' => null,
            'warranty_months' => 6,
            'exchange_days' => 7,
            'is_active' => 1,
            'is_featured' => 1,
            'colors' => [
                [
                    'style_color_code' => 'DEMO-01-'.uniqid(),
                    'color_name' => 'Đen',
                    'color_key' => 'black',
                    'primary_hex' => '#000000',
                    'variants' => [
                        ['size_value' => '40', 'sku' => 'SKU-DEMO-40-'.uniqid(), 'price_override' => null],
                    ],
                    'images' => [
                        [
                            'external_url' => 'https://example.com/demo.jpg',
                            'alt_text' => 'Demo image',
                            'is_thumbnail' => 1,
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_can_save_product_with_classifications_and_is_featured(): void
    {
        $c1 = Classification::query()->create([
            'name' => 'Sneaker',
            'slug' => 'sneaker-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
        ]);
        $c2 = Classification::query()->create([
            'name' => 'Chạy bộ',
            'slug' => 'chay-bo-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
        ]);

        $payload = $this->validProductPayload([
            'is_featured' => 1,
            'classification_ids' => [$c1->id, $c2->id],
        ]);

        $service = app(ProductManagementService::class);
        $product = $service->save($this->admin, null, $payload);

        $this->assertTrue($product->is_featured);
        $this->assertCount(2, $product->classifications);
        $this->assertTrue($product->classifications->contains('id', $c1->id));
        $this->assertTrue($product->classifications->contains('id', $c2->id));
    }

    public function test_can_update_product_classifications_sync(): void
    {
        $c1 = Classification::query()->create([
            'name' => 'Tag 1',
            'slug' => 'tag-1-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
        ]);
        $c2 = Classification::query()->create([
            'name' => 'Tag 2',
            'slug' => 'tag-2-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
        ]);
        $c3 = Classification::query()->create([
            'name' => 'Tag 3',
            'slug' => 'tag-3-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
        ]);

        $service = app(ProductManagementService::class);
        $product = $service->save($this->admin, null, $this->validProductPayload([
            'classification_ids' => [$c1->id, $c2->id],
        ]));

        $color = $product->colors->first();
        $variant = $color->variants->first();
        $image = $color->images->first();

        // Update: remove c1, keep c2, add c3
        $updatePayload = $this->validProductPayload([
            'name' => 'Updated Product Name',
            'slug' => $product->slug,
            'is_featured' => 0,
            'classification_ids' => [$c2->id, $c3->id],
            'colors' => [
                [
                    'id' => $color->id,
                    'style_color_code' => $color->style_color_code,
                    'color_name' => $color->color_name,
                    'color_key' => $color->color_key,
                    'primary_hex' => $color->primary_hex,
                    'variants' => [
                        ['id' => $variant->id, 'size_value' => $variant->size_value, 'sku' => $variant->sku, 'price_override' => null],
                    ],
                    'images' => [
                        ['id' => $image->id, 'external_url' => $image->external_url, 'alt_text' => $image->alt_text, 'is_thumbnail' => 1],
                    ],
                ],
            ],
        ]);

        $updatedProduct = $service->save($this->admin, $product, $updatePayload);

        $this->assertFalse($updatedProduct->is_featured);
        $this->assertCount(2, $updatedProduct->classifications);
        $this->assertFalse($updatedProduct->classifications->contains('id', $c1->id));
        $this->assertTrue($updatedProduct->classifications->contains('id', $c2->id));
        $this->assertTrue($updatedProduct->classifications->contains('id', $c3->id));
    }

    public function test_cannot_attach_newly_inactive_classification(): void
    {
        $inactive = Classification::query()->create([
            'name' => 'Inactive Tag',
            'slug' => 'inactive-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => false,
        ]);

        $service = app(ProductManagementService::class);

        $this->expectException(ValidationException::class);
        $service->save($this->admin, null, $this->validProductPayload([
            'classification_ids' => [$inactive->id],
        ]));
    }

    public function test_existing_inactive_classification_is_preserved_when_not_modified(): void
    {
        $cActive = Classification::query()->create([
            'name' => 'Active Tag',
            'slug' => 'active-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
        ]);

        $cOld = Classification::query()->create([
            'name' => 'Old Inactive Tag',
            'slug' => 'old-inactive-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
        ]);

        $service = app(ProductManagementService::class);
        $product = $service->save($this->admin, null, $this->validProductPayload([
            'classification_ids' => [$cActive->id, $cOld->id],
        ]));

        // Now cOld becomes inactive
        $cOld->forceFill(['is_active' => false])->save();

        $color = $product->colors->first();
        $variant = $color->variants->first();
        $image = $color->images->first();

        // Save again keeping existing classifications [cActive, cOld]
        $updatePayload = $this->validProductPayload([
            'name' => 'Product Name Unchanged',
            'slug' => $product->slug,
            'classification_ids' => [$cActive->id, $cOld->id],
            'colors' => [
                [
                    'id' => $color->id,
                    'style_color_code' => $color->style_color_code,
                    'color_name' => $color->color_name,
                    'color_key' => $color->color_key,
                    'primary_hex' => $color->primary_hex,
                    'variants' => [
                        ['id' => $variant->id, 'size_value' => $variant->size_value, 'sku' => $variant->sku, 'price_override' => null],
                    ],
                    'images' => [
                        ['id' => $image->id, 'external_url' => $image->external_url, 'alt_text' => $image->alt_text, 'is_thumbnail' => 1],
                    ],
                ],
            ],
        ]);

        // Should succeed without throwing validation exception because cOld was already attached
        $updated = $service->save($this->admin, $product, $updatePayload);
        $this->assertCount(2, $updated->classifications);
        $this->assertTrue($updated->classifications->contains('id', $cOld->id));
    }

    public function test_admin_product_form_renders_classifications_picker_and_featured_checkbox(): void
    {
        $c = Classification::query()->create([
            'name' => 'Sneaker Test',
            'slug' => 'sneaker-test-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));
        $response->assertOk();
        $response->assertSee('Sneaker Test');
        $response->assertSee('Sản phẩm nổi bật');
        $response->assertSee('classification_ids[]');
    }
}
