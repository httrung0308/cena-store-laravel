<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CouponType;
use App\Enums\GenderGroup;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminCrudStandardizationTest extends TestCase
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

    public function test_articles_support_two_way_ajax_hide_and_publish_for_admin_only(): void
    {
        $article = Article::factory()->create([
            'author_id' => $this->admin->id,
            'status' => ArticleStatus::Published,
            'published_at' => '2026-10-01 08:00:00',
        ]);

        // Staff is forbidden
        $this->actingAs($this->staff)
            ->patchJson(route('admin.articles.hide', $article))
            ->assertForbidden();
        $this->actingAs($this->staff)
            ->patchJson(route('admin.articles.publish', $article))
            ->assertForbidden();

        // Admin hides article via AJAX
        $this->actingAs($this->admin)
            ->patchJson(route('admin.articles.hide', $article))
            ->assertOk()
            ->assertJson(['status' => ArticleStatus::Hidden->value]);

        $article->refresh();
        $this->assertSame(ArticleStatus::Hidden, $article->status);

        // Index renders "Hiện bài" button for hidden article
        $this->actingAs($this->admin)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('Hiện bài');

        // Admin publishes article back via AJAX and preserves original published_at
        $this->actingAs($this->admin)
            ->patchJson(route('admin.articles.publish', $article))
            ->assertOk()
            ->assertJson(['status' => ArticleStatus::Published->value]);

        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame('2026-10-01 08:00:00', $article->published_at?->format('Y-m-d H:i:s'));
    }

    public function test_supplier_auto_generates_unique_internal_code_on_create_and_preserves_saved_code_on_rename(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.suppliers.store'), [
                'name' => 'Công ty Giày Miền Nam',
                'code' => '',
                'contact_name' => 'Anh Minh',
                'phone' => '0901234567',
                'email' => 'ncc@example.test',
                'address' => '123 Lê Lợi',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.suppliers.index'));

        $supplier = Supplier::query()->where('name', 'Công ty Giày Miền Nam')->firstOrFail();
        $this->assertMatchesRegularExpression('/^SUP-\d{4,}$/', $supplier->code);
        $originalCode = $supplier->code;

        // Updating name without changing code preserves original code
        $this->actingAs($this->staff)
            ->put(route('admin.suppliers.update', $supplier), [
                'name' => 'Công ty Giày Miền Nam Mới',
                'code' => $originalCode,
                'contact_name' => 'Anh Minh',
                'phone' => '0901234567',
                'email' => 'ncc@example.test',
                'address' => '123 Lê Lợi',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.suppliers.index'));

        $supplier->refresh();
        $this->assertSame('Công ty Giày Miền Nam Mới', $supplier->name);
        $this->assertSame($originalCode, $supplier->code);
    }

    public function test_coupon_normalizes_code_to_uppercase_and_filters_by_type_and_active(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), [
                'code' => 'cena_sale20',
                'name' => 'Giảm 20%',
                'type' => CouponType::Percentage->value,
                'value' => 20,
                'min_order_amount' => 500000,
                'max_discount_amount' => 200000,
                'per_user_limit' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::query()->where('code', 'CENA_SALE20')->first();
        $this->assertNotNull($coupon);

        // Duplicate lowercase code is rejected
        $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), [
                'code' => 'cena_sale20',
                'name' => 'Trùng mã',
                'type' => CouponType::Fixed->value,
                'value' => 50000,
                'min_order_amount' => 0,
                'per_user_limit' => 1,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('code');

        // Filter coupons by type & active
        $this->actingAs($this->admin)
            ->get(route('admin.coupons.index', ['type' => CouponType::Percentage->value, 'active' => '1']))
            ->assertOk()
            ->assertSee('CENA_SALE20');
    }

    public function test_categories_orders_stock_imports_and_reviews_filters_and_ajax_reply_work(): void
    {
        $cat = Category::factory()->create(['name' => 'Giày Chạy Bộ Nam Filter', 'gender_group' => GenderGroup::Men, 'is_active' => true]);
        $this->actingAs($this->staff)
            ->get(route('admin.categories.index', ['gender_group' => GenderGroup::Men->value, 'active' => '1']))
            ->assertOk()
            ->assertSee('Giày Chạy Bộ Nam Filter');

        $supplier = Supplier::factory()->create(['name' => 'NCC Lọc Kho', 'is_active' => true]);
        StockImport::factory()->create(['supplier_id' => $supplier->id, 'import_code' => 'PN-FILTER-01', 'created_by' => $this->admin->id]);
        $this->actingAs($this->staff)
            ->get(route('admin.stock-imports.index', ['supplier_id' => $supplier->id]))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->get(route('admin.stock-imports.index', ['supplier_id' => $supplier->id]))
            ->assertOk()
            ->assertSee('PN-FILTER-01');

        $product = Product::factory()->create(['category_id' => $cat->id, 'name' => 'Giày Variant Prefill']);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'sku' => 'SKU-PREFILL-01']);

        $this->actingAs($this->staff)
            ->get(route('admin.stock-imports.create', ['variant_id' => $variant->id]))
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->get(route('admin.stock-imports.create', ['variant_id' => $variant->id]))
            ->assertOk()
            ->assertSee('SKU-PREFILL-01');

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_code' => 'ORD-PAY-FILTER-01',
            'order_status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Unpaid,
        ]);
        $this->actingAs($this->staff)
            ->get(route('admin.orders.index', ['payment_status' => OrderPaymentStatus::Unpaid->value]))
            ->assertOk()
            ->assertSee('ORD-PAY-FILTER-01');

        $review = Review::factory()->create([
            'user_id' => $customer->id,
            'rating' => 5,
            'comment' => 'Rất êm chân và nhẹ',
            'is_visible' => true,
        ]);
        $this->actingAs($this->staff)
            ->get(route('admin.reviews.index', ['search' => 'êm chân', 'rating' => '5']))
            ->assertOk()
            ->assertSee('Rất êm chân và nhẹ');

        $this->actingAs($this->staff)
            ->patchJson(route('admin.reviews.reply', $review), ['admin_reply' => 'Cảm ơn bạn đã ủng hộ Cena!'])
            ->assertOk()
            ->assertJson(['reply_content' => 'Cảm ơn bạn đã ủng hộ Cena!']);
    }

    public function test_dashboard_shows_classifications_and_banners_cards_to_admin_only(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.classifications.index'))
            ->assertSee(route('admin.banners.index'))
            ->assertSee(route('admin.profile.show'));

        $this->actingAs($this->staff)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
