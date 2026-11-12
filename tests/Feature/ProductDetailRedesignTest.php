<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\ClassificationGroup;
use App\Enums\GenderGroup;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\ProductImageSourceType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderWorkflowService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductDetailRedesignTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detail_renders_grounded_copy_real_classifications_and_related_products(): void
    {
        $category = Category::factory()->create(['name' => 'Giày Nam Test', 'gender_group' => GenderGroup::Men, 'is_active' => true]);

        $activeSil = Classification::forceCreate([
            'name' => 'Sneaker Cổ Thấp',
            'slug' => 'sneaker-co-thap-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $inactiveSil = Classification::forceCreate([
            'name' => 'Phân Loại Ẩn',
            'slug' => 'phan-loai-an-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Cena Heritage Low',
            'description' => 'Mô tả thực tế về độ êm bàn chân.',
            'specifications' => ['Chất liệu upper' => 'Lưới thoáng khí', 'Đế ngoài' => 'Cao su tổng hợp'],
            'base_price' => 2_200_000,
            'promotion_price' => 1_750_000,
            'exchange_days' => 7,
            'warranty_months' => 6,
            'is_active' => true,
        ]);
        $product->classifications()->attach([$activeSil->id, $inactiveSil->id]);

        $color = ProductColor::factory()->create(['product_id' => $product->id, 'color_name' => 'Đen', 'color_key' => 'black']);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id, 'source_type' => ProductImageSourceType::ExternalUrl, 'storage_path' => null, 'external_url' => 'https://example.test/main.jpg']);
        ProductVariant::factory()->stocked(5)->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41']);

        $related = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Cena Related Shoe Alpha',
            'base_price' => 1_500_000,
            'is_active' => true,
        ]);
        $rColor = ProductColor::factory()->create(['product_id' => $related->id, 'color_name' => 'Trắng', 'color_key' => 'white']);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $rColor->id, 'source_type' => ProductImageSourceType::ExternalUrl, 'storage_path' => null, 'external_url' => 'https://example.test/rel.jpg']);
        ProductVariant::factory()->stocked(3)->create(['product_color_id' => $rColor->id, 'size_value' => '42', 'size_numeric' => '42']);

        $response = $this->get(route('shoes.show', $product->slug));
        $response->assertOk();

        // Honest, grounded content & headings
        $response->assertSee($product->name);
        $response->assertSee('Chất liệu upper');
        $response->assertSee('Lưới thoáng khí');
        $response->assertSee('Sneaker Cổ Thấp');
        $response->assertDontSee('Phân Loại Ẩn');
        $response->assertSee('2.000.000 ₫');
        $response->assertSee('Sản phẩm liên quan');
        $response->assertSee($related->name);

        // Removed filler/unverified claims & English titles
        $response->assertDontSee('Miễn phí giao hàng toàn quốc');
        $response->assertDontSee('Kiểm tra trước khi nhận hàng');
        $response->assertDontSee('CENA · TUYỂN CHỌN CHÍNH HÃNG');
        $response->assertDontSee('Shipping &amp; Returns', false);
        $response->assertDontSee('Shipping & Returns');
    }

    public function test_optional_description_and_specifications_are_omitted_when_empty(): void
    {
        $category = Category::factory()->create(['gender_group' => GenderGroup::Men, 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Cena Minimal Shoe',
            'description' => null,
            'specifications' => null,
            'is_active' => true,
        ]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id, 'source_type' => ProductImageSourceType::ExternalUrl, 'storage_path' => null, 'external_url' => 'https://example.test/min.jpg']);
        ProductVariant::factory()->stocked(2)->create(['product_color_id' => $color->id]);

        $response = $this->get(route('shoes.show', $product->slug));
        $response->assertOk();
        $response->assertDontSee('Mô tả sản phẩm');
        $response->assertDontSee('Thông số &amp; chất liệu', false);
    }

    public function test_review_eligibility_filtering_and_pagination_stay_consistent_with_visible_reviews(): void
    {
        config(['store.shipping.dataset_version' => 'factory-v1']);
        Storage::fake('private');

        $category = Category::factory()->create(['gender_group' => GenderGroup::Men, 'is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'base_price' => 1_000_000, 'promotion_price' => null, 'is_active' => true]);
        $color = ProductColor::factory()->create(['product_id' => $product->id, 'color_name' => 'Xanh dương', 'color_key' => 'blue']);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id, 'source_type' => ProductImageSourceType::ExternalUrl, 'storage_path' => null, 'external_url' => 'https://example.test/rev.jpg']);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '42', 'size_numeric' => '42']);

        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'unit_cost_amount' => 100, 'subtotal_amount' => 2000]);
        app(InventoryService::class)->receive(new InventoryChange('import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, 20, 100));

        // 1. Customer with pending (not delivered) order sees ineligible message, no "Viết đánh giá" button
        $pendingCustomer = User::factory()->customer()->create(['full_name' => 'Lê Chưa Giao']);
        $pendingOrder = $this->placeOrder($pendingCustomer, $variant, 1);

        $this->actingAs($pendingCustomer)
            ->get(route('shoes.show', $product->slug))
            ->assertOk()
            ->assertSee('Chưa đủ điều kiện viết đánh giá')
            ->assertSee($pendingOrder->order_code)
            ->assertDontSee('Viết đánh giá');

        // 2. Customer with 7 delivered + paid pairs submits 7 reviews -> triggers pagination (6 per page)
        $buyer = User::factory()->customer()->create(['full_name' => 'Trần Thanh Tùng']);
        $deliveredOrder = $this->placeOrder($buyer, $variant, 7, deliverAndPay: true);
        $units = $deliveredOrder->items->sole()->units->sortBy('unit_no')->values();

        // Before reviewing any unit: inline "Viết đánh giá" CTA is visible
        $this->actingAs($buyer)
            ->get(route('shoes.show', $product->slug))
            ->assertOk()
            ->assertSee('Viết đánh giá')
            ->assertSee('Có 7 đôi đang chờ bạn đánh giá');

        // Submit 6 visible 5-star reviews + 1 2-star review + mark 1st hidden via DB check
        foreach ($units->take(6) as $idx => $unit) {
            $this->actingAs($buyer)->post(route('account.reviews.store'), [
                'unit_id' => $unit->id,
                'rating' => $idx === 0 ? '2.0' : '5.0',
                'comment' => "Đánh giá công khai số {$idx} rất chi tiết.",
            ])->assertRedirect();
        }
        $hiddenUnit = $units[6];
        $this->actingAs($buyer)->post(route('account.reviews.store'), [
            'unit_id' => $hiddenUnit->id,
            'rating' => '1.0',
            'comment' => 'Đánh giá bị ẩn không được tính vào thống kê.',
        ])->assertRedirect();
        $hiddenReview = Review::query()->where('order_item_unit_id', $hiddenUnit->id)->firstOrFail();
        app(ReviewService::class)->setVisible($hiddenReview, false);

        // Summary reflects ONLY the 6 visible reviews (not the hidden one!)
        $this->post(route('logout'));
        $this->get(route('shoes.show', $product->slug))
            ->assertOk()
            ->assertSee('Đánh giá (6)')
            ->assertDontSee('Đánh giá bị ẩn không được tính vào thống kê')
            ->assertSee('Đăng nhập tài khoản đã mua', false);

        // Star filter: review_rating=2 shows only the 2-star review
        $this->get(route('shoes.show', ['slug' => $product->slug, 'review_rating' => 2]))
            ->assertOk()
            ->assertSee('Đánh giá công khai số 0 rất chi tiết.')
            ->assertDontSee('Đánh giá công khai số 1 rất chi tiết.');
    }

    private function placeOrder(User $customer, ProductVariant $variant, int $qty, bool $deliverAndPay = false): Order
    {
        $address = Address::factory()->create(['user_id' => $customer->id]);
        $cart = Cart::query()->where('user_id', $customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => $qty])->save();

        $checkout = app(CheckoutService::class);
        $order = $checkout->place($customer, $address->id, (string) Str::uuid(), $checkout->quote($customer, $address)['fingerprint'], null);

        if ($deliverAndPay) {
            $staff = User::factory()->staff()->create();
            $workflow = app(OrderWorkflowService::class);
            $order = $workflow->confirmAndPack($order, $staff, 'PENDING');
            $order = $workflow->ship($order, $staff, 'PACKING', 'GHN', null);
            $order = $workflow->deliver($order, $staff, 'ON_DELIVERY', $this->createProofImage('proof-'.$order->order_code.'.jpg'));
        }

        return $order->fresh(['items.units']);
    }

    private function createProofImage(string $name): File
    {
        $image = imagecreatetruecolor(240, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, 210, 220, 230));
        $temp = tmpfile();
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        fwrite($temp, ob_get_clean());

        return new File($name, $temp);
    }
}
