<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SalesLedgerType;
use App\Models\AdministrativeUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BusinessClock;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StoreSalesDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSalesDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        foreach (['HCM-DEMO' => ['26734', '26737'], 'HN-DEMO' => ['00004']] as $provinceCode => $wardCodes) {
            $province = AdministrativeUnit::factory()->create(['code' => $provinceCode, 'dataset_version' => 'sales-demo-v1']);
            foreach ($wardCodes as $wardCode) {
                AdministrativeUnit::factory()->wardOf($province)->create(['code' => $wardCode]);
            }
        }
        config([
            'store.demo.enabled' => true,
            'store.demo.password' => 'demo-password-for-tests',
            'store.shipping.dataset_version' => 'sales-demo-v1',
            'store.shipping.hcm_province_code' => 'HCM-DEMO',
        ]);
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->administrator()->create();
        User::factory()->staff()->create();
    }

    protected function tearDown(): void
    {
        app(BusinessClock::class)->unfreeze();
        parent::tearDown();
    }

    public function test_seeds_a_believable_shop_through_the_real_workflow(): void
    {
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-11-03 05:00:00');
        $this->seed(StoreSalesDemoSeeder::class);
        $clock->freeze('2026-11-03 05:00:00');

        $products = Product::query()->where('brand_line', 'Nike')->get();
        $this->assertCount(12, $products);

        // Every pair is bought well below its selling price but never for "a few hundred thousand" on a 3M shoe.
        $sales = SalesLedgerEntry::query()->where('type', SalesLedgerType::SaleRecognized)
            ->selectRaw('SUM(merchandise_revenue_delta) AS revenue, SUM(cogs_delta) AS cogs')->first();
        $costRatio = (int) $sales->cogs / (int) $sales->revenue;
        $this->assertGreaterThan(0.6, $costRatio);
        $this->assertLessThan(0.85, $costRatio);
        foreach ($products as $product) {
            $average = (float) ProductVariant::query()->whereHas('color', fn ($query) => $query->where('product_id', $product->id))->avg('average_cost');
            $price = $product->promotion_price ?? $product->base_price;
            $this->assertGreaterThan($price * 0.6, $average, $product->name);
            $this->assertLessThan($price, $average, $product->name);
        }

        $statuses = Order::query()->groupBy('order_status')->selectRaw('order_status, COUNT(*) AS total')->pluck('total', 'order_status');
        $this->assertGreaterThan(300, $statuses->sum());
        $this->assertGreaterThan(0.8, $statuses[OrderStatus::Delivered->value] / $statuses->sum());
        foreach ([OrderStatus::Cancelled, OrderStatus::Failed, OrderStatus::OnDelivery] as $status) {
            $this->assertGreaterThan(0, $statuses[$status->value] ?? 0, $status->value);
        }

        $this->assertSame(0, ProductVariant::query()->whereRaw('stock_qty < reserved_qty')->count());
        $report = app(ReportService::class);
        ['start' => $start, 'end' => $end] = $report->range('2026-08-06', '2026-11-03');
        $money = $report->money($start, $end);
        $this->assertSame((int) DB::table('sales_ledger_entries')->sum(DB::raw('merchandise_revenue_delta + shipping_revenue_delta')), $money['revenue']);
        $this->assertGreaterThan(0, $money['profit']);
        $this->assertNotSame([], $report->inventory()['low']);

        $this->actingAs($this->admin)->get(route('admin.reports.index', ['from' => '2026-08-06', 'to' => '2026-11-03']))->assertOk()
            ->assertSee("Nike Air Force 1 '07");

        $orders = Order::query()->count();
        $this->seed(StoreSalesDemoSeeder::class);
        $this->assertSame($orders, Order::query()->count(), 'Running the seeder again changes nothing.');
    }
}
