<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationAccountSeparationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_has_management_orders_but_no_personal_purchase_links_or_cart_loader(): void
    {
        $admin = User::factory()->administrator()->create();
        foreach (['admin.dashboard', 'account.index', 'home'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk()
                ->assertDontSee('href="'.route('orders.index').'"', false)
                ->assertDontSee('href="'.route('account.addresses.index').'"', false)
                ->assertDontSee('href="'.route('checkout.show').'"', false)
                ->assertDontSee('aria-label="Cart"', false)
                ->assertDontSee('$store.cart.load()', false)
                ->assertSee('href="'.route('admin.dashboard').'"', false);
        }
        $this->get(route('admin.dashboard'))->assertSee('href="'.route('admin.orders.index').'"', false);
        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('account.settings'))->assertOk();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_admin_account_overview_does_not_query_addresses_or_orders(): void
    {
        $admin = User::factory()->administrator()->create();
        DB::enableQueryLog();
        try {
            $this->actingAs($admin)->get(route('account.index'))->assertOk()
                ->assertSee('Quản trị cửa hàng')->assertSee('Quản lý đơn hàng của khách')
                ->assertDontSee('Các đơn hàng gần đây')->assertDontSee('Địa chỉ đã lưu')
                ->assertViewHas('recentOrders', fn ($orders) => $orders->isEmpty());
            $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertDoesNotMatchRegularExpression('/from [`"]?(?:orders|addresses)[`"]?\b/i', $queries);
    }

    public function test_existing_order_records_are_retained_and_still_visible_in_management(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['user_id' => $admin->id, 'order_code' => 'ORD-20261102-'.str_repeat('0', 26)]);
        $before = $order->fresh()->getAttributes();

        $this->actingAs($admin)->get(route('account.index'))->assertOk()->assertDontSee($order->order_code);
        $this->getJson(route('orders.show', $order->order_code))->assertForbidden();
        $this->postJson(route('orders.cancel', $order->order_code), ['reason' => 'Không được hủy qua luồng mua sắm'])->assertForbidden();
        $this->get(route('admin.orders.index'))->assertOk()->assertSee($order->order_code);

        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('orders', 1);
    }

    #[DataProvider('purchaseEndpoints')]
    public function test_admin_is_blocked_before_any_personal_purchase_endpoint_runs(string $method, string $url): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->json($method, $url, [])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
    }

    public static function purchaseEndpoints(): array
    {
        $code = 'ORD-20261102-'.str_repeat('0', 26);

        return [
            ['GET', '/checkout'], ['POST', '/checkout'], ['GET', '/account/orders'], ['GET', '/account/orders/'.$code], ['POST', '/account/orders/'.$code.'/cancel'],
            ['GET', '/cart'], ['POST', '/cart/items'], ['PATCH', '/cart/items/1'], ['DELETE', '/cart/items/1'], ['POST', '/cart/clear'], ['POST', '/account/reviews'],
        ];
    }

    public function test_admin_takes_precedence_even_with_a_customer_role_and_current_roles_are_rechecked(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('orders.index'))->assertOk();
        $role = Role::where('slug', RoleSlug::Admin)->first() ?? Role::factory()->create(['slug' => RoleSlug::Admin]);
        UserRole::factory()->create(['user_id' => $customer->id, 'role_id' => $role->id]);
        $this->getJson(route('orders.index'))->assertForbidden();
        $this->get(route('home'))->assertDontSee('aria-label="Cart"', false)->assertDontSee('href="'.route('orders.index').'"', false);
        UserRole::where('user_id', $customer->id)->where('role_id', $role->id)->delete();
        $this->get(route('orders.index'))->assertOk();
        $this->get(route('home'))->assertSee('aria-label="Cart"', false);
    }

    #[DataProvider('customerRoles')]
    public function test_customer_and_staff_personal_links_and_orders_remain_available(string $state): void
    {
        $user = User::factory()->{$state}()->create();
        $this->actingAs($user)->get(route('account.index'))->assertOk()
            ->assertSee('href="'.route('orders.index').'"', false)
            ->assertSee('href="'.route('account.addresses.index').'"', false)
            ->assertSee('aria-label="Cart"', false)->assertSee('$store.cart.load()', false)
            ->assertSee('Các đơn hàng gần đây');
        $this->get(route('orders.index'))->assertOk();
        $this->getJson(route('cart.show'))->assertOk();
        $this->getJson(route('admin.dashboard'))->assertStatus($state === 'staff' ? 200 : 403);
    }

    public static function customerRoles(): array
    {
        return [['customer'], ['staff']];
    }

    public function test_guest_storefront_and_authentication_contract_are_preserved(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('aria-label="Cart"', false)->assertSee('href="'.route('login').'"', false);
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->getJson(route('checkout.show'))->assertUnauthorized();
        $this->getJson(route('cart.show'))->assertOk();
    }

    public function test_navigation_uses_existing_local_images_and_clears_failed_previews_without_empty_img_sources(): void
    {
        $response = $this->get(route('home'))->assertOk()->assertSee('data-navigation-preview', false)
            ->assertSee('x-show="hoverImage && previewReady"', false)
            ->assertSee('x-on:load=', false)->assertSee('x-on:error=', false)
            ->assertSee('@pagehide.window=', false)->assertSee('@blur.window=', false)
            ->assertDontSee('<img :src="hoverImage"', false)
            // A string :style replaces the whole attribute and wipes x-show's display:none, so the box followed the cursor.
            ->assertSee(':style="{ transform:', false)
            ->assertDontSee(':style="`transform', false);
        foreach (['men', 'women', 'kid', 'unisex'] as $image) {
            $this->assertFileExists(public_path('legacy/img/'.$image.'.jpg'));
            $response->assertSee(asset('legacy/img/'.$image.'.jpg'), false);
        }
    }

    public function test_admin_sees_no_buy_buttons_and_login_never_merges_a_cart_or_returns_to_shopping(): void
    {
        $admin = User::factory()->administrator()->create(['email' => 'admin-nav@nike.test', 'password' => 'Mat-khau-admin-8']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id]);
        ProductVariant::factory()->create(['product_color_id' => $color->id]);

        $this->actingAs($admin)->get(route('shoes.show', $product->slug))->assertOk()
            ->assertDontSee('data-add-to-cart', false)->assertSee('Tài khoản quản trị không dùng để mua sắm');
        $this->actingAs(User::factory()->customer()->create())->get(route('shoes.show', $product->slug))->assertSee('data-add-to-cart', false);

        $this->post(route('logout'));
        RateLimiter::clear('login:admin-nav@nike.test|127.0.0.1');
        $this->getJson(route('cart.show'))->assertOk();
        $this->get(route('checkout.show'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => 'admin-nav@nike.test', 'password' => 'Mat-khau-admin-8'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame(0, Cart::query()->where('user_id', $admin->id)->count());
    }
}
