<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductManagementTaxonomyService;
use Database\Seeders\ProductDemoTaxonomySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminTaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('roles')]
    public function test_permissions_for_both_admin_areas(string $role, int $status): void
    {
        if ($role !== 'guest') {
            $actor = match ($role) {
                'admin' => User::factory()->administrator()->create(),
                'staff' => User::factory()->staff()->create(),
                'customer' => User::factory()->customer()->create(),
                default => User::factory()->inactive()->administrator()->create(),
            };
            $this->actingAs($actor);
        }
        foreach (['/admin/categories', '/admin/suppliers', '/admin/categories/create', '/admin/suppliers/create'] as $url) {
            $this->getJson($url)->assertStatus($status);
        }
        if ($status !== 200) {
            $this->postJson('/admin/categories', $this->categoryData())->assertStatus($status);
            $this->postJson('/admin/suppliers', $this->supplierData())->assertStatus($status);
        }
    }

    public static function roles(): array
    {
        return [['guest', 401], ['customer', 403], ['locked', 401], ['admin', 200], ['staff', 200]];
    }

    public function test_staff_can_create_edit_search_and_hide_a_two_level_category_without_deleting_products(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->post('/admin/categories', $this->categoryData())->assertRedirectToRoute('admin.categories.index');
        $parent = Category::where('slug', 'men')->sole();
        $data = $this->categoryData(['name' => 'Running', 'slug' => 'running', 'parent_id' => $parent->id]);
        $this->post('/admin/categories', $data)->assertRedirect();
        $child = Category::where('slug', 'running')->sole();
        $this->put('/admin/categories/'.$child->id, $data + ['description' => 'Desc'])->assertRedirect();
        Product::factory()->create(['category_id' => $child->id]);
        $this->get('/admin/categories?search=Running')->assertOk()->assertSee('Running')->assertSee('Sửa');
        $this->patch('/admin/categories/'.$parent->id.'/deactivate')->assertRedirect();
        $this->assertFalse($parent->fresh()->is_active);
        $this->assertNotNull($child->fresh());
        $this->assertDatabaseCount('products', 1);
        $this->delete('/admin/categories/'.$parent->id)->assertMethodNotAllowed();
        $this->assertDatabaseHas('categories', ['id' => $parent->id, 'deleted_at' => null]);
    }

    public function test_hierarchy_prevents_self_cycles_third_level_and_gender_mismatch(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $parent = Category::factory()->create(['gender_group' => 'MEN']);
        $child = Category::factory()->create(['gender_group' => 'MEN', 'parent_id' => $parent->id]);
        $this->putJson('/admin/categories/'.$parent->id, $this->categoryData(['slug' => $parent->slug, 'parent_id' => $parent->id]))->assertUnprocessable();
        $this->putJson('/admin/categories/'.$parent->id, $this->categoryData(['slug' => $parent->slug, 'parent_id' => $child->id]))->assertUnprocessable();
        $this->postJson('/admin/categories', $this->categoryData(['parent_id' => $child->id]))->assertUnprocessable();
        $this->postJson('/admin/categories', $this->categoryData(['parent_id' => $parent->id, 'gender_group' => 'WOMEN']))->assertUnprocessable();
        $this->putJson('/admin/categories/'.$parent->id, $this->categoryData(['slug' => $parent->slug, 'gender_group' => 'WOMEN']))->assertUnprocessable();
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertNull($parent->fresh()->parent_id);
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_slug_is_unique_even_when_original_category_is_soft_deleted_and_invalid_inputs_are_422(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = Category::factory()->create(['slug' => 'men']);
        $category->delete();
        $this->postJson('/admin/categories', $this->categoryData())->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson('/admin/categories', $this->categoryData(['slug' => 'Bad / slug', 'display_order' => -1, 'gender_group' => 'other']))->assertUnprocessable()->assertJsonValidationErrors(['slug', 'display_order', 'gender_group']);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_supplier_can_be_updated_and_deactivated_after_import_without_losing_history(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->post('/admin/suppliers', $this->supplierData())->assertRedirect();
        $supplier = Supplier::where('code', 'SUP-1')->sole();
        $import = StockImport::factory()->create(['supplier_id' => $supplier->id, 'supplier_name_snapshot' => 'Old supplier']);
        $this->put('/admin/suppliers/'.$supplier->id, $this->supplierData(['name' => 'Updated']))->assertRedirect();
        $this->get('/admin/suppliers?search=Updated')->assertOk()->assertSee('Updated');
        $this->patch('/admin/suppliers/'.$supplier->id.'/deactivate')->assertRedirect();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertSame('Old supplier', $import->fresh()->supplier_name_snapshot);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
        $this->postJson('/admin/suppliers', $this->supplierData())->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/admin/suppliers', $this->supplierData(['code' => 'NEW', 'phone' => '', 'email' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'email']);
    }

    public function test_filters_pagination_and_escaping_keep_untrusted_names_as_text(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Category::factory()->count(16)->create(['name' => 'Running <script>alert(1)</script>']);
        $this->get('/admin/categories?search=Running')->assertOk()->assertSee('search=Running', false)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->getJson('/admin/categories?search[]=bad')->assertUnprocessable();
    }

    public function test_revoked_role_cannot_write_through_service_with_stale_actor(): void
    {
        $actor = User::factory()->administrator()->create();
        DB::table('user_roles')->where('user_id', $actor->id)->delete();
        try {
            app(ProductManagementTaxonomyService::class)->saveCategory($actor, null, $this->categoryData());
            $this->fail('Expected denial');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_demo_taxonomy_replay_preserves_ids_and_does_not_reactivate_records(): void
    {
        User::factory()->administrator()->create();
        $this->seed(ProductDemoTaxonomySeeder::class);
        $ids = Category::orderBy('id')->pluck('id')->all();
        Category::where('slug', 'men')->update(['is_active' => false]);
        $this->seed(ProductDemoTaxonomySeeder::class);
        $this->assertSame($ids, Category::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('categories', 4);
        $this->assertDatabaseCount('suppliers', 3);
        $this->assertDatabaseHas('categories', ['slug' => 'men', 'is_active' => false]);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    private function categoryData(array $changes = []): array
    {
        return array_replace(['name' => 'Men', 'slug' => 'men', 'parent_id' => null, 'gender_group' => 'MEN', 'display_order' => 1, 'is_active' => 1], $changes);
    }

    private function supplierData(array $changes = []): array
    {
        return array_replace(['code' => 'SUP-1', 'name' => 'Supplier', 'phone' => '0901234567', 'email' => 'supplier@example.test', 'address' => 'HCM', 'is_active' => 1], $changes);
    }
}
