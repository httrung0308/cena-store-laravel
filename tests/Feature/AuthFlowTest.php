<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Cart;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Mat-khau-8ky!';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:khach@cena.test|127.0.0.1');
        Role::query()->where('slug', RoleSlug::Customer)->exists() || Role::factory()->customer()->create();
    }

    /** @return array<string, string> */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'full_name' => '  Khách   Hàng ',
            'email' => ' Khach@Cena.TEST ',
            'phone' => '0901 234 567',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    public function test_register_creates_active_customer_with_empty_cart_and_ignores_role_injection(): void
    {
        $response = $this->post(route('register.store'), $this->registration([
            'role' => 'admin', 'roles' => ['admin'], 'is_active' => '0', 'auth_version' => '99',
        ]));

        $response->assertRedirect(route('home'));
        $user = User::query()->where('email', 'khach@cena.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Khách Hàng', $user->full_name);
        $this->assertSame('0901234567', $user->phone);
        $this->assertTrue($user->is_active);
        $this->assertSame(1, $user->auth_version);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertSame([RoleSlug::Customer], $user->roles()->get()->pluck('slug')->all());
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, session(AuthService::SESSION_AUTH_VERSION));
    }

    public function test_register_rejects_invalid_input_without_creating_a_user(): void
    {
        User::factory()->create(['email' => 'khach@cena.test']);

        $cases = [
            'email' => $this->registration(['email' => 'KHACH@cena.test']),
            'password' => $this->registration(['password_confirmation' => 'khac-mat-khau']),
            'full_name' => $this->registration(['email' => 'moi@cena.test', 'full_name' => 'A']),
            'phone' => $this->registration(['email' => 'moi2@cena.test', 'phone' => '12345']),
        ];
        foreach ($cases as $field => $payload) {
            $this->from(route('register'))->post(route('register.store'), $payload)
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors($field);
        }

        $tooLong = str_repeat('a', 73);
        $this->post(route('register.store'), $this->registration(['email' => 'dai@cena.test', 'password' => $tooLong, 'password_confirmation' => $tooLong]))
            ->assertSessionHasErrors(['password' => 'Mật khẩu tối đa 72 byte (khoảng 72 ký tự không dấu).']);

        $this->assertGuest();
        $this->assertSame(1, User::query()->whereIn('email', ['khach@cena.test', 'moi@cena.test', 'moi2@cena.test', 'dai@cena.test'])->count());
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_service_turns_a_duplicate_email_race_into_a_validation_error(): void
    {
        User::factory()->create(['email' => 'khach@cena.test']);

        $this->expectException(ValidationException::class);
        app(AuthService::class)->register(['full_name' => 'Khách', 'email' => 'khach@cena.test', 'phone' => null, 'password' => self::PASSWORD]);
    }

    public function test_register_fails_loudly_and_rolls_back_when_customer_role_is_missing(): void
    {
        Role::query()->where('slug', RoleSlug::Customer)->delete();

        try {
            app(AuthService::class)->register(['full_name' => 'Khách', 'email' => 'moi@cena.test', 'phone' => null, 'password' => self::PASSWORD]);
            $this->fail('Expected a configuration error.');
        } catch (LogicException) {
            $this->assertFalse(User::query()->where('email', 'moi@cena.test')->exists());
        }
    }

    public function test_login_regenerates_session_stores_auth_version_and_returns_to_intended_page(): void
    {
        $user = User::factory()->customer()->create(['email' => 'khach@cena.test', 'password' => self::PASSWORD, 'auth_version' => 3]);
        $this->get(route('shoes.index'));
        session()->put('url.intended', route('shoes.index'));
        $before = session()->getId();

        $this->post(route('login.store'), ['email' => ' KHACH@cena.test ', 'password' => self::PASSWORD])
            ->assertRedirect(route('shoes.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame(3, session(AuthService::SESSION_AUTH_VERSION));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_admin_and_staff_land_on_back_office_after_login(): void
    {
        User::factory()->staff()->create(['email' => 'nhanvien@cena.test', 'password' => self::PASSWORD]);

        $this->post(route('login.store'), ['email' => 'nhanvien@cena.test', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_wrong_password_locked_and_deleted_accounts_cannot_sign_in(): void
    {
        User::factory()->customer()->create(['email' => 'khach@cena.test', 'password' => self::PASSWORD]);
        User::factory()->customer()->inactive()->create(['email' => 'khoa@cena.test', 'password' => self::PASSWORD]);
        User::factory()->customer()->create(['email' => 'xoa@cena.test', 'password' => self::PASSWORD])->delete();

        foreach ([['khach@cena.test', 'sai-mat-khau'], ['khoa@cena.test', self::PASSWORD], ['xoa@cena.test', self::PASSWORD]] as [$email, $password]) {
            $this->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => $password])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.'])
                ->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    public function test_sixth_attempt_is_throttled_even_with_the_right_password(): void
    {
        User::factory()->customer()->create(['email' => 'khach@cena.test', 'password' => self::PASSWORD]);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => 'khach@cena.test', 'password' => 'sai-'.$i]);
        }

        $this->post(route('login.store'), ['email' => 'khach@cena.test', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('quá nhiều lần', session('errors')->first('email'));
    }

    public function test_locking_the_account_or_bumping_auth_version_ends_the_old_session_on_next_request(): void
    {
        $user = User::factory()->administrator()->create(['email' => 'admin@cena.test', 'password' => self::PASSWORD]);
        $this->post(route('login.store'), ['email' => 'admin@cena.test', 'password' => self::PASSWORD]);
        $this->get('/admin')->assertOk();

        $user->forceFill(['auth_version' => 2])->save();
        $this->app['auth']->forgetGuards(); // a real request reloads the user row from the session id
        $this->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'admin@cena.test', 'password' => self::PASSWORD]);
        $this->get('/admin')->assertOk();
        $user->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson('/admin')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_logout_requires_post_and_invalidates_the_session(): void
    {
        $user = User::factory()->customer()->create();
        $this->actingAs($user)->get('/logout')->assertStatus(405);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertNull(session(AuthService::SESSION_AUTH_VERSION));
    }

    public function test_guest_is_sent_to_login_and_signed_in_user_skips_guest_pages(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('login'))->assertRedirect(route('home'));
    }

    public function test_login_and_register_pages_post_to_real_actions(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertDontSee('Chức năng đăng nhập chưa khả dụng');
        $this->get(route('register'))->assertOk()
            ->assertSee('action="'.route('register.store').'"', false)
            ->assertSee('name="phone"', false)
            ->assertDontSee('name="role"', false);
    }

    public function test_navbar_shows_login_link_for_guests_and_escaped_account_menu_for_users(): void
    {
        $this->get(route('home'))->assertSee('href="'.route('login').'"', false)->assertDontSee('id="account-panel"', false);

        $customer = User::factory()->customer()->create(['full_name' => '<b>Khách</b>']);
        $this->actingAs($customer)->get(route('home'))
            ->assertSee('id="account-panel"', false)
            ->assertSee('&lt;b&gt;Khách&lt;/b&gt;', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertDontSee('href="'.route('admin.dashboard').'"', false)
            ->assertDontSee($customer->password, false);

        $this->actingAs(User::factory()->administrator()->create())->get(route('home'))
            ->assertSee('href="'.route('admin.dashboard').'"', false);
    }
}
