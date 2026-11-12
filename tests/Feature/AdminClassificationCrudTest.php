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

    public function test_staff_cannot_toggle_home_nav(): void
    {
        $classification = Classification::forceCreate([
            'name' => 'Staff Blocked Nav',
            'slug' => 'staff-blocked-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
        ]);

        $this->actingAs($this->staff)
            ->patch(route('admin.classifications.toggle-home-nav', $classification))
            ->assertForbidden();

        $this->assertFalse($classification->fresh()->show_in_home_nav);
    }

    public function test_admin_can_toggle_and_sort_home_nav_with_max_6_active_per_group_and_reactivation_guard(): void
    {
        // Tạo sẵn 6 mục SILHOUETTE đang hoạt động & được chọn ở menu/trang chủ
        for ($i = 1; $i <= 6; $i++) {
            Classification::forceCreate([
                'name' => "Selected Sil {$i}",
                'slug' => 'selected-sil-'.$i.'-'.uniqid(),
                'classification_group' => ClassificationGroup::Silhouette,
                'sort_order' => $i,
                'is_active' => true,
                'show_in_home_nav' => true,
            ]);
        }

        // 1. Không thể tạo thêm mục thứ 7 active + show_in_home_nav = 1 trong cùng nhóm SILHOUETTE
        $this->actingAs($this->admin)->post(route('admin.classifications.store'), [
            'name' => 'Sil Seventh Blocked',
            'slug' => 'sil-seventh-'.uniqid(),
            'classification_group' => 'SILHOUETTE',
            'sort_order' => 7,
            'is_active' => '1',
            'show_in_home_nav' => '1',
        ])->assertSessionHasErrors('show_in_home_nav');

        // 2. Nhưng nhóm ACTIVITY vẫn độc lập và chọn được bình thường
        $actSlug = 'act-first-'.uniqid();
        $this->actingAs($this->admin)->post(route('admin.classifications.store'), [
            'name' => 'Activity First Selected',
            'slug' => $actSlug,
            'classification_group' => 'ACTIVITY',
            'sort_order' => 1,
            'is_active' => '1',
            'show_in_home_nav' => '1',
        ])->assertRedirect(route('admin.classifications.index'));

        $this->assertDatabaseHas('classifications', [
            'slug' => $actSlug,
            'show_in_home_nav' => 1,
        ]);

        // 3. Mục đang hoạt động thứ 7 không thể bật toggle-home-nav
        $unselectedActive = Classification::forceCreate([
            'name' => 'Unselected Active Sil',
            'slug' => 'unselected-active-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 8,
            'is_active' => true,
            'show_in_home_nav' => false,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.classifications.toggle-home-nav', $unselectedActive))
            ->assertSessionHasErrors('classification');
        $this->assertFalse($unselectedActive->fresh()->show_in_home_nav);

        // 4. Mục đã chọn nhưng đang tạm ngưng: khi bật lại (toggleActive hoặc update) phải bị chặn nếu nhóm đã đủ 6
        $inactiveSelected = Classification::forceCreate([
            'name' => 'Inactive Selected Sil',
            'slug' => 'inactive-selected-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 9,
            'is_active' => false,
            'show_in_home_nav' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.classifications.toggle-active', $inactiveSelected))
            ->assertSessionHasErrors('classification');
        $this->assertFalse($inactiveSelected->fresh()->is_active);

        $this->actingAs($this->admin)
            ->put(route('admin.classifications.update', $inactiveSelected), [
                'name' => $inactiveSelected->name,
                'slug' => $inactiveSelected->slug,
                'classification_group' => 'SILHOUETTE',
                'sort_order' => 9,
                'is_active' => '1',
                'show_in_home_nav' => '1',
            ])->assertSessionHasErrors('show_in_home_nav');
        $this->assertFalse($inactiveSelected->fresh()->is_active);

        // 5. Khi bỏ chọn 1 mục active, mục tạm ngưng có thể kích hoạt lại thành công
        $firstSelected = Classification::query()
            ->where('classification_group', ClassificationGroup::Silhouette->value)
            ->where('show_in_home_nav', true)
            ->where('is_active', true)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.classifications.toggle-home-nav', $firstSelected))
            ->assertSessionHasNoErrors();
        $this->assertFalse($firstSelected->fresh()->show_in_home_nav);

        $this->actingAs($this->admin)
            ->patch(route('admin.classifications.toggle-active', $inactiveSelected))
            ->assertSessionHasNoErrors();
        $this->assertTrue($inactiveSelected->fresh()->is_active);
    }
}
