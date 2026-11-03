<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\SalesLedgerType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\SalesLedgerEntry;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SalesLedgerService;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
        $this->customer = User::factory()->customer()->create();
    }

    protected function tearDown(): void
    {
        app(BusinessClock::class)->unfreeze();
        parent::tearDown();
    }

    public function test_money_comes_from_the_ledger_for_delivered_orders_only_and_sums_reversals(): void
    {
        $runner = $this->variant('Runner <Một>', stock: 10, cost: 560_000);
        $court = $this->variant('Court Hai', stock: 10, cost: 540_000);
        $this->freeze('2026-11-01 03:00:00');
        $delivered = $this->deliver($this->placeOrder($runner, 3));
        $small = $this->deliver($this->placeOrder($court, 1));
        $this->placeOrder($runner, 2);
        $cancelled = $this->placeOrder($court, 2);
        $this->actingAs($this->staff)->post(route('admin.orders.transition', [$cancelled, 'cancel']), ['expected_status' => 'PENDING', 'reason' => 'Khách đổi ý'])->assertRedirect();
        $failed = $this->ship($this->placeOrder($court, 1));
        $this->actingAs($this->staff)->post(route('admin.orders.transition', [$failed, 'fail']), ['expected_status' => 'ON_DELIVERY', 'reason' => 'Không liên lạc được'])->assertRedirect();

        $unit = $delivered->items->sole()->units->first();
        DB::transaction(fn () => app(SalesLedgerService::class)->postUnique('test:reverse:'.$unit->id, SalesLedgerType::SaleReversed,
            ['merchandise_revenue_delta' => -$unit->net_amount, 'cogs_delta' => -560_000], ['order_id' => $delivered->id, 'order_item_unit_id' => $unit->id], '2026-11-01 04:00:00'));

        // 3 x 800k + 1 x 800k merchandise with one pair reversed; the 2.4M order ships free, the small one pays 40k.
        $merchandise = 4 * 800_000 - $unit->net_amount;
        $shipping = $delivered->shipping_fee + $small->shipping_fee;
        $this->assertSame([0, 40_000], [$delivered->shipping_fee, $small->shipping_fee]);
        $cogs = 3 * 560_000 + 540_000 - 560_000;
        $ledgerRows = SalesLedgerEntry::query()->count();
        $stock = ProductVariant::query()->orderBy('id')->get(['stock_qty', 'reserved_qty'])->toArray();

        $this->actingAs($this->admin)->get(route('admin.reports.index', ['from' => '2026-11-01', 'to' => '2026-11-01']))->assertOk()
            ->assertViewHas('money', ['revenue' => $merchandise + $shipping, 'merchandise' => $merchandise, 'shipping' => $shipping, 'cogs' => $cogs, 'expense' => 0, 'profit' => $merchandise + $shipping - $cogs])
            ->assertViewHas('bestSellers', fn (array $rows) => array_column($rows, 'pairs') === [3, 1] && $rows[0]['name'] === 'Runner <Một>')
            ->assertViewHas('daily', fn (array $days) => $days === [['day' => '2026-11-01', 'revenue' => $merchandise + $shipping, 'cogs' => $cogs, 'profit' => $merchandise + $shipping - $cogs]])
            ->assertViewHas('operations', [['day' => '2026-11-01', 'orders' => 5, 'pairs' => 4]])
            ->assertSee('Runner &lt;Một&gt;', false)->assertDontSee('Runner <Một>', false)
            ->assertSee('Giá vốn')->assertSee('Lợi nhuận')->assertSee('data-report-chart', false);

        $this->assertSame($ledgerRows, SalesLedgerEntry::query()->count(), 'The dashboard never writes to the ledger.');
        $this->assertSame($stock, ProductVariant::query()->orderBy('id')->get(['stock_qty', 'reserved_qty'])->toArray(), 'The dashboard never touches stock.');
    }

    public function test_days_follow_vietnamese_time(): void
    {
        $shoe = $this->variant('Night Runner', stock: 5, cost: 580_000);
        // 17:30 UTC on 31/10 is 00:30 on 01/11 in Vietnam.
        $this->freeze('2026-10-31 17:30:00');
        $this->deliver($this->placeOrder($shoe, 1));
        $this->actingAs($this->admin);

        $this->get(route('admin.reports.index', ['from' => '2026-10-31', 'to' => '2026-10-31']))
            ->assertViewHas('money', fn (array $money) => $money['revenue'] === 0)->assertViewHas('bestSellers', []);
        $this->get(route('admin.reports.index', ['from' => '2026-11-01', 'to' => '2026-11-01']))
            ->assertViewHas('money', fn (array $money) => $money['merchandise'] === 800_000)
            ->assertViewHas('bestSellers', fn (array $rows) => $rows[0]['pairs'] === 1);
        $this->get(route('admin.reports.index', ['from' => '2026-11-02', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
    }

    public function test_stock_counts_sellable_pairs_and_warns_about_low_sizes(): void
    {
        $plenty = $this->variant('Plenty', stock: 20, cost: 580_000);
        $low = $this->variant('Almost Gone', stock: 6, cost: 580_000);
        $before = app(ReportService::class)->inventory();
        $this->placeOrder($low, 2);
        $plenty->forceFill(['quarantine_qty' => 3])->save();

        $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()
            ->assertViewHas('inventory', fn (array $inventory) => $inventory['sellable'] === $before['sellable'] - 2
                && in_array('Almost Gone', array_column($inventory['low'], 'product'), true)
                && ! in_array('Plenty', array_column($inventory['low'], 'product'), true)
                && collect($inventory['low'])->firstWhere('product', 'Almost Gone')['sellable'] === 4
                && $inventory['reserved'] === $before['reserved'] + 2
                && $inventory['quarantine'] === $before['quarantine'] + 3)
            ->assertSee('Cảnh báo sắp hết hàng');
    }

    public function test_admin_compares_with_the_previous_period_of_the_same_length(): void
    {
        $shoe = $this->variant('Trend Runner', stock: 10, cost: 560_000);
        // 10:00 on 31/10 and 01/11 in Vietnam: one pair the day before, two pairs on the selected day.
        $this->freeze('2026-10-31 03:00:00');
        $this->deliver($this->placeOrder($shoe, 1));
        $this->freeze('2026-11-01 03:00:00');
        $this->deliver($this->placeOrder($shoe, 2));

        $this->actingAs($this->admin)->get(route('admin.reports.index', ['from' => '2026-11-01', 'to' => '2026-11-01']))->assertOk()
            ->assertViewHas('money', fn (array $money) => $money['merchandise'] === 1_600_000 && $money['cogs'] === 1_120_000)
            ->assertViewHas('previousMoney', fn (array $money) => $money['merchandise'] === 800_000 && $money['cogs'] === 560_000)
            ->assertViewHas('presets', fn (array $presets) => $presets[0] === ['label' => '7 ngày', 'from' => '2026-10-26', 'to' => '2026-11-01'])
            ->assertSee('so với kỳ trước')->assertSee('Mỗi 100 đồng doanh thu')->assertSee('Biên lợi nhuận');
    }

    public function test_staff_sees_operational_figures_only_and_customers_are_kept_out(): void
    {
        $shoe = $this->variant('Staff View', stock: 5, cost: 563_457);
        $this->deliver($this->placeOrder($shoe, 1));

        $this->actingAs($this->staff)->get(route('admin.reports.index'))->assertOk()
            ->assertViewHas('money', null)->assertViewHas('previousMoney', null)->assertViewHas('daily', [])
            ->assertViewHas('operations', fn (array $days) => array_sum(array_column($days, 'pairs')) === 1)
            ->assertSee('Sản phẩm bán chạy')->assertSee('Staff View')->assertSee('Tồn kho còn bán được')->assertSee('data-operations-chart', false)
            ->assertDontSee('data-metric', false)->assertDontSee('data-report-chart', false)->assertDontSee('563.457')
            ->assertDontSee('&quot;revenue&quot;', false)->assertDontSee('"revenue"', false)->assertDontSee('Mỗi 100 đồng doanh thu');
        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertSee('href="'.route('admin.reports.index').'"', false);

        $this->actingAs($this->customer)->get(route('admin.reports.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    }

    private function freeze(string $instant): void
    {
        app(BusinessClock::class)->freeze($instant);
    }

    private function ship(Order $order): Order
    {
        $this->actingAs($this->staff);
        foreach (['confirm' => [], 'pack' => [], 'ship' => ['carrier_name' => 'GHN', 'tracking_code' => 'GHN-'.$order->id]] as $action => $fields) {
            $this->post(route('admin.orders.transition', [$order, $action]), ['expected_status' => $order->fresh()->order_status->value] + $fields)->assertRedirect();
        }

        return $order->fresh();
    }

    private function deliver(Order $order): Order
    {
        $order = $this->ship($order);
        $this->post(route('admin.orders.transition', [$order, 'deliver']), ['expected_status' => 'ON_DELIVERY', 'receipt_reference' => 'PT-'.$order->id])->assertRedirect();

        return $order->fresh();
    }

    private function placeOrder(ProductVariant $variant, int $quantity): Order
    {
        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        CartItem::query()->where('cart_id', $cart->id)->delete();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => $quantity])->save();
        $service = app(CheckoutService::class);

        return $service->place($this->customer, $address->id, (string) Str::uuid(), $service->quote($this->customer, $address)['fingerprint'], null);
    }

    private function variant(string $name, int $stock, int $cost): ProductVariant
    {
        $product = Product::factory()->create(['name' => $name, 'category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => 800_000, 'warranty_months' => 6, 'exchange_days' => 7]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41', 'price_override' => null]);
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $stock, 'unit_cost_amount' => $cost, 'subtotal_amount' => $stock * $cost]);
        app(InventoryService::class)->receive(new InventoryChange('import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, $stock, $cost));

        return $variant->fresh();
    }
}
