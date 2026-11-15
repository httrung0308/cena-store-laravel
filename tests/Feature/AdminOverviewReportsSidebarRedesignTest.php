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

    public function test_staff_dashboard_keeps_reports_and_stock_imports_and_matches_unreplied_reviews_filter(): void
    {
        Review::factory()->create(['is_visible' => true, 'admin_reply' => null, 'comment' => 'Chờ shop phản hồi']);
        Review::factory()->create(['is_visible' => true, 'admin_reply' => 'Shop đã phản hồi', 'comment' => 'Đã trả lời rồi']);
        Review::factory()->create(['is_visible' => false, 'admin_reply' => null, 'comment' => 'Đánh giá đã ẩn']);

        $expectedHref = route('admin.reviews.index', ['visible' => '1', 'replied' => '0']);

        $this->actingAs($this->staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.reports.index'), false)
            ->assertSee(route('admin.stock-imports.create'), false)
            ->assertSee(htmlspecialchars($expectedHref, ENT_QUOTES), false);

        $this->actingAs($this->staff)
            ->get($expectedHref)
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
}
