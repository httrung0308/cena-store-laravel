<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CartTest extends TestCase
{
    use DatabaseTransactions;

    private ProductColor $black;

    protected function setUp(): void
    {
        parent::setUp();
        // SYNTHETIC TEST ONLY: one product, two colours, sizes 40 and 40.5.
        $product = Product::factory()->create(['name' => 'Giày <b>Thử</b>', 'base_price' => 2_190_000, 'promotion_price' => 1_790_000]);
        $this->black = ProductColor::factory()->create(['product_id' => $product->id, 'color_name' => 'Đen']);
    }

    private function variant(array $attributes = [], int $stock = 5, ?ProductColor $color = null): ProductVariant
    {
        return ProductVariant::factory()->stocked($stock)->create(['product_color_id' => ($color ?? $this->black)->id, 'size_value' => '40', 'size_numeric' => '40.0', ...$attributes]);
    }

    private function add(int $variantId, int $quantity = 1, ?int $version = 0)
    {
        return $this->postJson(route('cart.items.store'), ['variant_id' => $variantId, 'quantity' => $quantity, 'version' => $version]);
    }

    public function test_guest_cart_lives_behind_a_hashed_session_token(): void
    {
        $variant = $this->variant();

        $this->add($variant->id, 2)
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.subtotal', 3_580_000)
            ->assertJsonPath('data.items.0.unit_price', 1_790_000)
            ->assertJsonPath('data.items.0.product_name', 'Giày <b>Thử</b>')
            ->assertJsonPath('data.items.0.size_value', '40')
            ->assertJsonMissingPath('data.items.0.average_cost')
            ->assertJsonPath('data.checkout_note', 'Giá và tồn kho được kiểm tra lại khi đặt hàng.');

        $token = session(CartService::SESSION_GUEST_TOKEN);
        $cart = Cart::query()->where('guest_token_hash', hash('sha256', $token))->sole();
        $this->assertNull($cart->user_id);
        $this->assertNotSame($token, $cart->guest_token_hash);
        $this->assertNotNull($cart->expires_at);

        $this->getJson(route('cart.show'))->assertOk()->assertJsonPath('data.count', 2);
    }

    public function test_two_colours_and_half_sizes_are_separate_lines(): void
    {
        $red = ProductColor::factory()->create(['product_id' => $this->black->product_id, 'color_name' => 'Đỏ']);
        $black40 = $this->variant();
        $black405 = $this->variant(['size_value' => '40.5', 'size_numeric' => '40.5']);
        $red40 = $this->variant([], 5, $red);

        $version = $this->add($black40->id)->json('data.version');
        $version = $this->add($black405->id, 1, $version)->json('data.version');
        $this->add($red40->id, 1, $version)
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.1.size_value', '40.5')
            ->assertJsonPath('data.items.2.color_name', 'Đỏ');
    }

    public function test_quantity_is_capped_by_available_stock_and_ten_per_size(): void
    {
        $low = $this->variant([], 3);
        $low->forceFill(['reserved_qty' => 1])->save();
        $plenty = $this->variant(['size_value' => '41', 'size_numeric' => '41.0'], 50);

        $version = $this->add($low->id, 2)->assertOk()->json('data.version');
        $this->add($low->id, 1, $version)
            ->assertStatus(409)
            ->assertJsonPath('code', 'STOCK_UNAVAILABLE')
            ->assertJsonPath('errors.quantity.0', 'Mỗi size chỉ có thể có tối đa 2 đôi trong giỏ.');

        $this->add($plenty->id, 11, $version)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $version = $this->add($plenty->id, 10, $version)->assertOk()->json('data.version');
        $this->add($plenty->id, 1, $version)->assertStatus(409);

        $this->assertSame(2, CartItem::query()->where('variant_id', $low->id)->value('quantity'));
        $this->assertSame(1, $low->fresh()->reserved_qty, 'Adding to cart never reserves stock.');
    }

    public function test_inactive_or_deleted_products_cannot_be_added(): void
    {
        $inactive = $this->variant(['is_active' => false]);
        $deleted = $this->variant(['size_value' => '41', 'size_numeric' => '41.0']);
        $deleted->delete();

        $this->add($inactive->id)->assertUnprocessable()->assertJsonPath('code', 'PRODUCT_UNAVAILABLE');
        $this->add($deleted->id)->assertUnprocessable();
        $this->add(999999999)->assertUnprocessable();
    }

    public function test_stale_version_is_rejected_with_the_current_cart(): void
    {
        $variant = $this->variant();
        $data = $this->add($variant->id)->json('data');

        $this->patchJson(route('cart.items.update', $data['items'][0]['id']), ['quantity' => 3, 'version' => $data['version'] - 1])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CART_STALE')
            ->assertJsonPath('data.version', $data['version'])
            ->assertJsonPath('data.items.0.quantity', 1);
    }

    public function test_update_remove_and_clear(): void
    {
        $a = $this->variant();
        $b = $this->variant(['size_value' => '41', 'size_numeric' => '41.0']);
        $version = $this->add($a->id)->json('data.version');
        $data = $this->add($b->id, 1, $version)->json('data');

        $data = $this->patchJson(route('cart.items.update', $data['items'][0]['id']), ['quantity' => 4, 'version' => $data['version']])
            ->assertOk()->assertJsonPath('data.items.0.quantity', 4)->json('data');
        $this->patchJson(route('cart.items.update', $data['items'][0]['id']), ['quantity' => 0, 'version' => $data['version']])
            ->assertUnprocessable();
        $data = $this->deleteJson(route('cart.items.destroy', $data['items'][1]['id']), ['version' => $data['version']])
            ->assertOk()->assertJsonCount(1, 'data.items')->json('data');
        $this->postJson(route('cart.clear'), ['version' => $data['version']])
            ->assertOk()->assertJsonPath('data.count', 0)->assertJsonCount(0, 'data.items');
    }

    public function test_items_of_another_cart_are_not_found(): void
    {
        $variant = $this->variant();
        $otherCart = (new Cart)->forceFill(['user_id' => User::factory()->customer()->create()->id, 'version' => 1]);
        $otherCart->save();
        $foreign = new CartItem;
        $foreign->forceFill(['cart_id' => $otherCart->id, 'variant_id' => $variant->id, 'quantity' => 1])->save();

        $version = $this->add($variant->id)->json('data.version');
        $this->patchJson(route('cart.items.update', $foreign->id), ['quantity' => 2, 'version' => $version])->assertNotFound();
        $this->deleteJson(route('cart.items.destroy', $foreign->id), ['version' => $version])->assertNotFound();

        $this->assertSame(1, $foreign->fresh()->quantity);
    }

    public function test_signed_in_user_uses_own_cart_and_ignores_client_owner_fields(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->variant();

        $this->actingAs($user)->postJson(route('cart.items.store'), [
            'variant_id' => $variant->id, 'quantity' => 1, 'version' => 0, 'user_id' => 1, 'cart_id' => 1, 'price' => 1,
        ])->assertOk()->assertJsonPath('data.items.0.unit_price', 1_790_000);

        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->sole()->items()->count());
        $this->assertNull(session(CartService::SESSION_GUEST_TOKEN));
    }

    public function test_items_that_became_unavailable_stay_visible_with_a_warning(): void
    {
        $variant = $this->variant();
        $this->add($variant->id, 2);
        $variant->forceFill(['stock_qty' => 1])->save();

        $this->getJson(route('cart.show'))
            ->assertJsonPath('data.items.0.eligible', false)
            ->assertJsonPath('data.items.0.notice', 'Chỉ còn 1 đôi.')
            ->assertJsonPath('data.subtotal', 0);

        $variant->forceFill(['is_active' => false])->save();
        $this->getJson(route('cart.show'))->assertJsonPath('data.items.0.notice', 'Sản phẩm đã ngừng bán.');
    }

    public function test_drawer_markup_is_wired_to_the_cart_routes(): void
    {
        $this->get(route('home'))
            ->assertSee('name="csrf-token"', false)
            ->assertSee('@cart-open.window', false)
            ->assertSee('$store.cart.count', false)
            ->assertSee('Đăng nhập để đặt hàng')
            ->assertSee('$store.cart.configure(', false)
            ->assertSee('__ID__', false);
    }
}
