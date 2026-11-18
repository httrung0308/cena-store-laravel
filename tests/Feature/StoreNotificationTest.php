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
use App\Models\Review;
use App\Models\SalesLedgerEntry;
use App\Models\StockImportItem;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderWorkflowService;
use App\Services\StoreNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
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
        $workflow->confirmAndPack($delivered, $this->staff, OrderStatus::Pending->value);
        $packingHistory = $delivered->statusHistories()->where('to_status', OrderStatus::Packing)->sole();
        DB::transaction(fn () => app(StoreNotificationService::class)->recordForStatusHistory($packingHistory));
        $workflow->ship($delivered->fresh(), $this->staff, OrderStatus::Packing->value, 'GHN', 'GHN-F3-1');
        $workflow->deliver($delivered->fresh(), $this->staff, OrderStatus::OnDelivery->value, UploadedFile::fake()->image('proof.jpg'));

        $cancelled = $this->placeOrder();
        $workflow->cancel($cancelled->fresh(), $this->staff, OrderStatus::Pending->value, 'Khách yêu cầu hủy trước khi đóng gói', false);

        $failed = $this->placeOrder();
        $workflow->confirmAndPack($failed, $this->staff, OrderStatus::Pending->value);
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
        $workflow->confirmAndPack($order, $this->staff, OrderStatus::Pending->value);
        $notification = StoreNotification::query()->where('order_id', $order->id)->sole();
        $url = route('orders.show', $order->order_code);

        $firstItem = $order->items()->orderBy('id')->firstOrFail();
        $expectedSummary = $firstItem->product_name_snapshot.' · '.$firstItem->color_name_snapshot.' · Size '.$firstItem->size_value_snapshot;

        $this->actingAs($this->customer)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Đơn hàng đang được đóng gói')
            ->assertSee($expectedSummary)
            ->assertDontSee('Đã đọc')
            ->assertDontSee('Xem đơn')
            ->assertDontSee('payment')
            ->assertDontSee('cost');
        $this->get(route('home'))->assertOk()->assertSee(route('notifications.index'))->assertSee('1 chưa đọc');
        $this->getJson(route('notifications.api.unread-count'))->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->postJson(route('notifications.api.read', $notification))->assertOk()
            ->assertJsonPath('data.notification.order_code', $order->order_code)
            ->assertJsonPath('data.notification.order_status', OrderStatus::Packing->value)
            ->assertJsonPath('data.notification.headline', 'Đơn hàng đang được đóng gói')
            ->assertJsonPath('data.notification.item_summary', $expectedSummary)
            ->assertJsonPath('data.notification.url', $url);
        $readAt = $notification->fresh()->read_at;
        $this->postJson(route('notifications.api.read', $notification))->assertOk();
        $this->assertSame($readAt->toISOString(), $notification->fresh()->read_at->toISOString());
        $this->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionMissing('status');
        $this->getJson(route('notifications.api.unread-count'))->assertOk()->assertJsonPath('data.unread_count', 0);

        $otherCustomer = User::factory()->customer()->create();
        $this->actingAs($otherCustomer)->get(route('notifications.index'))->assertOk()->assertDontSee($order->order_code);
        $this->postJson(route('notifications.api.read', $notification))->assertNotFound();
        $this->post(route('notifications.read', $notification))->assertNotFound();
        $this->actingAs(User::factory()->administrator()->create())->get(route('notifications.index'))->assertForbidden();
        auth()->logout();
        $this->getJson(route('notifications.api.unread-count'))->assertUnauthorized();
    }

    public function test_recent_notifications_api_is_limited_to_six_newest_first_and_owner_scoped(): void
    {
        $otherCustomer = User::factory()->customer()->create();
        $otherOrder = Order::factory()->for($otherCustomer)->create(['order_status' => OrderStatus::Packing]);
        $otherHistory = OrderStatusHistory::withoutEvents(fn (): OrderStatusHistory => OrderStatusHistory::factory()
            ->for($otherOrder, 'order')
            ->create(['to_status' => OrderStatus::Packing]));
        StoreNotification::factory()->create([
            'user_id' => $otherCustomer->id,
            'order_id' => $otherOrder->id,
            'order_status_history_id' => $otherHistory->id,
            'created_at' => now()->addMinute(),
        ]);

        $createdIds = [];
        $baseTime = now()->subHours(2);
        for ($i = 0; $i < 8; $i++) {
            $order = Order::factory()->for($this->customer)->create(['order_status' => OrderStatus::Packing]);
            $history = OrderStatusHistory::withoutEvents(fn (): OrderStatusHistory => OrderStatusHistory::factory()
                ->for($order, 'order')
                ->create(['to_status' => OrderStatus::Packing]));
            $notification = StoreNotification::factory()->create([
                'user_id' => $this->customer->id,
                'order_id' => $order->id,
                'order_status_history_id' => $history->id,
                'created_at' => $baseTime->copy()->addMinutes($i),
                'read_at' => $i === 0 ? $baseTime->copy()->addMinutes($i + 1) : null,
            ]);
            $createdIds[] = $notification->id;
        }

        $response = $this->actingAs($this->customer)->getJson(route('notifications.api.recent'));

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 7)
            ->assertJsonCount(6, 'data.notifications');

        $returnedIds = collect($response->json('data.notifications'))->pluck('id')->all();
        $expectedIds = array_reverse(array_slice($createdIds, 2, 6));
        $this->assertSame($expectedIds, $returnedIds);
        $this->assertNotContains($otherOrder->order_code, collect($response->json('data.notifications'))->pluck('order_code')->all());
    }

    public function test_mark_all_read_api_only_marks_current_users_unread_notifications(): void
    {
        $otherCustomer = User::factory()->customer()->create();
        $otherOrder = Order::factory()->for($otherCustomer)->create(['order_status' => OrderStatus::Packing]);
        $otherHistory = OrderStatusHistory::withoutEvents(fn (): OrderStatusHistory => OrderStatusHistory::factory()
            ->for($otherOrder, 'order')
            ->create(['to_status' => OrderStatus::Packing]));
        $otherNotification = StoreNotification::factory()->create([
            'user_id' => $otherCustomer->id,
            'order_id' => $otherOrder->id,
            'order_status_history_id' => $otherHistory->id,
            'read_at' => null,
        ]);

        $myUnread = [];
        for ($i = 0; $i < 2; $i++) {
            $order = Order::factory()->for($this->customer)->create(['order_status' => OrderStatus::Packing]);
            $history = OrderStatusHistory::withoutEvents(fn (): OrderStatusHistory => OrderStatusHistory::factory()
                ->for($order, 'order')
                ->create(['to_status' => OrderStatus::Packing]));
            $myUnread[] = StoreNotification::factory()->create([
                'user_id' => $this->customer->id,
                'order_id' => $order->id,
                'order_status_history_id' => $history->id,
                'read_at' => null,
            ]);
        }

        $this->actingAs($this->customer)
            ->postJson(route('notifications.api.read-all'))
            ->assertOk()
            ->assertJsonPath('data.marked_count', 2)
            ->assertJsonPath('data.unread_count', 0);

        foreach ($myUnread as $item) {
            $this->assertNotNull($item->fresh()->read_at);
        }
        $this->assertNull($otherNotification->fresh()->read_at);

        $this->actingAs($this->customer)
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionMissing('status');
    }

    public function test_notification_order_resource_never_serializes_order_or_payment_details(): void
    {
        $order = $this->placeOrder(note: 'Chi tiết nhạy cảm của khách không được đưa vào thông báo.');
        app(OrderWorkflowService::class)->confirmAndPack($order, $this->staff, OrderStatus::Pending->value);
        $notification = StoreNotification::query()->where('order_id', $order->id)->sole();

        $this->actingAs($this->customer)->getJson(route('notifications.api.unread-count'))->assertOk();
        $response = $this->postJson(route('notifications.api.read', $notification));
        $response->assertOk()->assertDontSee('sensitive')->assertDontSee('total_amount')->assertDontSee('customer_note');
        $this->assertSame($this->customer->id, $notification->fresh()->user_id);
    }

    public function test_notification_summary_uses_snapshots_after_product_rename_or_delete_and_handles_empty_items(): void
    {
        $secondShoe = $this->variant(stock: 4, cost: 250_000);
        $thirdShoe = $this->variant(stock: 4, cost: 250_000);
        $order = $this->placeOrderWithVariants([$this->shoe, $secondShoe, $thirdShoe]);
        app(OrderWorkflowService::class)->confirmAndPack($order, $this->staff, OrderStatus::Pending->value);

        $firstItem = $order->items()->orderBy('id')->firstOrFail();
        $expectedSummary = $firstItem->product_name_snapshot.' · '.$firstItem->color_name_snapshot.' · Size '.$firstItem->size_value_snapshot.' · Cùng 2 sản phẩm khác';

        // Rename and soft-delete the underlying product to verify snapshot resilience.
        $product = $this->shoe->color->product;
        $product->forceFill(['name' => 'Renamed Product Should Not Appear'])->save();
        $product->delete();

        $emptyOrder = Order::factory()->for($this->customer)->create(['order_status' => OrderStatus::Cancelled]);
        OrderStatusHistory::factory()->for($emptyOrder, 'order')->create(['to_status' => OrderStatus::Cancelled]);

        $recent = $this->actingAs($this->customer)->getJson(route('notifications.api.recent'));
        $recent->assertOk();
        $notifications = collect($recent->json('data.notifications'));

        $multiItemNotification = $notifications->firstWhere('order_code', $order->order_code);
        $this->assertNotNull($multiItemNotification);
        $this->assertSame('Đơn hàng đang được đóng gói', $multiItemNotification['headline']);
        $this->assertSame($expectedSummary, $multiItemNotification['item_summary']);
        $this->assertStringNotContainsString('Renamed Product Should Not Appear', $multiItemNotification['item_summary']);

        $emptyItemNotification = $notifications->firstWhere('order_code', $emptyOrder->order_code);
        $this->assertNotNull($emptyItemNotification);
        $this->assertSame('Đơn hàng đã bị hủy', $emptyItemNotification['headline']);
        $this->assertSame('Đơn hàng', $emptyItemNotification['item_summary']);

        $this->actingAs($this->customer)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($expectedSummary)
            ->assertDontSee('Renamed Product Should Not Appear');
    }

    public function test_delivered_notification_includes_review_cta_according_to_eligibility(): void
    {
        $workflow = app(OrderWorkflowService::class);

        // 1. Single eligible active product -> shoes.show#product-reviews
        $singleOrder = $this->placeOrder();
        $workflow->confirmAndPack($singleOrder, $this->staff, OrderStatus::Pending->value);
        $workflow->ship($singleOrder->fresh(), $this->staff, OrderStatus::Packing->value, 'GHN', 'GHN-REV-1');
        $workflow->deliver($singleOrder->fresh(), $this->staff, OrderStatus::OnDelivery->value, UploadedFile::fake()->image('proof1.jpg'));

        $singleProduct = $this->shoe->color->product;
        $expectedSingleUrl = route('shoes.show', $singleProduct->slug).'#product-reviews';

        $recent = $this->actingAs($this->customer)->getJson(route('notifications.api.recent'))->assertOk();
        $singleDelivered = collect($recent->json('data.notifications'))
            ->firstWhere('order_code', $singleOrder->order_code);
        $this->assertSame('Đơn hàng đã giao thành công', $singleDelivered['headline']);
        $this->assertSame([
            'label' => 'Đánh giá sản phẩm',
            'prompt' => 'Chia sẻ cảm nhận về sản phẩm bạn vừa nhận.',
            'url' => $expectedSingleUrl,
        ], $singleDelivered['review_cta']);

        // 2. Unpaid delivered order -> review_cta is null
        $singleOrder->refresh()->forceFill(['payment_status' => OrderPaymentStatus::Unpaid])->save();
        $unpaidRecent = $this->actingAs($this->customer)->getJson(route('notifications.api.recent'))->assertOk();
        $unpaidDelivered = collect($unpaidRecent->json('data.notifications'))
            ->firstWhere('order_code', $singleOrder->order_code);
        $this->assertNull($unpaidDelivered['review_cta']);

        // Restore paid status for remaining checks
        $singleOrder->refresh()->forceFill(['payment_status' => OrderPaymentStatus::Paid])->save();

        // 3. Multiple eligible distinct products -> orders.show
        $secondShoe = $this->variant(stock: 4, cost: 250_000);
        $multiOrder = $this->placeOrderWithVariants([$this->shoe, $secondShoe]);
        $workflow->confirmAndPack($multiOrder, $this->staff, OrderStatus::Pending->value);
        $workflow->ship($multiOrder->fresh(), $this->staff, OrderStatus::Packing->value, 'GHN', 'GHN-REV-2');
        $workflow->deliver($multiOrder->fresh(), $this->staff, OrderStatus::OnDelivery->value, UploadedFile::fake()->image('proof2.jpg', 120, 120));

        $multiRecent = $this->actingAs($this->customer)->getJson(route('notifications.api.recent'))->assertOk();
        $multiDelivered = collect($multiRecent->json('data.notifications'))
            ->firstWhere('order_code', $multiOrder->order_code);
        $this->assertSame(route('orders.show', $multiOrder->order_code), $multiDelivered['review_cta']['url']);

        // 4. Once all units are reviewed -> review_cta is null
        foreach ($singleOrder->items()->with('units')->get() as $item) {
            foreach ($item->units as $unit) {
                Review::factory()->create([
                    'order_item_unit_id' => $unit->id,
                    'user_id' => $this->customer->id,
                ]);
            }
        }
        $afterReviewRecent = $this->actingAs($this->customer)->getJson(route('notifications.api.recent'))->assertOk();
        $afterReviewDelivered = collect($afterReviewRecent->json('data.notifications'))
            ->firstWhere('order_code', $singleOrder->order_code);
        $this->assertNull($afterReviewDelivered['review_cta']);
    }

    public function test_reading_notification_does_not_review_and_reviewing_does_not_alter_notifications(): void
    {
        $workflow = app(OrderWorkflowService::class);
        $order = $this->placeOrder();
        $workflow->confirmAndPack($order, $this->staff, OrderStatus::Pending->value);
        $workflow->ship($order->fresh(), $this->staff, OrderStatus::Packing->value, 'GHN', 'GHN-REV-3');
        $workflow->deliver($order->fresh(), $this->staff, OrderStatus::OnDelivery->value, UploadedFile::fake()->image('proof3.jpg'));

        $deliveredNotification = StoreNotification::query()
            ->where('order_id', $order->id)
            ->where('order_status', OrderStatus::Delivered)
            ->sole();
        $originalCreatedAt = $deliveredNotification->created_at->toISOString();
        $notificationCountBefore = StoreNotification::query()->where('order_id', $order->id)->count();

        $unit = $order->items()->firstOrFail()->units()->firstOrFail();

        // Reading the notification does not mark the unit reviewed or change created_at.
        $readResponse = $this->actingAs($this->customer)
            ->postJson(route('notifications.api.read', $deliveredNotification))
            ->assertOk();
        $this->assertNotNull($readResponse->json('data.notification.review_cta'));
        $this->assertFalse(Review::query()->where('order_item_unit_id', $unit->id)->exists());
        $this->assertSame($originalCreatedAt, $deliveredNotification->fresh()->created_at->toISOString());

        // Creating a review does not create a new notification or mutate created_at.
        Review::factory()->create([
            'order_item_unit_id' => $unit->id,
            'user_id' => $this->customer->id,
        ]);

        $this->assertSame($notificationCountBefore, StoreNotification::query()->where('order_id', $order->id)->count());
        $this->assertSame($originalCreatedAt, $deliveredNotification->fresh()->created_at->toISOString());
    }

    /** @param list<ProductVariant> $variants */
    private function placeOrderWithVariants(array $variants, ?string $note = null): Order
    {
        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        foreach ($variants as $variant) {
            (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => 1])->save();
        }
        $checkout = app(CheckoutService::class);

        return $checkout->place($this->customer, $address->id, (string) Str::uuid(), $checkout->quote($this->customer, $address)['fingerprint'], $note);
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
