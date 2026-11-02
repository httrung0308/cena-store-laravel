<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\ProductManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actor = User::factory()->staff()->create();
    }

    public function test_one_save_creates_two_colors_three_sizes_each_and_albums_with_zero_inventory(): void
    {
        $this->actingAs($this->actor);
        $data = $this->data();
        $this->post('/admin/products', $data)->assertRedirect();
        $product = Product::sole()->load('colors.variants', 'colors.images');
        $this->assertCount(2, $product->colors);
        foreach ($product->colors as $color) {
            $this->assertCount(3, $color->variants);
            $this->assertCount(2, $color->images);
            $this->assertSame(['40', '40.5', '41'], $color->variants->pluck('size_value')->all());
            $this->assertSame(1, $color->images->where('is_thumbnail', true)->count());
            foreach ($color->variants as $variant) {
                $this->assertSame([0, 0, 0, '0.0000'], [$variant->stock_qty, $variant->reserved_qty, $variant->quarantine_qty, $variant->average_cost]);
            }
        }
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertDontSee('name="stock_qty"', false)->assertDontSee('name="average_cost"', false);
        $this->get('/admin/products')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>evil</script>', false);
    }

    public function test_size_sequence_is_generated_on_server_and_keeps_half_size_strings(): void
    {
        $data = $this->data();
        $data['colors'] = [$data['colors'][0]];
        $data['colors'][0]['variants'] = [];
        $data['colors'][0] += ['range_start' => '40', 'range_end' => '41', 'sku_prefix' => 'SEQUENCE'];
        $product = app(ProductManagementService::class)->save($this->actor, null, $data);
        $this->assertSame(['40', '40.5', '41'], $product->variants()->orderBy('size_numeric')->pluck('size_value')->all());
        $this->assertSame(['SEQUENCE-40', 'SEQUENCE-40_5', 'SEQUENCE-41'], $product->variants()->orderBy('size_numeric')->pluck('sku')->all());
    }

    #[DataProvider('badCases')]
    public function test_bad_payload_rolls_back_all_records(string $case): void
    {
        $this->actingAs($this->actor);
        $data = $this->data();
        switch ($case) {
            case 'canonical-duplicate': $data['colors'][0]['variants'][1]['size_value'] = '40.0';
                break;
            case 'fraction': $data['colors'][0]['variants'][0]['size_value'] = '40.2';
                break;
            case 'float-size': $data['colors'][0]['variants'][0]['size_value'] = 40.5;
                break;
            case 'zero-price': $data['base_price'] = 0;
                break;
            case 'sale-above-base':$data['promotion_price'] = $data['base_price'] + 1;
                break;
            case 'float-money':$data['base_price'] = 100.1;
                break;
            case 'zero-override':$data['colors'][0]['variants'][0]['price_override'] = 0;
                break;
            case 'bad-url':$data['colors'][0]['images'][0]['external_url'] = 'javascript:alert(1)';
                break;
            case 'url-credentials':$data['colors'][0]['images'][0]['external_url'] = 'https://user:secret@example.com/a.jpg';
                break;
            case 'missing-source':unset($data['colors'][0]['images'][0]['external_url']);
                break;
            case 'two-thumbnails':$data['colors'][0]['images'][1]['is_thumbnail'] = true;
                break;
            case 'no-thumbnail':$data['colors'][0]['images'][0]['is_thumbnail'] = false;
                break;
            case 'unknown-category':$data['category_id'] = PHP_INT_MAX;
                break;
            case 'reversed-range':$data['colors'][0]['variants'] = [];
                $data['colors'][0] += ['range_start' => '41', 'range_end' => '40', 'sku_prefix' => 'RANGE'];
                break;
        }
        $this->postJson('/admin/products', $data)->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_colors', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function badCases(): array
    {
        return array_map(fn ($v) => [$v], ['canonical-duplicate', 'fraction', 'float-size', 'zero-price', 'sale-above-base', 'float-money', 'zero-override', 'bad-url', 'url-credentials', 'missing-source', 'two-thumbnails', 'no-thumbnail', 'unknown-category', 'reversed-range']);
    }

    #[DataProvider('forbiddenFields')]
    public function test_stock_cost_cannot_be_written_from_product_form(string $field): void
    {
        $this->actingAs($this->actor);
        $data = $this->data();
        $data['colors'][0]['variants'][0][$field] = 999;
        $this->postJson('/admin/products', $data)->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function forbiddenFields(): array
    {
        return [['stock_qty'], ['reserved_qty'], ['quarantine_qty'], ['average_cost'], ['size_numeric'], ['unit_cost_basis']];
    }

    public function test_real_mime_is_checked_instead_of_client_mime_or_extension(): void
    {
        $this->actingAs($this->actor);
        $data = $this->data();
        $temp = tempnam(sys_get_temp_dir(), 'cena-mime-');
        try {
            file_put_contents($temp, '<?php echo "not an image";');
            unset($data['colors'][0]['images'][1]['external_url']);
            $data['colors'][0]['images'][1]['file'] = new UploadedFile($temp, 'fake.jpg', 'image/jpeg', null, true);
            $this->post('/admin/products', $data, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('colors.0.images.1.file');
            $this->assertDatabaseCount('products', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    public function test_duplicate_sku_in_late_color_rolls_back_database_and_removes_staged_upload(): void
    {
        $data = $this->data();
        unset($data['colors'][0]['images'][0]['external_url']);
        $data['colors'][0]['images'][0]['file'] = UploadedFile::fake()->image('shoe.jpg');
        $data['colors'][1]['variants'][2]['sku'] = $data['colors'][0]['variants'][0]['sku'];
        $this->actingAs($this->actor)->post('/admin/products', $data, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('colors');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_success_uses_server_path_and_album_removal_preserves_existing_file(): void
    {
        $data = $this->data();
        unset($data['colors'][0]['images'][1]['external_url']);
        $data['colors'][0]['images'][1]['file'] = UploadedFile::fake()->image('photo.png');
        $this->actingAs($this->actor)->post('/admin/products', $data)->assertRedirect()->assertSessionHasNoErrors();
        $product = Product::sole();
        $upload = ProductImage::where('source_type', 'UPLOAD')->sole();
        $this->assertStringStartsWith('products/', $upload->storage_path);
        Storage::disk('public')->assertExists($upload->storage_path);
        $update = $this->updateData($product);
        $update['colors'][0]['images'] = [$update['colors'][0]['images'][0]];
        app(ProductManagementService::class)->save($this->actor, $product, $update);
        $this->assertDatabaseMissing('product_images', ['id' => $upload->id]);
        Storage::disk('public')->assertExists($upload->storage_path);
    }

    #[DataProvider('childTypes')]
    public function test_foreign_nested_ids_return_404_and_keep_original_graph(string $type): void
    {
        $first = app(ProductManagementService::class)->save($this->actor, null, $this->data());
        $secondData = $this->data('OTHER');
        $second = app(ProductManagementService::class)->save($this->actor, null, $secondData);
        $input = $this->updateData($second);
        $other = $this->updateData($first);
        match ($type) {
            'color' => $input['colors'][0]['id'] = $other['colors'][0]['id'],
            'variant' => $input['colors'][0]['variants'][0]['id'] = $other['colors'][0]['variants'][0]['id'],
            'image' => $input['colors'][0]['images'][0]['id'] = $other['colors'][0]['images'][0]['id'],
        };
        $input['name'] = 'Must roll back';
        $this->actingAs($this->actor)->putJson('/admin/products/'.$second->id, $input)->assertNotFound();
        $this->assertNotSame('Must roll back', $second->fresh()->name);
        $this->assertDatabaseCount('product_variants', 12);
        $this->assertDatabaseCount('product_images', 8);
    }

    public static function childTypes(): array
    {
        return [['color'], ['variant'], ['image']];
    }

    public function test_documented_sku_and_color_codes_are_immutable_but_omission_only_deactivates(): void
    {
        $product = app(ProductManagementService::class)->save($this->actor, null, $this->data());
        $variant = $product->variants()->first();
        StockImportItem::factory()->create(['variant_id' => $variant->id]);
        $input = $this->updateData($product);
        $input['colors'][0]['variants'][0]['sku'] = 'CHANGED';
        $this->actingAs($this->actor)->putJson('/admin/products/'.$product->id, $input)->assertUnprocessable();
        $input = $this->updateData($product);
        $input['colors'][0]['style_color_code'] = 'NEW-COLOR';
        $this->putJson('/admin/products/'.$product->id, $input)->assertUnprocessable();
        $input = $this->updateData($product);
        array_shift($input['colors'][0]['variants']);
        $this->put('/admin/products/'.$product->id, $input)->assertRedirect();
        $this->assertFalse($variant->fresh()->is_active);
        $this->assertSame(0, $variant->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_import_items', ['variant_id' => $variant->id]);
        $this->patch('/admin/products/'.$product->id.'/deactivate')->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseCount('product_variants', 6);
    }

    public function test_customer_and_guest_cannot_read_or_write_admin_products_and_staff_can(): void
    {
        $this->getJson('/admin/products')->assertUnauthorized();
        $this->postJson('/admin/products', $this->data())->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create());
        $this->getJson('/admin/products')->assertForbidden();
        $this->postJson('/admin/products', $this->data())->assertForbidden();
        $this->actingAs($this->actor)->get('/admin/products/create')->assertOk();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_higher_override_is_kept_and_replay_update_does_not_create_children(): void
    {
        $input = $this->data();
        $input['colors'][0]['variants'][0]['price_override'] = 3000000;
        $product = app(ProductManagementService::class)->save($this->actor, null, $input);
        $data = $this->updateData($product);
        app(ProductManagementService::class)->save($this->actor, $product, $data);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_colors', 2);
        $this->assertDatabaseCount('product_variants', 6);
        $this->assertSame(3000000, $product->variants()->first()->price_override);
        $this->assertSame(2, ProductImage::where('is_thumbnail', true)->count());
    }

    public function test_both_image_sources_are_rejected_in_a_real_multipart_request(): void
    {
        $data = $this->data();
        $data['colors'][0]['images'][0]['file'] = UploadedFile::fake()->image('shoe.jpg');
        $this->actingAs($this->actor)->post('/admin/products', $data, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('colors.0.images.0');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_admin_can_switch_thumbnail_without_creating_duplicate_thumbnail_or_image(): void
    {
        $actor = User::factory()->administrator()->create();
        $product = app(ProductManagementService::class)->save($actor, null, $this->data());
        $data = $this->updateData($product);
        $data['colors'][0]['images'][0]['is_thumbnail'] = false;
        $data['colors'][0]['images'][1]['is_thumbnail'] = true;
        $this->actingAs($actor)->put('/admin/products/'.$product->id, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($data['colors'][0]['images'][1]['id'], $product->colors()->first()->thumbnail->id);
        $this->assertDatabaseCount('product_images', 4);
    }

    private function data(string $prefix = 'SHOE'): array
    {
        $colors = [];
        foreach (['black', 'white'] as $i => $key) {
            $variants = [];
            foreach (['40', '40.5', '41'] as $size) {
                $variants[] = ['size_value' => $size, 'sku' => $prefix.'-'.$key.'-'.str_replace('.', '_', $size), 'price_override' => null, 'is_active' => 1];
            }
            $colors[] = ['style_color_code' => $prefix.'-'.$key, 'color_name' => $key, 'color_key' => $key, 'primary_hex' => $i ? '#ffffff' : '#000000', 'is_active' => 1, 'variants' => $variants,
                'images' => [['external_url' => 'https://example.com/a.jpg', 'alt_text' => 'Shoe', 'is_thumbnail' => true], ['external_url' => 'https://example.com/b.jpg', 'alt_text' => 'Side', 'is_thumbnail' => false]]];
        }

        return ['name' => 'Cena <script>evil</script>', 'slug' => strtolower($prefix), 'category_id' => Category::factory()->create()->id, 'base_price' => 2000000, 'promotion_price' => 1500000, 'warranty_months' => 6, 'exchange_days' => 7, 'is_active' => 1, 'colors' => $colors];
    }

    private function updateData(Product $product): array
    {
        $product->load('colors.variants', 'colors.images');
        $data = array_intersect_key($product->toArray(), array_flip(['name', 'slug', 'category_id', 'brand_line', 'description', 'base_price', 'promotion_price', 'warranty_months', 'exchange_days', 'is_active']));
        $data['colors'] = $product->colors->map(fn ($c) => ['id' => $c->id, 'style_color_code' => $c->style_color_code, 'color_name' => $c->color_name, 'color_key' => $c->color_key, 'primary_hex' => $c->primary_hex, 'is_active' => $c->is_active,
            'variants' => $c->variants->map(fn ($v) => ['id' => $v->id, 'sku' => $v->sku, 'size_value' => $v->size_value, 'price_override' => $v->price_override, 'is_active' => $v->is_active])->all(),
            'images' => $c->images->map(fn ($i) => ['id' => $i->id, 'external_url' => $i->external_url, 'alt_text' => $i->alt_text, 'is_thumbnail' => $i->is_thumbnail])->all()])->all();

        return $data;
    }
}
