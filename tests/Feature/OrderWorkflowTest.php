<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderUnitState;
use App\Enums\PaymentAttemptStatus;
use App\Enums\SalesLedgerType;
use App\Enums\ShipmentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\SalesLedgerEntry;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $customer;

    private ProductVariant $shoe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        Storage::fake('private');
        $this->staff = User::factory()->staff()->create();
        $this->customer = User::factory()->customer()->create();
        $this->shoe = $this->variant(stock: 4, cost: 300_000);
    }

    public function test_full_cod_flow_ships_stock_freezes_cogs_collects_cash_and_recognises_revenue(): void
    {
        $order = $this->placeOrder(quantity: 3);
        $this->actingAs($this->staff);

        foreach (['confirm' => [], 'ship' => ['carrier_name' => 'GHN', 'tracking_code' => 'GHN-123']] as $action => $fields) {
            $this->transition($order, $action, $fields)->assertRedirect(route('admin.orders.show', $order));
        }
        $this->shoe->refresh();
        $this->assertSame([1, 0], [$this->shoe->stock_qty, $this->shoe->reserved_qty], 'Dispatch takes the reserved pairs out of stock once.');
        $order->refresh();
        $item = $order->items->sole();
        $this->assertSame('300000.0000', $item->cost_unit_snapshot);
        $this->assertSame(900_000, $item->cogs_amount);
        $this->assertSame([300_000, 300_000, 300_000], $item->units->pluck('current_cogs_amount')->all());
        $this->assertSame(ShipmentStatus::InTransit, $order->shipment->status);

        // A later import at another cost must not rewrite the frozen COGS.
        $this->receive($this->shoe, 1, 900_000);

        $this->transition($order, 'deliver', ['proof' => UploadedFile::fake()->image('delivery.jpg')])->assertRedirect();
        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->order_status);
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame($order->total_amount, $order->paid_amount);
        $this->assertSame(PaymentAttemptStatus::Succeeded, $order->payments->sole()->status);
        $this->assertTrue($order->items->sole()->units->every(fn ($unit) => $unit->state === OrderUnitState::OwnedByCustomer && $unit->warranty_deadline_at !== null));
        $sales = SalesLedgerEntry::query()->where('order_id', $order->id)->where('type', SalesLedgerType::SaleRecognized)->get();
        $this->assertSame([2_400_000, 900_000], [$sales->sum('merchandise_revenue_delta'), $sales->sum('cogs_delta')]);
        $this->assertSame(1, SalesLedgerEntry::query()->where('order_id', $order->id)->where('type', SalesLedgerType::ShippingRecognized)->count());
        $this->assertSame(['PENDING', 'PACKING', 'ON_DELIVERY', 'DELIVERED'], $order->statusHistories()->orderBy('id')->get()->map(fn ($h) => $h->to_status->value)->all());
        $this->assertNotNull($order->shipment->deliveryProof);
        Storage::disk('private')->assertExists($order->shipment->deliveryProof->path);
        $this->actingAs($this->staff)->get(route('admin.orders.delivery-proof', $order))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->customer)->get(route('admin.orders.delivery-proof', $order))->assertForbidden();

        $this->actingAs($this->customer)->get(route('orders.show', $order->order_code))->assertSee('Giao thành công')->assertDontSee('Hủy đơn này');
    }

    public function test_staff_cancel_releases_the_reservation_and_voids_the_cod_attempt(): void
    {
        $order = $this->placeOrder(quantity: 2);
        $this->actingAs($this->staff);
        $this->transition($order, 'confirm');
        $this->transition($order->fresh(), 'cancel', ['reason' => 'Khách đổi ý qua điện thoại'])->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->order_status);
        $this->assertSame([4, 0], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty]);
        $this->assertSame(PaymentAttemptStatus::Failed, $order->payments->sole()->status);
        $this->assertTrue($order->items->sole()->units->every(fn ($unit) => $unit->state === OrderUnitState::Cancelled));
        $this->assertSame('Khách đổi ý qua điện thoại', $order->cancel_reason);
    }

    public function test_customer_cancels_only_their_own_pending_order(): void
    {
        $order = $this->placeOrder(quantity: 1);
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('orders.cancel', $order->order_code), ['expected_status' => 'PENDING', 'reason' => 'Không phải đơn của tôi'])->assertNotFound();

        $this->actingAs($this->customer)->get(route('orders.show', $order->order_code))->assertSee('Hủy đơn này');
        $this->post(route('orders.cancel', $order->order_code), ['expected_status' => 'PENDING', 'reason' => 'Đặt nhầm size'])->assertRedirect();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->order_status);
        $this->assertSame(0, $this->shoe->fresh()->reserved_qty);
        $this->assertSame('CUSTOMER_CANCEL', $order->statusHistories()->latest('id')->first()->reason_code);

        $confirmed = $this->placeOrder(quantity: 1);
        $this->actingAs($this->staff);
        $this->transition($confirmed, 'confirm');
        $this->actingAs($this->customer)->from(route('orders.show', $confirmed->order_code))
            ->post(route('orders.cancel', $confirmed->order_code), ['expected_status' => 'CONFIRMED', 'reason' => 'Muộn quá rồi'])->assertSessionHasErrors('order');
        $this->assertSame(OrderStatus::Packing, $confirmed->fresh()->order_status);
    }

    public function test_forbidden_edges_and_stale_double_clicks_change_nothing(): void
    {
        $order = $this->placeOrder(quantity: 1);
        $this->actingAs($this->staff)->from(route('admin.orders.show', $order));

        $this->transition($order, 'deliver', ['proof' => UploadedFile::fake()->image('wrong-state.jpg')], 'PENDING')->assertSessionHasErrors('order');
        $this->transition($order, 'ship', ['carrier_name' => 'GHN'], 'PENDING')->assertSessionHasErrors('order');
        $this->transition($order, 'confirm')->assertRedirect(route('admin.orders.show', $order));
        $this->transition($order, 'confirm', [], 'PENDING')->assertRedirect();

        $this->assertSame(2, OrderStatusHistory::query()->where('order_id', $order->id)->count(), 'The second click replays the same command.');
        $this->transition($order, 'pack', [], 'PENDING')->assertSessionHasErrors('order');
        $this->assertSame(OrderStatus::Packing, $order->fresh()->order_status);
    }

    public function test_idempotent_replays_do_not_duplicate_history_or_inventory_or_ledger(): void
    {
        Storage::fake('private');
        $order = $this->placeOrder(quantity: 1);
        $this->actingAs($this->staff)->from(route('admin.orders.show', $order));

        // Confirm & replay confirm
        $this->transition($order, 'confirm')->assertRedirect(route('admin.orders.show', $order));
        $this->transition($order, 'confirm', [], 'PENDING')->assertRedirect();
        $this->assertSame(2, OrderStatusHistory::query()->where('order_id', $order->id)->count());

        // Stale action 'pack' is rejected
        $this->transition($order, 'pack', [], 'PENDING')->assertSessionHasErrors('order');

        // Ship & replay ship
        $this->transition($order->fresh(), 'ship', ['carrier_name' => 'GHN', 'tracking_code' => 'GHN-123'])->assertRedirect();
        $historyCountAfterShip = OrderStatusHistory::query()->where('order_id', $order->id)->count();
        $movementsCountAfterShip = InventoryMovement::query()->where('reference_type', InventoryReferenceType::Order)->where('reference_id', $order->id)->count();

        // Replay ship with stale expected PACKING
        $this->transition($order->fresh(), 'ship', ['carrier_name' => 'GHN', 'tracking_code' => 'GHN-123'], 'PACKING')->assertRedirect();
        $this->assertSame($historyCountAfterShip, OrderStatusHistory::query()->where('order_id', $order->id)->count());
        $this->assertSame($movementsCountAfterShip, InventoryMovement::query()->where('reference_type', InventoryReferenceType::Order)->where('reference_id', $order->id)->count());
    }

    public function test_cod_delivery_requires_an_image_proof(): void
    {
        $order = $this->placeOrder(quantity: 1);
        $this->actingAs($this->staff);
        $this->transition($order, 'confirm');
        $this->transition($order->fresh(), 'ship', ['carrier_name' => 'GHN']);

        $this->from(route('admin.orders.show', $order))->transition($order->fresh(), 'deliver')->assertSessionHasErrors('proof');
        $this->assertSame(OrderStatus::OnDelivery, $order->fresh()->order_status);
        $this->assertSame(PaymentAttemptStatus::Pending, $order->payments()->sole()->status);
    }

    public function test_cod_proof_image_cannot_be_reused_for_two_orders_and_failed_delivery_keeps_stock_out(): void
    {
        $first = $this->placeOrder(quantity: 1);
        $second = $this->placeOrder(quantity: 1);
        $this->actingAs($this->staff);
        foreach ([$first, $second] as $order) {
            $this->transition($order, 'confirm');
            $this->transition($order->fresh(), 'ship', ['carrier_name' => 'GHN']);
        }
        $proof = UploadedFile::fake()->image('same-proof.jpg');
        $this->transition($first->fresh(), 'deliver', ['proof' => $proof]);
        $this->from(route('admin.orders.show', $second))->transition($second->fresh(), 'deliver', ['proof' => $proof])->assertSessionHasErrors('order');
        $this->assertSame(OrderPaymentStatus::Unpaid, $second->fresh()->payment_status);

        $this->transition($second->fresh(), 'fail', ['reason' => 'Khách không nghe máy'])->assertRedirect();
        $second->refresh();
        $this->assertSame(OrderStatus::Failed, $second->order_status);
        $this->assertSame(ShipmentStatus::Returning, $second->shipment->status);
        $this->assertSame(2, $this->shoe->fresh()->stock_qty, 'Stock comes back only when the return is received.');
    }

    public function test_back_office_order_pages_require_staff_and_escape_customer_input(): void
    {
        $order = $this->placeOrder(quantity: 1, note: '<script>alert(1)</script>');
        $this->actingAs($this->customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($this->customer)->post(route('admin.orders.transition', [$order, 'confirm']), ['expected_status' => 'PENDING'])->assertForbidden();

        $this->actingAs($this->staff)->get(route('admin.orders.index', ['search' => substr($order->order_code, 0, 12)]))->assertOk()->assertSee($order->order_code);
        $this->get(route('admin.orders.index', ['status' => 'DELIVERED']))->assertOk()->assertDontSee($order->order_code);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Xác nhận và đóng gói')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    private function transition(Order $order, string $action, array $fields = [], ?string $expected = null)
    {
        return $this->post(route('admin.orders.transition', [$order, $action]), ['expected_status' => $expected ?? $order->fresh()->order_status->value] + $fields);
    }

    private function placeOrder(int $quantity, ?string $note = null): Order
    {
        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $this->shoe->id, 'quantity' => $quantity])->save();
        $service = app(CheckoutService::class);

        return $service->place($this->customer, $address->id, (string) Str::uuid(), $service->quote($this->customer, $address)['fingerprint'], $note);
    }

    private function variant(int $stock, int $cost): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => 800_000, 'warranty_months' => 6, 'exchange_days' => 7]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41', 'price_override' => null]);
        $this->receive($variant, $stock, $cost);

        return $variant->fresh();
    }

    private function receive(ProductVariant $variant, int $quantity, int $cost): InventoryMovement
    {
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost_amount' => $cost, 'subtotal_amount' => $quantity * $cost]);

        return app(InventoryService::class)->receive(new InventoryChange('import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, $quantity, $cost));
    }
}
