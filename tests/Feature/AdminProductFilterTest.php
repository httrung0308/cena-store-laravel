<?php

namespace Tests\Feature;

use App\Enums\GenderGroup;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminProductFilterTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
        $this->admin = User::factory()->administrator()->create();
    }

    public function test_filters_combine_and_between_groups_and_or_within_multi_select_groups(): void
    {
        $catUnisex = Category::factory()->create([
            'name' => 'Unisex',
            'gender_group' => GenderGroup::Unisex,
            'is_active' => true,
        ]);
        $catMen = Category::factory()->create([
            'name' => 'Giày Nam Filter',
            'gender_group' => GenderGroup::Men,
            'is_active' => false, // Inactive category must still be available & filterable
        ]);

        $silLow = Classification::factory()->silhouette()->create([
            'name' => 'Cổ thấp Filter',
            'is_active' => true,
            'show_in_home_nav' => false,
        ]);
        $silHigh = Classification::factory()->silhouette()->create([
            'name' => 'Cổ cao Filter',
            'is_active' => false, // Inactive classification must still be available & filterable
            'show_in_home_nav' => false,
        ]);
        $actRun = Classification::factory()->activity()->create([
            'name' => 'Chạy bộ Filter',
            'is_active' => true,
            'show_in_home_nav' => false,
        ]);

        $match1 = $this->createProductWithStock($catUnisex, 'Alpha Runner Match', 'alpha-runner-match', true, true, 5, 0, 0);
        $match1->classifications()->attach([$silLow->id, $actRun->id]);

        $match2 = $this->createProductWithStock($catMen, 'Beta High Match', 'beta-high-match', true, true, 3, 0, 0);
        $match2->classifications()->attach([$silHigh->id, $actRun->id]);

        $wrongActivity = $this->createProductWithStock($catUnisex, 'Gamma No Activity', 'gamma-no-activity', true, true, 5, 0, 0);
        $wrongActivity->classifications()->attach([$silLow->id]);

        $notFeatured = $this->createProductWithStock($catUnisex, 'Delta Not Featured', 'delta-not-featured', true, false, 5, 0, 0);
        $notFeatured->classifications()->attach([$silLow->id, $actRun->id]);

        // OR within genders & silhouettes, AND across genders, silhouettes, activities, status, stock, featured
        $response = $this->actingAs($this->staff)->get(route('admin.products.index', [
            'genders' => [GenderGroup::Unisex->value, GenderGroup::Men->value],
            'silhouettes' => [$silLow->id, $silHigh->id],
            'activities' => [$actRun->id],
            'status' => 'active',
            'stock' => 'in_stock',
            'featured' => 'featured',
        ]));

        $response->assertOk()
            ->assertSee('Alpha Runner Match')
            ->assertSee('Beta High Match')
            ->assertDontSee('Gamma No Activity')
            ->assertDontSee('Delta Not Featured')
            ->assertSee('Cổ cao Filter')
            ->assertSee('Đối tượng')
            ->assertSee(GenderGroup::Men->label())
            ->assertDontSee('Giày Nam Filter')
            ->assertDontSee('name="categories[]"', false);
    }

    public function test_stock_filter_uses_saleable_quantity_and_valid_variants_independently_of_product_status(): void
    {
        $category = Category::factory()->create(['gender_group' => GenderGroup::Unisex, 'is_active' => true]);
        $actFilter = Classification::factory()->activity()->create(['name' => 'Stock Filter Activity', 'is_active' => true]);

        // 1. Active product, all stock reserved (stock_qty=5, reserved_qty=5) => out_of_stock
        $allReserved = $this->createProductWithStock($category, 'Reserved Only Shoe', 'reserved-only-shoe', true, false, 5, 5, 0);
        $allReserved->classifications()->attach([$actFilter->id]);

        // 2. Active product, all stock quarantined (stock_qty=0, quarantine_qty=8) => out_of_stock
        $quarantined = $this->createProductWithStock($category, 'Quarantined Only Shoe', 'quarantined-only-shoe', true, false, 0, 0, 8);
        $quarantined->classifications()->attach([$actFilter->id]);

        // 3. Active product, stock on inactive variant => out_of_stock
        $inactiveVariant = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Inactive Variant Stock Shoe',
            'slug' => 'inactive-variant-stock-shoe',
            'is_active' => true,
        ]);
        $inactiveVariant->classifications()->attach([$actFilter->id]);
        $activeColor = ProductColor::factory()->create(['product_id' => $inactiveVariant->id, 'is_active' => true]);
        ProductVariant::factory()->create([
            'product_color_id' => $activeColor->id,
            'is_active' => false,
            'stock_qty' => 10,
            'reserved_qty' => 0,
        ]);

        // 4. Active product, stock on inactive color => out_of_stock
        $inactiveColorProd = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Inactive Color Stock Shoe',
            'slug' => 'inactive-color-stock-shoe',
            'is_active' => true,
        ]);
        $inactiveColorProd->classifications()->attach([$actFilter->id]);
        $inactiveColor = ProductColor::factory()->create(['product_id' => $inactiveColorProd->id, 'is_active' => false]);
        ProductVariant::factory()->create([
            'product_color_id' => $inactiveColor->id,
            'is_active' => true,
            'stock_qty' => 10,
            'reserved_qty' => 0,
        ]);

        // 5. Inactive product, valid variant with sellable stock => in_stock!
        $inactiveSellable = $this->createProductWithStock($category, 'Hidden But Sellable Shoe', 'hidden-but-sellable-shoe', false, false, 4, 1, 0);
        $inactiveSellable->classifications()->attach([$actFilter->id]);

        // Filter in_stock
        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['activities' => [$actFilter->id], 'stock' => 'in_stock']))
            ->assertOk()
            ->assertSee($inactiveSellable->name)
            ->assertDontSee($allReserved->name)
            ->assertDontSee($quarantined->name)
            ->assertDontSee($inactiveVariant->name)
            ->assertDontSee($inactiveColorProd->name);

        // Filter out_of_stock
        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['activities' => [$actFilter->id], 'stock' => 'out_of_stock']))
            ->assertOk()
            ->assertSee($allReserved->name)
            ->assertSee($quarantined->name)
            ->assertSee($inactiveVariant->name)
            ->assertSee($inactiveColorProd->name)
            ->assertDontSee($inactiveSellable->name);
    }

    public function test_empty_result_validation_permissions_and_pagination_redirect_after_toggle(): void
    {
        // Customer forbidden
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('admin.products.index'))->assertForbidden();

        // Invalid filter params rejected
        $this->actingAs($this->staff)
            ->getJson(route('admin.products.index', ['status' => 'bogus', 'stock' => 'bad']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'stock']);

        // Empty state
        $this->actingAs($this->staff)
            ->get(route('admin.products.index', ['search' => 'KhongTonTaiSanPhamNao99999']))
            ->assertOk()
            ->assertSee('Không có sản phẩm nào phù hợp với bộ lọc hiện tại.')
            ->assertSee('Xóa bộ lọc');

        // Pagination redirect when page 2 becomes empty after deactivation
        $category = Category::factory()->create(['gender_group' => GenderGroup::Unisex, 'is_active' => true]);
        $actPage = Classification::factory()->activity()->create(['name' => 'Pagination Activity', 'is_active' => true]);
        $created = [];
        for ($i = 1; $i <= 16; $i++) {
            $p = $this->createProductWithStock($category, 'Page Test Shoe '.$i, 'page-test-shoe-'.$i, true, false, 1, 0, 0);
            $p->classifications()->attach([$actPage->id]);
            $created[] = $p;
        }

        // Order is desc by id, so $created[0] is on page 2
        $pageTwoUrl = route('admin.products.index', ['activities' => [$actPage->id], 'status' => 'active', 'page' => 2]);
        $this->actingAs($this->staff)
            ->get($pageTwoUrl)
            ->assertOk()
            ->assertSee($created[0]->name);

        $this->actingAs($this->staff)
            ->patchJson(route('admin.products.deactivate', $created[0]))
            ->assertOk()
            ->assertJson(['is_active' => false]);

        $this->actingAs($this->staff)
            ->get($pageTwoUrl)
            ->assertRedirect(route('admin.products.index', ['activities' => [$actPage->id], 'status' => 'active', 'page' => 1]));
    }

    public function test_admin_and_staff_can_view_own_profile_in_admin_layout_and_navigation_uses_xem_cua_hang(): void
    {
        $customer = User::factory()->customer()->create();

        // Guest redirected, customer forbidden
        $this->get(route('admin.profile.show'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('admin.profile.show'))->assertForbidden();

        // Staff views own profile & top bar / sidebar links
        $staffResponse = $this->actingAs($this->staff)
            ->get(route('admin.profile.show', ['id' => $this->admin->id, 'user_id' => $this->admin->id]));

        $staffResponse->assertOk()
            ->assertSee('Tài khoản của tôi')
            ->assertSee('Xem cửa hàng')
            ->assertDontSee('Xem Storefront')
            ->assertDontSee('Xem cửa hàng khách')
            ->assertSee($this->staff->full_name)
            ->assertSee($this->staff->email)
            ->assertSee('Nhân viên')
            ->assertDontSee($this->admin->email);

        // Admin views own profile
        $adminResponse = $this->actingAs($this->admin)
            ->get(route('admin.profile.show'));

        $adminResponse->assertOk()
            ->assertSee('Tài khoản của tôi')
            ->assertSee($this->admin->full_name)
            ->assertSee($this->admin->email)
            ->assertSee('Quản trị viên');

        // Bottom-up name shortening on topbar preserves complete words from the end
        $longNameStaff = User::factory()->staff()->create([
            'full_name' => 'Nguyễn Hoàng Minh Phương',
        ]);

        $this->assertSame('Hoàng Minh Phương', $longNameStaff->compactDisplayName(18));
        $this->assertSame('Minh Phương', $longNameStaff->compactDisplayName(12));
        $this->assertSame('MP', $longNameStaff->avatarInitials());

        $mediumNameUser = new User(['full_name' => 'Nguyễn Hoàng Thành']);
        $this->assertSame('Nguyễn Hoàng Thành', $mediumNameUser->compactDisplayName(18));
        $this->assertSame('Hoàng Thành', $mediumNameUser->compactDisplayName(12));
        $this->assertSame('HT', $mediumNameUser->avatarInitials());

        $topbarResponse = $this->actingAs($longNameStaff)
            ->get(route('admin.products.index'));

        $topbarResponse->assertOk()
            ->assertSee('Hoàng Minh Phương')
            ->assertSee('Minh Phương')
            ->assertSee('Nguyễn Hoàng Minh Phương')
            ->assertSee('absolute left-0 right-0 z-30 mt-1.5 w-full max-w-full', false)
            ->assertSee("isSelected('genders', \$el.dataset.filterValue) ? 'bg-accent/10 text-accent font-bold border-accent/30'", false)
            ->assertDontSee('x-text="activeAdvancedCount()"', false);
    }

    private function createProductWithStock(
        Category $category,
        string $name,
        string $slug,
        bool $isActive,
        bool $isFeatured,
        int $stockQty,
        int $reservedQty,
        int $quarantineQty
    ): Product {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => $isActive,
            'is_featured' => $isFeatured,
        ]);
        $color = ProductColor::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_color_id' => $color->id,
            'is_active' => true,
            'stock_qty' => $stockQty,
            'reserved_qty' => $reservedQty,
            'quarantine_qty' => $quarantineQty,
        ]);

        return $product;
    }
}
