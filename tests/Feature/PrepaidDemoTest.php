<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\SalesLedgerType;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepaidDemoTest extends TestCase
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
        $cart = (new Cart)->forceFill(['user_id' => $this->customer->id, 'version' => 1]);
        $cart->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $this->shoe->id, 'quantity' => 1])->save();
    }

    public function test_prepaid_order_reserves_stock_then_owner_can_pay_idempotently(): void
    {
        $service = app(CheckoutService::class);
        $quote = $service->quote($this->customer, $this->address, PaymentMethod::PrepaidDemo);
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'address_id' => $this->address->id,
            'checkout_key' => (string) Str::uuid(),
            'fingerprint' => $quote['fingerprint'],
            'payment_method' => PaymentMethod::PrepaidDemo->value,
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $this->customer->id)->sole();
        $this->assertSame(PaymentMethod::PrepaidDemo, $order->payment_method);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(1, $this->shoe->fresh()->reserved_qty);
        $this->assertSame(PaymentAttemptStatus::Pending, $order->payments->sole()->status);

        $this->get(route('orders.payment.show', $order->order_code))->assertOk()->assertSee(number_format($order->total_amount, 0, ',', '.').' ₫');
        $this->post(route('orders.payment.complete', $order->order_code), ['result' => 'success'])->assertRedirect();
        $this->post(route('orders.payment.complete', $order->order_code), ['result' => 'success'])->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame($order->total_amount, $order->paid_amount);
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->where('status', PaymentAttemptStatus::Succeeded)->count());
        $this->assertSame(0, SalesLedgerEntry::query()->where('order_id', $order->id)->count(), 'Payment alone does not recognize a sale.');

        $other = User::factory()->customer()->create();
        $this->actingAs($other)->get(route('orders.payment.show', $order->order_code))->assertNotFound();
        $this->post(route('orders.payment.complete', $order->order_code), ['result' => 'success'])->assertNotFound();
    }

    public function test_failed_demo_attempt_can_be_retried_and_paid_order_cancel_requests_exact_refund(): void
    {
        $order = $this->placePrepaidOrder();
        $this->actingAs($this->customer)->post(route('orders.payment.complete', $order->order_code), ['result' => 'failure'])->assertRedirect();
        $this->assertSame(PaymentAttemptStatus::Failed, $order->payments()->firstOrFail()->status);

        $this->post(route('orders.payment.retry', $order->order_code))->assertRedirect();
        $this->assertSame(2, Payment::query()->where('order_id', $order->id)->count());
        $this->post(route('orders.payment.complete', $order->order_code), ['result' => 'success'])->assertRedirect();
        $this->assertSame(OrderPaymentStatus::Paid, $order->fresh()->payment_status);

        $this->post(route('orders.cancel', $order->order_code), ['expected_status' => OrderStatus::Pending->value, 'reason' => 'Khách đổi ý'])->assertRedirect();
        $order->refresh();
        $refund = Refund::query()->where('order_id', $order->id)->sole();
        $this->assertSame(RefundStatus::Requested, $refund->status);
        $this->assertSame($order->paid_amount, $refund->amount);
        $this->assertSame(OrderPaymentStatus::RefundPending, $order->payment_status);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post(route('admin.orders.refunds.approve', [$order, $refund]))->assertRedirect();
        $this->assertSame(RefundStatus::Approved, $refund->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertSame($order->paid_amount, $order->fresh()->refunded_amount);
        $this->assertNull($refund->fresh()->paid_by, 'The demo approval does not claim a real transfer.');
        $this->assertNull($refund->fresh()->succeeded_at);
    }

    public function test_paid_prepaid_order_can_be_delivered_without_a_photo_and_records_sales(): void
    {
        $order = $this->placePrepaidOrder();
        $this->actingAs($this->customer)->post(route('orders.payment.complete', $order->order_code), ['result' => 'success'])->assertRedirect();
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->post(route('admin.orders.transition', [$order, 'confirm']), ['expected_status' => OrderStatus::Pending->value])->assertRedirect();
        $this->post(route('admin.orders.transition', [$order, 'ship']), ['expected_status' => OrderStatus::Packing->value, 'carrier_name' => 'GHN'])->assertRedirect();
        $this->post(route('admin.orders.transition', [$order, 'deliver']), ['expected_status' => OrderStatus::OnDelivery->value])->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->order_status);
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertNull($order->shipment->deliveryProof);
        $this->assertSame(1, SalesLedgerEntry::query()->where('order_id', $order->id)->where('type', SalesLedgerType::ShippingRecognized)->count());
        $this->assertSame(1, SalesLedgerEntry::query()->where('order_id', $order->id)->where('type', SalesLedgerType::SaleRecognized)->count());
    }

    private function placePrepaidOrder(): Order
    {
        $service = app(CheckoutService::class);
        $fingerprint = $service->quote($this->customer, $this->address, PaymentMethod::PrepaidDemo)['fingerprint'];

        return $service->place($this->customer, $this->address->id, (string) Str::uuid(), (string) $fingerprint, null, PaymentMethod::PrepaidDemo);
    }

    private function variant(): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => null]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41']);
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 2, 'unit_cost_amount' => 100_000, 'subtotal_amount' => 200_000]);
        app(InventoryService::class)->receive(new InventoryChange('prepaid-import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, 2, 100_000));

        return $variant->fresh();
    }
}
