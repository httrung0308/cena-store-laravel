<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderUnitState;
use App\Enums\PaymentAttemptStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1']);
        $this->customer = User::factory()->customer()->create();
        $this->address = Address::factory()->create(['user_id' => $this->customer->id, 'is_default' => true]);
        config(['store.shipping.hcm_province_code' => 'not-this-province']);
    }

    public function test_guest_is_sent_to_login_and_empty_cart_goes_back_to_the_catalogue(): void
    {
        $this->get(route('checkout.show'))->assertRedirect(route('login'));
        $this->actingAs($this->customer)->get(route('checkout.show'))->assertRedirect(route('shoes.index'));
    }

    public function test_cod_order_snapshots_prices_reserves_stock_and_empties_the_cart(): void
    {
        $shoe = $this->variant(stock: 5, base: 1_000_000, promotion: 800_000, size: '40.5');
        $cart = $this->cart([$shoe->id => 2]);

        $page = $this->actingAs($this->customer)->get(route('checkout.show'))->assertOk()
            ->assertSee('Thanh toán khi nhận hàng (COD)')->assertSee('1.600.000 ₫')->assertSee('40.000 ₫')->assertSee('1.640.000 ₫');
        $this->post(route('checkout.store'), $this->payload($page->viewData('quote')['fingerprint']) + ['customer_note' => '<b>Giao giờ trưa</b>', 'total_amount' => 1])
            ->assertRedirect();

        $order = Order::query()->where('user_id', $this->customer->id)->sole();
        $this->assertSame(OrderStatus::Pending, $order->order_status);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame([1_600_000, 40_000, 1_640_000], [$order->subtotal_amount, $order->shipping_fee, $order->total_amount]);
        $this->assertSame('40.5', $order->items->sole()->size_value_snapshot);
        $this->assertSame(800_000, $order->items->sole()->unit_price);
        $this->assertSame([1, 2], $order->items->sole()->units->pluck('unit_no')->all());
        $this->assertTrue($order->items->sole()->units->every(fn ($unit) => $unit->state === OrderUnitState::Allocated && $unit->net_amount === 800_000));
        $this->assertSame(PaymentAttemptStatus::Pending, $order->payments->sole()->status);
        $this->assertSame(1_640_000, $order->payments->sole()->expected_amount);
        $this->assertSame('ORDER_CREATED', $order->statusHistories->sole()->reason_code);

        $shoe->refresh();
        $this->assertSame([5, 2], [$shoe->stock_qty, $shoe->reserved_qty], 'Checkout reserves, it never ships stock.');
        $this->assertSame(1, InventoryMovement::query()->where('reference_id', $order->id)->where('action', InventoryAction::OrderReserved)->count());
        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
        $this->assertSame($cart->version + 1, $cart->fresh()->version);

        $this->get(route('orders.show', $order->order_code))->assertOk()
            ->assertSee($order->order_code)->assertSee('&lt;b&gt;Giao giờ trưa&lt;/b&gt;', false)->assertSee('Chờ xác nhận');
    }

    public function test_same_checkout_key_returns_the_same_order_and_a_changed_payload_is_rejected(): void
    {
        $shoe = $this->variant(stock: 5);
        $this->cart([$shoe->id => 1]);
        $service = app(CheckoutService::class);
        $fingerprint = $service->quote($this->customer, $this->address)['fingerprint'];
        $key = (string) Str::uuid();

        $first = $service->place($this->customer, $this->address->id, $key, $fingerprint, null);
        $again = $service->place($this->customer, $this->address->id, $key, $fingerprint, null);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, InventoryMovement::query()->where('reference_id', $first->id)->count());
        $this->actingAs($this->customer)->from(route('checkout.show'))
            ->post(route('checkout.store'), ['address_id' => $this->address->id, 'checkout_key' => $key, 'fingerprint' => str_repeat('a', 64), 'payment_method' => 'COD'])
            ->assertRedirect()->assertSessionHasErrors('checkout');
        $this->assertSame(1, Order::query()->where('user_id', $this->customer->id)->count());
    }

    public function test_price_change_after_the_quote_is_rejected_without_side_effects(): void
    {
        $shoe = $this->variant(stock: 5, base: 900_000, promotion: null);
        $cart = $this->cart([$shoe->id => 1]);
        $fingerprint = app(CheckoutService::class)->quote($this->customer, $this->address)['fingerprint'];
        $shoe->forceFill(['price_override' => 950_000])->save();

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload($fingerprint))
            ->assertRedirect()->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $shoe->fresh()->reserved_qty);
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_one_short_sku_rolls_back_the_whole_order_and_names_the_size(): void
    {
        $plenty = $this->variant(stock: 5, size: '41');
        $last = $this->variant(stock: 1, size: '42');
        $this->cart([$plenty->id => 2, $last->id => 1]);
        $fingerprint = app(CheckoutService::class)->quote($this->customer, $this->address)['fingerprint'];
        // Someone else's order takes the last pair after this customer saw the quote.
        $this->reserveElsewhere($last);

        $this->actingAs($this->customer)->post(route('checkout.store'), $this->payload($fingerprint))
            ->assertRedirect()->assertSessionHasErrors('checkout');

        $this->assertStringContainsString('size 42 đã hết hàng', session('errors')->first('checkout'));
        $this->assertSame(0, Order::query()->where('user_id', $this->customer->id)->count());
        $this->assertSame(0, $plenty->fresh()->reserved_qty);
    }

    public function test_shipping_is_free_from_two_million_and_cheaper_inside_hcm(): void
    {
        $service = app(CheckoutService::class);
        $this->assertSame(0, $service->shippingFee(2_000_000, $this->address));
        $this->assertSame(40_000, $service->shippingFee(1_999_999, $this->address));
        config(['store.shipping.hcm_province_code' => $this->address->province_code_snapshot]);
        $this->assertSame(30_000, $service->shippingFee(1_999_999, $this->address));
    }

    public function test_another_users_address_or_order_is_never_used_or_shown(): void
    {
        $shoe = $this->variant(stock: 5);
        $this->cart([$shoe->id => 1]);
        $foreign = Address::factory()->create();
        $fingerprint = app(CheckoutService::class)->quote($this->customer, $this->address)['fingerprint'];

        $this->actingAs($this->customer)->post(route('checkout.store'), ['address_id' => $foreign->id] + $this->payload($fingerprint))
            ->assertRedirect()->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::query()->count());

        $this->post(route('checkout.store'), $this->payload($fingerprint))->assertRedirect();
        $order = Order::query()->sole();
        $this->actingAs(User::factory()->customer()->create())->get(route('orders.show', $order->order_code))->assertNotFound();
        $this->get(route('orders.index'))->assertOk()->assertDontSee($order->order_code);
    }

    public function test_drawer_and_product_page_lead_to_checkout(): void
    {
        $this->actingAs($this->customer)->get(route('home'))->assertSee('href="'.route('checkout.show').'"', false)->assertSee('Đặt hàng');
    }

    /** @return array<string, mixed> */
    private function payload(?string $fingerprint): array
    {
        return ['address_id' => $this->address->id, 'checkout_key' => (string) Str::uuid(), 'fingerprint' => (string) $fingerprint, 'payment_method' => 'COD'];
    }

    private function variant(int $stock, int $base = 1_000_000, ?int $promotion = 800_000, string $size = '40'): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => $base, 'promotion_price' => $promotion, 'name' => 'Giày Thử '.Str::random(4)]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => $size, 'size_numeric' => $size, 'price_override' => null]);
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $stock, 'unit_cost_amount' => 100, 'subtotal_amount' => $stock * 100]);
        app(InventoryService::class)->receive(new InventoryChange('import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, $stock, 100));

        return $variant->fresh();
    }

    /** @param array<int, int> $lines variant id => quantity */
    private function cart(array $lines): Cart
    {
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => 3])->save();
        foreach ($lines as $variantId => $quantity) {
            (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variantId, 'quantity' => $quantity])->save();
        }

        return $cart;
    }

    private function reserveElsewhere(ProductVariant $variant): void
    {
        $other = User::factory()->customer()->create();
        $otherAddress = Address::factory()->create(['user_id' => $other->id]);
        $otherCart = (new Cart)->forceFill(['user_id' => $other->id, 'version' => 1]);
        $otherCart->save();
        (new CartItem)->forceFill(['cart_id' => $otherCart->id, 'variant_id' => $variant->id, 'quantity' => 1])->save();
        $service = app(CheckoutService::class);
        $service->place($other, $otherAddress->id, (string) Str::uuid(), $service->quote($other, $otherAddress)['fingerprint'], null);
    }
}
