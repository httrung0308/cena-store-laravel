<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Models\Classification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminClassificationCrudTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_admin_can_view_classification_list(): void
    {
        Classification::forceCreate([
            'name' => 'Sneaker Test Tag',
            'slug' => 'sneaker-test-tag-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.classifications.index'));
        $response->assertOk();
        $response->assertSee('Sneaker Test Tag');
    }

    public function test_staff_cannot_access_classification_management(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.classifications.index'));
        $response->assertForbidden();

        $createResponse = $this->actingAs($this->staff)->get(route('admin.classifications.create'));
        $createResponse->assertForbidden();
    }

    public function test_admin_can_create_new_classification(): void
    {
        $slug = 'running-new-'.uniqid();
        $payload = [
            'name' => 'Chạy Bộ Đường Trường',
            'slug' => $slug,
            'classification_group' => 'ACTIVITY',
            'sort_order' => 5,
            'is_active' => '1',
            'description' => 'Mô tả nhãn chạy bộ',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.classifications.store'), $payload);
        $response->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'slug' => $slug,
            'classification_group' => 'ACTIVITY',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_update_classification(): void
    {
        $classification = Classification::forceCreate([
            'name' => 'Old Name',
            'slug' => 'old-slug-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $updatedSlug = 'new-slug-'.uniqid();
        $payload = [
            'name' => 'New Name',
            'slug' => $updatedSlug,
            'classification_group' => 'SILHOUETTE',
            'sort_order' => 10,
            'is_active' => '1',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.classifications.update', $classification), $payload);
        $response->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'id' => $classification->id,
            'name' => 'New Name',
            'slug' => $updatedSlug,
            'sort_order' => 10,
        ]);
    }

    public function test_admin_can_toggle_active_status(): void
    {
        $classification = Classification::forceCreate([
            'name' => 'Toggle Tag',
            'slug' => 'toggle-tag-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->patch(route('admin.classifications.toggle-active', $classification));
        $this->assertFalse($classification->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.classifications.toggle-active', $classification));
        $this->assertTrue($classification->fresh()->is_active);
    }

    public function test_cannot_change_group_when_classification_has_products(): void
    {
        $product = Product::factory()->create();
        $classification = Classification::forceCreate([
            'name' => 'Fixed Group Tag',
            'slug' => 'fixed-group-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
        ]);
        $product->classifications()->attach($classification->id);

        $payload = [
            'name' => 'Fixed Group Tag',
            'slug' => $classification->slug,
            'classification_group' => 'ACTIVITY', // Attempt to change from SILHOUETTE to ACTIVITY
            'sort_order' => 1,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.classifications.update', $classification), $payload);
        $response->assertSessionHasErrors('classification_group');

        $this->assertSame(ClassificationGroup::Silhouette, $classification->fresh()->classification_group);
    }

    public function test_cannot_delete_classification_with_linked_products(): void
    {
        $product = Product::factory()->create();
        $classification = Classification::forceCreate([
            'name' => 'In Use Tag',
            'slug' => 'in-use-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
        ]);
        $product->classifications()->attach($classification->id);

        $response = $this->actingAs($this->admin)->delete(route('admin.classifications.destroy', $classification));
        $response->assertSessionHasErrors('classification');

        $this->assertDatabaseHas('classifications', ['id' => $classification->id]);
    }

    public function test_can_delete_unused_classification(): void
    {
        $classification = Classification::forceCreate([
            'name' => 'Unused Tag',
            'slug' => 'unused-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.classifications.destroy', $classification));
        $response->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseMissing('classifications', ['id' => $classification->id]);
    }
}
