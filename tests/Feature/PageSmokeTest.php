<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Article;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $customer;

    private Address $address;

    private Product $product;

    private ProductVariant $variant;

    private Article $article;

    private Category $category;

    private Supplier $supplier;

    private StockImport $stockImport;

    private Order $order;

    private Review $review;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1']);

        $this->admin = User::factory()->administrator()->create(['full_name' => 'Admin Quản Trị']);
        $this->customer = User::factory()->customer()->create(['full_name' => 'Khách Hàng Thử']);
        $this->address = Address::factory()->create(['user_id' => $this->customer->id, 'is_default' => true]);

        $this->category = Category::factory()->create(['is_active' => true]);
        $this->supplier = Supplier::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Giày Smoke Test '.Str::random(5),
            'is_active' => true,
        ]);
        $color = ProductColor::factory()->create(['product_id' => $this->product->id, 'is_active' => true]);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id]);
        $this->variant = ProductVariant::factory()->create([
            'product_color_id' => $color->id,
            'size_value' => '41',
            'size_numeric' => '41',
            'is_active' => true,
        ]);

        $this->stockImport = StockImport::factory()->create(['supplier_id' => $this->supplier->id]);
        StockImportItem::factory()->create([
            'stock_import_id' => $this->stockImport->id,
            'variant_id' => $this->variant->id,
            'quantity' => 10,
            'unit_cost_amount' => 500000,
            'subtotal_amount' => 5000000,
        ]);
        app(InventoryService::class)->receive(new InventoryChange(
            'smoke-import:'.$this->stockImport->id,
            $this->variant->id,
            InventoryAction::ImportPosted,
            InventoryReferenceType::StockImport,
            $this->stockImport->id,
            10,
            500000
        ));

        $this->article = Article::factory()->published()->create([
            'author_id' => $this->admin->id,
            'title' => 'Bài Viết Smoke Test '.Str::random(5),
        ]);

        $this->order = Order::factory()->create([
            'order_code' => 'ORD-20261106-'.strtoupper((string) Str::ulid()),
            'user_id' => $this->customer->id,
            'order_status' => OrderStatus::Pending,
        ]);
        $orderItem = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);
        $unit = OrderItemUnit::factory()->create([
            'order_item_id' => $orderItem->id,
        ]);
        OrderStatusHistory::factory()->create([
            'order_id' => $this->order->id,
            'from_status' => null,
            'to_status' => OrderStatus::Pending,
        ]);

        $this->review = Review::factory()->create([
            'order_item_unit_id' => $unit->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
            'is_visible' => true,
        ]);
    }

    public function test_admin_index_and_subpages_render_without_error(): void
    {
        $admin = $this->admin;

        // Index pages
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.stock-imports.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.articles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();

        // Create pages
        $this->actingAs($admin)->get(route('admin.products.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.categories.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.suppliers.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.stock-imports.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.articles.create'))->assertOk();

        // Edit / Show pages
        $this->actingAs($admin)->get(route('admin.products.edit', $this->product))->assertOk();
        $this->actingAs($admin)->get(route('admin.categories.edit', $this->category))->assertOk();
        $this->actingAs($admin)->get(route('admin.suppliers.edit', $this->supplier))->assertOk();
        $this->actingAs($admin)->get(route('admin.stock-imports.show', $this->stockImport))->assertOk();
        $this->actingAs($admin)->get(route('admin.stock-imports.edit', $this->stockImport))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.show', $this->order))->assertOk();
        $this->actingAs($admin)->get(route('admin.articles.edit', $this->article))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.show', $this->customer))->assertOk();
    }

    public function test_customer_and_guest_pages_render_without_error(): void
    {
        // Guest pages
        $this->get(route('home'))->assertOk();
        $this->get(route('shoes.index'))->assertOk();
        $this->get(route('shoes.show', $this->product->slug))->assertOk();
        $this->get(route('news.index'))->assertOk();
        $this->get(route('news.show', $this->article->slug))->assertOk();
        $this->get(route('cart.show'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();

        // Customer account pages
        $this->actingAs($this->customer)->get(route('account.index'))->assertOk();
        $this->actingAs($this->customer)->get(route('account.settings'))->assertOk();
        $this->actingAs($this->customer)->get(route('account.addresses.index'))->assertOk();
        $this->actingAs($this->customer)->get(route('account.addresses.create'))->assertOk();
        $this->actingAs($this->customer)->get(route('account.addresses.edit', $this->address))->assertOk();
        $this->actingAs($this->customer)->get(route('orders.index'))->assertOk();
        $this->actingAs($this->customer)->get(route('orders.show', $this->order->order_code))->assertOk();

        // Customer checkout with cart
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $this->variant->id, 'quantity' => 1])->save();

        $this->actingAs($this->customer)->get(route('checkout.show'))->assertOk();
    }
}
