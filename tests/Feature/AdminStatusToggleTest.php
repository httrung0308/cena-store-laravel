<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminStatusToggleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_and_admin_can_toggle_product_category_and_supplier_both_ways_without_cascading(): void
    {
        $staff = User::factory()->staff()->create();

        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['category_id' => $inactiveCategory->id, 'is_active' => true]);
        $color = ProductColor::factory()->create(['product_id' => $product->id, 'is_active' => false]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'is_active' => false]);

        $this->actingAs($staff)
            ->patchJson(route('admin.products.deactivate', $product))
            ->assertOk()
            ->assertJson(['is_active' => false]);
        $this->assertFalse($product->fresh()->is_active);

        $this->patchJson(route('admin.products.activate', $product))
            ->assertOk()
            ->assertJson(['is_active' => true]);
        $this->assertTrue($product->fresh()->is_active);
        $this->assertFalse($inactiveCategory->fresh()->is_active, 'Category stays inactive.');
        $this->assertFalse($color->fresh()->is_active, 'Color stays inactive.');
        $this->assertFalse($variant->fresh()->is_active, 'Variant stays inactive.');

        $this->patchJson(route('admin.categories.activate', $inactiveCategory))
            ->assertOk()
            ->assertJson(['is_active' => true]);
        $this->patchJson(route('admin.categories.deactivate', $inactiveCategory))
            ->assertOk()
            ->assertJson(['is_active' => false]);

        $supplier = Supplier::factory()->create(['is_active' => true]);
        $this->patchJson(route('admin.suppliers.deactivate', $supplier))
            ->assertOk()
            ->assertJson(['is_active' => false]);
        $this->patchJson(route('admin.suppliers.activate', $supplier))
            ->assertOk()
            ->assertJson(['is_active' => true]);
    }

    public function test_admin_only_toggles_enforce_role_and_explicit_target_states(): void
    {
        $admin = User::factory()->administrator()->create();
        $staff = User::factory()->staff()->create();
        $coupon = Coupon::factory()->create(['is_active' => true]);
        $banner = Banner::factory()->create(['is_active' => true]);

        $this->actingAs($staff)->patchJson(route('admin.coupons.deactivate', $coupon))->assertForbidden();
        $this->actingAs($staff)->patchJson(route('admin.coupons.activate', $coupon))->assertForbidden();
        $this->actingAs($staff)->patchJson(route('admin.banners.toggle-active', $banner), ['active' => 0])->assertForbidden();

        $this->actingAs($admin)->patchJson(route('admin.coupons.deactivate', $coupon))
            ->assertOk()
            ->assertJson(['is_active' => false]);
        $this->patchJson(route('admin.coupons.activate', $coupon))
            ->assertOk()
            ->assertJson(['is_active' => true]);

        // Idempotent explicit target state on banner
        $this->patchJson(route('admin.banners.toggle-active', $banner), ['active' => 0])
            ->assertOk()
            ->assertJson(['is_active' => false]);
        $this->patchJson(route('admin.banners.toggle-active', $banner), ['active' => 0])
            ->assertOk()
            ->assertJson(['is_active' => false]);
        $this->patchJson(route('admin.banners.toggle-active', $banner), ['active' => 1])
            ->assertOk()
            ->assertJson(['is_active' => true]);
    }

    public function test_classification_home_nav_limit_is_enforced_and_returns_422_json(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        // Clear any existing active home-nav silhouettes in transaction so test is deterministic
        Classification::query()
            ->where('classification_group', ClassificationGroup::Silhouette)
            ->update(['show_in_home_nav' => false]);

        $items = Classification::factory()->count(6)->create([
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'show_in_home_nav' => true,
        ]);
        $seventh = Classification::factory()->create([
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'show_in_home_nav' => false,
        ]);

        $this->patchJson(route('admin.classifications.toggle-home-nav', $seventh), ['show_in_home_nav' => 1])
            ->assertUnprocessable();
        $this->assertFalse($seventh->fresh()->show_in_home_nav);

        // Turning one off allows turning the 7th on
        $this->patchJson(route('admin.classifications.toggle-home-nav', $items->first()), ['show_in_home_nav' => 0])
            ->assertOk()
            ->assertJson(['show_in_home_nav' => false]);
        $this->patchJson(route('admin.classifications.toggle-home-nav', $seventh), ['show_in_home_nav' => 1])
            ->assertOk()
            ->assertJson(['show_in_home_nav' => true]);
    }

    public function test_review_visibility_supports_json_toggle_and_customer_is_forbidden(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $review = Review::factory()->create(['is_visible' => true]);

        $this->actingAs($customer)->patchJson(route('admin.reviews.visibility', $review), ['visible' => 0])->assertForbidden();

        $this->actingAs($staff)->patchJson(route('admin.reviews.visibility', $review), ['visible' => 0])
            ->assertOk()
            ->assertJson(['is_visible' => false]);
        $this->assertFalse($review->fresh()->is_visible);

        $this->patchJson(route('admin.reviews.visibility', $review), ['visible' => 1])
            ->assertOk()
            ->assertJson(['is_visible' => true]);
        $this->assertTrue($review->fresh()->is_visible);
    }
}
