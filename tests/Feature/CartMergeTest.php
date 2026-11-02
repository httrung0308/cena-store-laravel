<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Mat-khau-8ky!';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:khach@cena.test|127.0.0.1');
        Role::query()->where('slug', RoleSlug::Customer)->exists() || Role::factory()->customer()->create();
    }

    private function variant(int $stock, string $size = '40'): ProductVariant
    {
        return ProductVariant::factory()->stocked($stock)->create(['size_value' => $size, 'size_numeric' => $size.(str_contains($size, '.') ? '' : '.0')]);
    }

    private function addAsGuest(ProductVariant $variant, int $quantity): void
    {
        $version = $this->getJson(route('cart.show'))->json('data.version');
        $this->postJson(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => $quantity, 'version' => $version])->assertOk();
    }

    public function test_login_merges_guest_lines_caps_quantities_and_reports_changes(): void
    {
        $user = User::factory()->customer()->create(['email' => 'khach@cena.test', 'password' => self::PASSWORD]);
        $shared = $this->variant(4);
        $guestOnly = $this->variant(9, '40.5');
        $gone = $this->variant(5, '41');
        $userCart = (new Cart)->forceFill(['user_id' => $user->id, 'version' => 1]);
        $userCart->save();
        (new CartItem)->forceFill(['cart_id' => $userCart->id, 'variant_id' => $shared->id, 'quantity' => 2])->save();

        $this->addAsGuest($shared, 3);
        $this->addAsGuest($guestOnly, 2);
        $this->addAsGuest($gone, 1);
        $gone->forceFill(['is_active' => false])->save();
        $guestHash = hash('sha256', session(CartService::SESSION_GUEST_TOKEN));

        $this->post(route('login.store'), ['email' => 'khach@cena.test', 'password' => self::PASSWORD])->assertRedirect();

        $items = CartItem::query()->where('cart_id', $userCart->id)->pluck('quantity', 'variant_id');
        $this->assertSame(4, $items[$shared->id], 'Capped at available stock.');
        $this->assertSame(2, $items[$guestOnly->id]);
        $this->assertFalse($items->has($gone->id));
        $this->assertFalse(Cart::query()->where('guest_token_hash', $guestHash)->exists());
        $this->assertNull(session(CartService::SESSION_GUEST_TOKEN));

        $notices = $this->getJson(route('cart.show'))->json('data.notices');
        $this->assertCount(2, $notices);
        $this->assertStringContainsString('chỉ giữ được 4 đôi', $notices[0].$notices[1]);
    }

    public function test_repeating_the_merge_does_nothing(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->variant(10);
        $this->addAsGuest($variant, 2);
        $session = $this->app['session.store'];

        $service = app(CartService::class);
        $service->mergeGuestCart($user, $session);
        $session->put(CartService::SESSION_GUEST_TOKEN, 'replayed-token');
        $this->assertSame([], $service->mergeGuestCart($user, $session));

        $this->assertSame(2, CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id', $user->id))->value('quantity'));
    }

    public function test_registration_moves_the_guest_cart_into_the_new_account(): void
    {
        $variant = $this->variant(5);
        $this->addAsGuest($variant, 2);

        $this->post(route('register.store'), [
            'full_name' => 'Khách Mới', 'email' => 'moi@cena.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertRedirect();

        $user = User::query()->where('email', 'moi@cena.test')->sole();
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Cart::query()->where('user_id', $user->id)->sole()->items()->value('quantity'));
    }

    public function test_logout_starts_an_empty_guest_cart(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->variant(5);
        $this->actingAs($user)->postJson(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1, 'version' => 0])->assertOk();

        $this->post(route('logout'));

        $this->getJson(route('cart.show'))->assertOk()->assertJsonPath('data.count', 0);
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->sole()->items()->count());
    }
}
