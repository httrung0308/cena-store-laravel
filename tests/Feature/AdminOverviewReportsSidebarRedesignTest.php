<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\SalesLedgerType;
use App\Enums\StockImportStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Review;
use App\Models\SalesLedgerEntry;
use App\Models\StockImport;
use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminOverviewReportsSidebarRedesignTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_order_status_confirmed_and_packing_have_distinct_labels(): void
    {
        $this->assertSame('Đã xác nhận', OrderStatus::Confirmed->label());
        $this->assertSame('Đang đóng gói', OrderStatus::Packing->label());
        $this->assertSame('CONFIRMED', OrderStatus::Confirmed->value);
        $this->assertSame('PACKING', OrderStatus::Packing->value);
    }

    public function test_overview_counters_match_filtered_list_queries_and_respect_roles(): void
    {
        Order::factory()->create(['order_status' => OrderStatus::Pending]);
        Order::factory()->create(['order_status' => OrderStatus::Confirmed]);
        Order::factory()->create(['order_status' => OrderStatus::Packing]);

        $refundOrder = Order::factory()->create([
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => OrderPaymentStatus::RefundPending,
        ]);
        Refund::factory()->create([
            'order_id' => $refundOrder->id,
            'status' => RefundStatus::Requested,
        ]);

        StockImport::factory()->create(['status' => StockImportStatus::Draft]);

        $category = Category::factory()->create();
        $outProduct = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $outColor = ProductColor::factory()->create(['product_id' => $outProduct->id, 'is_active' => true]);
        $lowVariant = ProductVariant::factory()->create([
            'product_color_id' => $outColor->id,
            'is_active' => true,
            'stock_qty' => 0,
            'reserved_qty' => 0,
        ]);

        Review::factory()->create([
            'is_visible' => true,
            'admin_reply' => null,
        ]);

        $adminDashboard = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $adminDashboard->assertOk()
            ->assertDontSee('Trạng thái hệ thống')
            ->assertDontSee('Realtime')
            ->assertDontSee('Thời gian thực')
            ->assertSee(route('admin.orders.index', ['status' => 'PENDING']), false)
            ->assertSee(route('admin.orders.index', ['status' => 'CONFIRMED']), false)
            ->assertSee(route('admin.orders.index', ['status' => 'PACKING']), false)
            ->assertSee(route('admin.stock-imports.index', ['status' => 'DRAFT']), false)
            ->assertSee(route('admin.products.index', ['stock' => 'out_of_stock']), false)
            ->assertSee(route('admin.stock-imports.create', ['variant_id' => $lowVariant->id]), false)
            ->assertSee(route('admin.orders.index', ['payment_status' => 'REFUND_PENDING']), false);

        // Verify the filtered list routes accept the exact query params linked from Overview
        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'PENDING']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === (int) $adminDashboard->viewData('pendingOrdersCount'));

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'CONFIRMED']))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === (int) $adminDashboard->viewData('confirmedOrdersCount'));

        $this->actingAs($this->admin)->get(route('admin.stock-imports.index', ['status' => 'DRAFT']))
            ->assertOk()
            ->assertViewHas('imports', fn ($imports) => $imports->total() === (int) $adminDashboard->viewData('draftImportsCount'));

        $this->actingAs($this->admin)->get(route('admin.products.index', ['stock' => 'out_of_stock']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === (int) $adminDashboard->viewData('outOfStockProductsCount'));

        // Staff sees operational queues and Staff mobile badge, but not Admin refund approval queue
        $this->actingAs($this->staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('isAdmin', false)
            ->assertViewHas('pendingRefunds', fn ($refunds) => $refunds->isEmpty())
            ->assertDontSee(route('admin.orders.index', ['payment_status' => 'REFUND_PENDING']), false);
    }

    public function test_reports_daily_includes_expense_and_validates_exceeding_date_range_without_silent_reset(): void
    {
        SalesLedgerEntry::factory()->create([
            'type' => SalesLedgerType::InventoryLoss,
            'merchandise_revenue_delta' => 0,
            'shipping_revenue_delta' => 0,
            'cogs_delta' => 0,
            'expense_delta' => 250_000,
            'occurred_at' => '2026-11-10 04:00:00',
        ]);

        // Revenue = 0, Expense = 250,000 -> daily includes expense directly and finance activity is recognized
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['from' => '2026-11-10', 'to' => '2026-11-10']))
            ->assertOk()
            ->assertViewHas('money', [
                'revenue' => 0,
                'merchandise' => 0,
                'shipping' => 0,
                'cogs' => 0,
                'expense' => 250_000,
                'profit' => -250_000,
            ])
            ->assertViewHas('daily', [
                [
                    'day' => '2026-11-10',
                    'revenue' => 0,
                    'cogs' => 0,
                    'expense' => 250_000,
                    'profit' => -250_000,
                ],
            ])
            ->assertSee('250.000')
            ->assertDontSee('Realtime');

        // Range > 366 days returns validation error and keeps old input instead of silently resetting to 30 days
        $this->from(route('admin.reports.index'))
            ->actingAs($this->admin)
            ->get(route('admin.reports.index', ['from' => '2025-01-01', 'to' => '2026-11-10']))
            ->assertRedirect(route('admin.reports.index'))
            ->assertSessionHasErrors('to')
            ->assertSessionHasInput('from', '2025-01-01')
            ->assertSessionHasInput('to', '2026-11-10');
    }

    public function test_sidebar_renders_collapsible_groups_and_marks_active_group_on_subroutes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('aria-controls="admin-nav-group-content"', false)
            ->assertSee('data-sidebar-group="content" data-group-active="true"', false)
            ->assertDontSee('không thay đổi phiên đăng nhập hiện tại')
            ->assertDontSee('72 byte');
    }

    public function test_staff_dashboard_keeps_reports_and_stock_imports_and_treats_review_replies_as_optional(): void
    {
        Review::factory()->create(['is_visible' => true, 'admin_reply' => null, 'comment' => 'Chờ shop phản hồi']);
        Review::factory()->create(['is_visible' => true, 'admin_reply' => 'Shop đã phản hồi', 'comment' => 'Đã trả lời rồi']);
        Review::factory()->create(['is_visible' => false, 'admin_reply' => null, 'comment' => 'Đánh giá đã ẩn']);

        $this->actingAs($this->staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.reports.index'), false)
            ->assertSee(route('admin.stock-imports.create'), false)
            ->assertSee('role="tooltip"', false)
            ->assertDontSee('Đánh giá chưa phản hồi')
            ->assertDontSee('Đánh giá mới chưa phản hồi');

        $this->actingAs($this->staff)
            ->get(route('admin.reviews.index', ['visible' => '1', 'replied' => '0']))
            ->assertOk()
            ->assertSee('Chờ shop phản hồi')
            ->assertDontSee('Đã trả lời rồi')
            ->assertDontSee('Đánh giá đã ẩn');
    }

    public function test_reports_renders_segmented_tabs_by_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('id="tab-finance"', false)
            ->assertSee('id="tab-operations"', false)
            ->assertSee('id="tab-inventory"', false)
            ->assertSee('id="tab-feedback"', false);

        $this->actingAs($this->staff)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertDontSee('id="tab-finance"', false)
            ->assertSee('id="tab-operations"', false)
            ->assertSee('id="tab-inventory"', false)
            ->assertDontSee('id="tab-feedback"', false);
    }

    public function test_reports_calendar_periods_boundaries_navigation_and_admin_footer(): void
    {
        // Monday 2024-04-01 00:30 VN (2024-03-31 17:30 UTC) — boundary of week, month, Q2, and leap year 2024
        $clock = app(BusinessClock::class);
        $clock->freeze(CarbonImmutable::parse('2024-03-31 17:30:00', 'UTC'));

        try {
            // Default opens current calendar Month (2024-04-01 .. 2024-04-01) and renders updated admin footer
            $this->actingAs($this->admin)
                ->get(route('admin.reports.index'))
                ->assertOk()
                ->assertViewHas('period', 'month')
                ->assertViewHas('from', '2024-04-01')
                ->assertViewHas('to', '2024-04-01')
                ->assertViewHas('nextPeriod', null)
                ->assertSee('© 2024 CENA — Quản trị cửa hàng.')
                ->assertDontSee('Nike');

            // Past leap-year February (2024-02-01 .. 2024-02-29) & Prev/Next
            $this->actingAs($this->admin)
                ->get(route('admin.reports.index', ['period' => 'month', 'from' => '2024-02-01', 'to' => '2024-02-29']))
                ->assertOk()
                ->assertViewHas('period', 'month')
                ->assertViewHas('from', '2024-02-01')
                ->assertViewHas('to', '2024-02-29')
                ->assertViewHas('prevPeriod', ['from' => '2024-01-01', 'to' => '2024-01-31'])
                ->assertViewHas('nextPeriod', ['from' => '2024-03-01', 'to' => '2024-03-31']);

            // Quarter Q1 2024 (2024-01-01 .. 2024-03-31)
            $this->actingAs($this->admin)
                ->get(route('admin.reports.index', ['period' => 'quarter', 'from' => '2024-01-01', 'to' => '2024-03-31']))
                ->assertOk()
                ->assertViewHas('period', 'quarter')
                ->assertViewHas('nextPeriod', ['from' => '2024-04-01', 'to' => '2024-04-01']);

            // Tuesday 2024-04-02 10:00 VN -> Week starts Monday 2024-04-01 .. 2024-04-02 (capped at viewing time 10:00, not end of day)
            $clock->freeze(CarbonImmutable::parse('2024-04-02 03:00:00', 'UTC'));
            SalesLedgerEntry::factory()->create([
                'type' => SalesLedgerType::SaleRecognized,
                'merchandise_revenue_delta' => 500_000,
                'shipping_revenue_delta' => 0,
                'cogs_delta' => 250_000,
                'expense_delta' => 0,
                'occurred_at' => '2024-04-02 02:55:00', // 09:55 VN (included)
            ]);
            SalesLedgerEntry::factory()->create([
                'type' => SalesLedgerType::SaleRecognized,
                'merchandise_revenue_delta' => 900_000,
                'shipping_revenue_delta' => 0,
                'cogs_delta' => 450_000,
                'expense_delta' => 0,
                'occurred_at' => '2024-04-02 03:05:00', // 10:05 VN (after viewing time, excluded)
            ]);
            $this->actingAs($this->admin)
                ->get(route('admin.reports.index', ['period' => 'week']))
                ->assertOk()
                ->assertViewHas('from', '2024-04-01')
                ->assertViewHas('to', '2024-04-02')
                ->assertViewHas('asOfTime', '10:00')
                ->assertViewHas('money', fn (array $m) => $m['merchandise'] === 500_000)
                ->assertSee('(đến 10:00)')
                ->assertViewHas('prevPeriod', ['from' => '2024-03-25', 'to' => '2024-03-31'])
                ->assertViewHas('nextPeriod', null);

            // Leap year 2024 full past year check when frozen in 2025-01-15
            $clock->freeze(CarbonImmutable::parse('2025-01-15 03:00:00', 'UTC'));
            $this->actingAs($this->admin)
                ->get(route('admin.reports.index', ['period' => 'year', 'from' => '2024-01-01', 'to' => '2024-12-31']))
                ->assertOk()
                ->assertViewHas('from', '2024-01-01')
                ->assertViewHas('to', '2024-12-31')
                ->assertViewHas('nextPeriod', ['from' => '2025-01-01', 'to' => '2025-01-15']);

            // Custom range preserves 'custom' mode and disables nextPeriod
            $this->actingAs($this->admin)
                ->get(route('admin.reports.index', ['period' => 'custom', 'from' => '2025-01-05', 'to' => '2025-01-10', 'tab' => 'operations']))
                ->assertOk()
                ->assertViewHas('period', 'custom')
                ->assertViewHas('activeTab', 'operations')
                ->assertViewHas('nextPeriod', null)
                ->assertSee('Khoảng tùy chọn');
        } finally {
            $clock->unfreeze();
        }
    }
}
