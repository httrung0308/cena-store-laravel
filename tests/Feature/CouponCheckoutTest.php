<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponType;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CouponCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    private Address $address;

    private ProductVariant $shoe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $this->customer = User::factory()->customer()->create();
        $this->address = Address::factory()->create(['user_id' => $this->customer->id]);
        $this->shoe = $this->variant();
        $this->addToCart($this->customer, $this->address, $this->shoe);
    }

    public function test_percentage_coupon_snapshots_discount_and_cancel_releases_the_reserved_use(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10, 'usage_limit' => 1, 'per_user_limit' => 1]);
        $service = app(CheckoutService::class);
        $quote = $service->quote($this->customer, $this->address, couponCode: $coupon->code);
        $this->assertSame([1_000_000, 100_000, 40_000, 940_000], [$quote['subtotal'], $quote['merchandise_discount'], $quote['shipping_fee'], $quote['total']]);
        $order = $service->place($this->customer, $this->address->id, (string) Str::uuid(), (string) $quote['fingerprint'], null, couponCode: $coupon->code);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('SAVE10', $order->coupon_code_snapshot);
        $this->assertSame(900_000, $order->items->sole()->units->sole()->net_amount);
        $this->assertSame(CouponRedemptionStatus::Reserved, $order->redemption->status);

        app(\App\Services\OrderWorkflowService::class)->cancel($order, $this->customer, OrderStatus::Pending->value, 'Đặt nhầm size', true);
        $this->assertSame(CouponRedemptionStatus::Released, $order->redemption->fresh()->status);

        $secondCustomer = User::factory()->customer()->create();
        $secondAddress = Address::factory()->create(['user_id' => $secondCustomer->id]);
        $this->addToCart($secondCustomer, $secondAddress, $this->shoe->fresh());
        $secondQuote = $service->quote($secondCustomer, $secondAddress, couponCode: $coupon->code);
        $this->assertNull($secondQuote['coupon_error']);
        $second = $service->place($secondCustomer, $secondAddress->id, (string) Str::uuid(), (string) $secondQuote['fingerprint'], null, couponCode: $coupon->code);
        $this->assertSame(CouponRedemptionStatus::Reserved, $second->redemption->status);

        $thirdCustomer = User::factory()->customer()->create();
        $thirdAddress = Address::factory()->create(['user_id' => $thirdCustomer->id]);
        $this->addToCart($thirdCustomer, $thirdAddress, $this->shoe->fresh());
        $thirdQuote = $service->quote($thirdCustomer, $thirdAddress, couponCode: $coupon->code);
        $this->assertNotNull($thirdQuote['coupon_error']);
        $this->assertNull($thirdQuote['fingerprint']);
    }

    public function test_fixed_and_free_shipping_coupon_types_apply_to_saved_order_amounts(): void
    {
        $service = app(CheckoutService::class);
        $fixed = Coupon::factory()->create(['code' => 'FIXED250', 'type' => CouponType::Fixed, 'value' => 250_000]);
        $fixedQuote = $service->quote($this->customer, $this->address, couponCode: $fixed->code);
        $this->assertSame(750_000, $fixedQuote['total'] - $fixedQuote['shipping_fee']);

        $freeShipping = Coupon::factory()->create(['code' => 'SHIPFREE', 'type' => CouponType::FreeShipping, 'value' => 0]);
        $freeQuote = $service->quote($this->customer, $this->address, couponCode: $freeShipping->code);
        $this->assertSame([40_000, 0, 1_000_000], [$freeQuote['shipping_base_fee'], $freeQuote['shipping_fee'], $freeQuote['total']]);
    }

    public function test_reserved_coupon_use_is_consumed_only_when_delivery_succeeds(): void
    {
        Storage::fake('private');
        $coupon = Coupon::factory()->create(['code' => 'DELIVER10', 'type' => CouponType::Percentage, 'value' => 10]);
        $checkout = app(CheckoutService::class);
        $quote = $checkout->quote($this->customer, $this->address, couponCode: $coupon->code);
        $order = $checkout->place($this->customer, $this->address->id, (string) Str::uuid(), (string) $quote['fingerprint'], null, couponCode: $coupon->code);
        $this->assertSame(CouponRedemptionStatus::Reserved, $order->redemption->status);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.orders.transition', [$order, 'confirm']), ['expected_status' => OrderStatus::Pending->value])->assertRedirect();
        $this->post(route('admin.orders.transition', [$order, 'ship']), ['expected_status' => OrderStatus::Packing->value, 'carrier_name' => 'GHN'])->assertRedirect();
        $this->post(route('admin.orders.transition', [$order, 'deliver']), ['expected_status' => OrderStatus::OnDelivery->value, 'proof' => UploadedFile::fake()->image('delivered.jpg')])->assertRedirect();

        $this->assertSame(CouponRedemptionStatus::Consumed, $order->redemption->fresh()->status);
    }

    public function test_admin_can_create_and_edit_coupon_while_staff_cannot_manage_coupons(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.coupons.index'))->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $payload = [
            'code' => 'WELCOME15', 'name' => 'Khách hàng mới', 'type' => CouponType::Percentage->value, 'value' => 15,
            'min_order_amount' => 100_000, 'max_discount_amount' => 300_000, 'usage_limit' => 20,
            'per_user_limit' => 1, 'is_active' => 1,
        ];
        $this->actingAs($admin)->post(route('admin.coupons.store'), $payload)->assertRedirect(route('admin.coupons.index'));
        $coupon = Coupon::query()->where('code', 'WELCOME15')->sole();
        $this->assertTrue($coupon->is_active);

        $payload['name'] = 'Ưu đãi khách mới';
        $payload['is_active'] = 0;
        $this->put(route('admin.coupons.update', $coupon), $payload)->assertRedirect(route('admin.coupons.index'));
        $this->assertSame('Ưu đãi khách mới', $coupon->fresh()->name);
        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_checkout_quote_ajax_returns_json_without_page_reload(): void
    {
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->create(['user_id' => $customer->id]);
        $shoe = $this->variant();
        $this->addToCart($customer, $address, $shoe);

        $coupon = Coupon::factory()->create([
            'code' => 'AJAXDISCOUNT',
            'type' => CouponType::Fixed,
            'value' => 100_000,
            'min_order_amount' => 500_000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->getJson(route('checkout.show', [
            'address' => $address->id,
            'payment_method' => 'COD',
            'coupon_code' => 'AJAXDISCOUNT',
        ]));

        $response->assertOk();
        $response->assertJsonPath('coupon_code', 'AJAXDISCOUNT');
        $response->assertJsonPath('quote.merchandise_discount', 100_000);
        $response->assertJsonStructure([
            'quote' => ['fingerprint', 'subtotal', 'merchandise_discount', 'shipping_fee', 'total'],
            'formatted' => ['subtotal', 'merchandise_discount', 'shipping_fee', 'total'],
        ]);
    }

    private function addToCart(User $customer, Address $address, ProductVariant $shoe): void
    {
        $cart = (new Cart)->forceFill(['user_id' => $customer->id, 'version' => 1]);
        $cart->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $shoe->id, 'quantity' => 1])->save();
    }

    private function variant(): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => null]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '42', 'size_numeric' => '42']);
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'unit_cost_amount' => 100_000, 'subtotal_amount' => 500_000]);
        app(InventoryService::class)->receive(new InventoryChange('coupon-import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, 5, 100_000));

        return $variant->fresh();
    }
}
