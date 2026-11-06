<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StorefrontRedesignTest extends TestCase
{
    use DatabaseTransactions;

    public function test_storefront_uses_nike_branding_and_full_footer_with_disclaimer(): void
    {
        $this->get(route('shoes.index'))
            ->assertOk()
            ->assertSee('<title>Giày — Shoes — Cena</title>', false)
            ->assertSee('Giày nam')
            ->assertSee('Chính sách')
            ->assertSee('Thanh toán khi nhận hàng (COD)')
            ->assertSee('không phải website chính thức của Nike')
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_auth_pages_are_full_screen_without_navbar_and_link_to_each_other(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Chào mừng trở lại.')
            ->assertSee('cena-auth-bg.svg', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertDontSee('aria-label="Main navigation"', false)
            ->assertDontSee('id="cart-panel"', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Gia nhập cùng Cena.')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee('aria-label="Main navigation"', false);
    }

    public function test_failed_login_keeps_email_and_shows_the_error_on_the_redesigned_form(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['email' => 'khong-co@nike.test', 'password' => 'sai-mat-khau'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))->assertSee('value="khong-co@nike.test"', false)->assertSee('role="alert"', false);
    }

    public function test_admin_dashboard_lists_modules_and_signed_in_staff_details(): void
    {
        $staff = User::factory()->staff()->create(['full_name' => 'Nhân viên <i>Kho</i>']);

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Các mô-đun quản lý')
            ->assertSee('Nhân viên &lt;i&gt;Kho&lt;/i&gt;', false)
            ->assertSee('Nhân viên')
            ->assertSee('href="'.route('admin.categories.index').'"', false)
            ->assertSee('href="'.route('admin.stock-imports.index').'"', false)
            ->assertSee('aria-label="Main navigation"', false);
    }

    public function test_account_overview_requires_login_and_shows_the_default_address(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));

        $user = User::factory()->customer()->create(['full_name' => 'Khách <b>A</b>']);
        $this->actingAs($user)->get(route('account.index'))
            ->assertOk()
            ->assertSee('Xin chào Khách &lt;b&gt;A&lt;/b&gt;!', false)
            ->assertSee('Chưa có địa chỉ nào');

        Address::factory()->create(['user_id' => $user->id, 'recipient_name' => 'Người nhận Mặc định', 'is_default' => true]);
        $this->actingAs($user)->get(route('account.index'))
            ->assertSee('Người nhận Mặc định')
            ->assertSee('(Mặc định)')
            ->assertSee('href="'.route('account.addresses.index').'"', false);
    }

    public function test_account_overview_never_shows_another_users_address(): void
    {
        $owner = User::factory()->customer()->create();
        Address::factory()->create(['user_id' => $owner->id, 'recipient_name' => 'Địa chỉ của người khác', 'is_default' => true]);

        $this->actingAs(User::factory()->customer()->create())->get(route('account.index'))
            ->assertOk()
            ->assertDontSee('Địa chỉ của người khác');
    }

    public function test_account_menu_drops_down_under_the_icon_with_profile_header_and_items(): void
    {
        $customer = User::factory()->customer()->create(['full_name' => 'minh <b>Nguyễn</b>']);
        $this->actingAs($customer)->get(route('home'))->assertOk()
            ->assertSee('id="account-panel"', false)
            ->assertSee('origin-top-right', false)
            ->assertSee('data-autofocus', false)
            ->assertSee('minh &lt;b&gt;Nguyễn&lt;/b&gt;', false)
            ->assertSee('>M</span>', false)
            ->assertSee('href="'.route('orders.index').'"', false)
            ->assertSee('href="'.route('account.settings').'"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertDontSee('href="'.route('admin.dashboard').'"', false);

        $this->actingAs(User::factory()->administrator()->create())->get(route('home'))
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee('href="'.route('orders.index').'"', false);
    }
}
