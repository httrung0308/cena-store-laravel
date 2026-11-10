<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\SalesLedgerType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\SalesLedgerEntry;
use App\Models\StockImportItem;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderWorkflowService;
use App\Services\StoreNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StoreNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $customer;

    private ProductVariant $shoe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $this->staff = User::factory()->staff()->create();
        $this->customer = User::factory()->customer()->create();
        $this->shoe = $this->variant(stock: 6, cost: 300_000);
    }

    public function test_only_the_selected_order_statuses_create_one_notification_per_status_history(): void
    {
        $workflow = app(OrderWorkflowService::class);
        $delivered = $this->placeOrder();
        $workflow->confirm($delivered, $this->staff, OrderStatus::Pending->value);
        $this->assertSame(0, StoreNotification::query()->where('order_id', $delivered->id)->count(), 'CONFIRMED is intentionally excluded.');
        $workflow->pack($delivered->fresh(), $this->staff, OrderStatus::Confirmed->value);
        $packingHistory = $delivered->statusHistories()->where('to_status', OrderStatus::Packing)->sole();
        $workflow->pack($delivered->fresh(), $this->staff, OrderStatus::Confirmed->value);
        DB::transaction(fn () => app(StoreNotificationService::class)->recordForStatusHistory($packingHistory));
        $workflow->ship($delivered->fresh(), $this->staff, OrderStatus::Packing->value, 'GHN', 'GHN-F3-1');
        $workflow->deliver($delivered->fresh(), $this->staff, OrderStatus::OnDelivery->value, 'PT-F3-1');

        $cancelled = $this->placeOrder();
        $workflow->confirm($cancelled, $this->staff, OrderStatus::Pending->value);
        $workflow->cancel($cancelled->fresh(), $this->staff, OrderStatus::Confirmed->value, 'Khách yêu cầu hủy trước khi đóng gói', false);

        $failed = $this->placeOrder();
        $workflow->confirm($failed, $this->staff, OrderStatus::Pending->value);
        $workflow->pack($failed->fresh(), $this->staff, OrderStatus::Confirmed->value);
        $workflow->ship($failed->fresh(), $this->staff, OrderStatus::Packing->value, 'GHN', 'GHN-F3-2');
        $workflow->fail($failed->fresh(), $this->staff, OrderStatus::OnDelivery->value, 'Khách vắng mặt sau ba lần liên hệ');

        $statusesByOrder = StoreNotification::query()->whereIn('order_id', [$delivered->id, $cancelled->id, $failed->id])
            ->orderBy('id')->get()->groupBy('order_id')
            ->map(fn ($records) => $records->pluck('order_status')->map(fn (OrderStatus $status): string => $status->value)->all());
        $this->assertSame([
            $delivered->id => ['PACKING', 'ON_DELIVERY', 'DELIVERED'],
            $cancelled->id => ['CANCELLED'],
            $failed->id => ['PACKING', 'ON_DELIVERY', 'FAILED'],
        ], $statusesByOrder->all());
        $this->assertSame(7, StoreNotification::query()->whereIn('order_id', [$delivered->id, $cancelled->id, $failed->id])->count());
        $this->assertSame(OrderPaymentStatus::Paid, $delivered->fresh()->payment_status);
        $this->assertSame(2, SalesLedgerEntry::query()->where('order_id', $delivered->id)->whereIn('type', [SalesLedgerType::SaleRecognized, SalesLedgerType::ShippingRecognized])->count());
    }

    public function test_status_rollback_also_rolls_back_notification_creation(): void
    {
        $order = $this->placeOrder();
        $workflow = app(OrderWorkflowService::class);

        try {
            DB::transaction(function () use ($order, $workflow): void {
                $workflow->confirm($order, $this->staff, OrderStatus::Pending->value);
                throw new RuntimeException('Roll back the enclosing order transaction.');
            });
            $this->fail('The enclosing transaction should have rolled back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Roll back the enclosing order transaction.', $exception->getMessage());
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
        $this->assertSame(1, $order->statusHistories()->count(), 'Only the original PENDING history remains.');
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_status_history_is_not_discarded_for_inactive_or_soft_deleted_owners(): void
    {
        $inactive = User::factory()->customer()->inactive()->create();
        $deleted = User::factory()->customer()->create();
        $deleted->delete();

        foreach ([$inactive, $deleted] as $owner) {
            $order = Order::factory()->create(['user_id' => $owner->id, 'order_status' => OrderStatus::Packing]);
            OrderStatusHistory::factory()->for($order, 'order')->create(['to_status' => OrderStatus::Packing]);

            $this->assertDatabaseHas('notifications', [
                'user_id' => $owner->id,
                'order_id' => $order->id,
                'order_status' => OrderStatus::Packing->value,
            ]);
        }
    }

    public function test_notification_factory_make_does_not_persist_a_related_graph(): void
    {
        $before = [
            User::query()->count(),
            Order::query()->count(),
            OrderStatusHistory::query()->count(),
            StoreNotification::query()->count(),
        ];

        StoreNotification::factory()->make();

        $this->assertSame($before, [
            User::query()->count(),
            Order::query()->count(),
            OrderStatusHistory::query()->count(),
            StoreNotification::query()->count(),
        ]);
    }

    public function test_notification_factory_create_preserves_explicit_owner_and_source_ids(): void
    {
        $owner = User::factory()->customer()->create();
        $order = Order::factory()->for($owner)->create(['order_status' => OrderStatus::Packing]);
        $history = OrderStatusHistory::withoutEvents(fn (): OrderStatusHistory => OrderStatusHistory::factory()
            ->for($order, 'order')
            ->create(['to_status' => OrderStatus::Packing]));

        $notification = StoreNotification::factory()->create([
            'user_id' => $owner->id,
            'order_id' => $order->id,
            'order_status_history_id' => $history->id,
        ]);

        $this->assertSame($owner->id, $notification->user_id);
        $this->assertSame($order->id, $notification->order_id);
        $this->assertSame($history->id, $notification->order_status_history_id);
    }

    public function test_notification_page_api_count_mark_read_and_owner_scope_are_safe(): void
    {
        $order = $this->placeOrder();
        $workflow = app(OrderWorkflowService::class);
        $workflow->confirm($order, $this->staff, OrderStatus::Pending->value);
        $workflow->pack($order->fresh(), $this->staff, OrderStatus::Confirmed->value);
        $notification = StoreNotification::query()->where('order_id', $order->id)->sole();
        $url = route('orders.show', $order->order_code);

        $this->actingAs($this->customer)->get(route('notifications.index'))
            ->assertOk()->assertSee($order->order_code)->assertSee('Đang đóng gói')->assertSee($url)->assertDontSee('payment')->assertDontSee('cost');
        $this->get(route('home'))->assertOk()->assertSee(route('notifications.index'))->assertSee('1 chưa đọc');
        $this->getJson(route('notifications.api.unread-count'))->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->postJson(route('notifications.api.read', $notification))->assertOk()->assertJsonPath('data.notification.order_code', $order->order_code)
            ->assertJsonPath('data.notification.order_status', OrderStatus::Packing->value)->assertJsonPath('data.notification.url', $url);
        $readAt = $notification->fresh()->read_at;
        $this->postJson(route('notifications.api.read', $notification))->assertOk();
        $this->assertSame($readAt->toISOString(), $notification->fresh()->read_at->toISOString());
        $this->post(route('notifications.read', $notification))->assertRedirect(route('notifications.index'));
        $this->getJson(route('notifications.api.unread-count'))->assertOk()->assertJsonPath('data.unread_count', 0);

        $otherCustomer = User::factory()->customer()->create();
        $this->actingAs($otherCustomer)->get(route('notifications.index'))->assertOk()->assertDontSee($order->order_code);
        $this->postJson(route('notifications.api.read', $notification))->assertNotFound();
        $this->actingAs(User::factory()->administrator()->create())->get(route('notifications.index'))->assertForbidden();
        auth()->logout();
        $this->getJson(route('notifications.api.unread-count'))->assertUnauthorized();
    }

    public function test_notification_order_resource_never_serializes_order_or_payment_details(): void
    {
        $order = $this->placeOrder(note: 'Chi tiết nhạy cảm của khách không được đưa vào thông báo.');
        app(OrderWorkflowService::class)->confirm($order, $this->staff, OrderStatus::Pending->value);
        app(OrderWorkflowService::class)->pack($order->fresh(), $this->staff, OrderStatus::Confirmed->value);
        $notification = StoreNotification::query()->where('order_id', $order->id)->sole();

        $this->actingAs($this->customer)->getJson(route('notifications.api.unread-count'))->assertOk();
        $response = $this->postJson(route('notifications.api.read', $notification));
        $response->assertOk()->assertDontSee('sensitive')->assertDontSee('total_amount')->assertDontSee('customer_note');
        $this->assertSame($this->customer->id, $notification->fresh()->user_id);
    }

    private function placeOrder(?string $note = null): Order
    {
        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $this->shoe->id, 'quantity' => 1])->save();
        $checkout = app(CheckoutService::class);

        return $checkout->place($this->customer, $address->id, (string) Str::uuid(), $checkout->quote($this->customer, $address)['fingerprint'], $note);
    }

    private function variant(int $stock, int $cost): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => 800_000, 'warranty_months' => 6, 'exchange_days' => 7]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41', 'price_override' => null]);
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $stock, 'unit_cost_amount' => $cost, 'subtotal_amount' => $stock * $cost]);
        app(InventoryService::class)->receive(new InventoryChange('notification-test:import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, $stock, $cost));

        return $variant->fresh();
    }
}
