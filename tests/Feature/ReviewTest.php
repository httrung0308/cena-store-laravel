<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    private User $staff;

    private Product $product;

    private ProductVariant $shoe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1']);
        Storage::fake('private');
        $this->customer = User::factory()->customer()->create(['full_name' => 'Nguyễn Văn Minh']);
        $this->staff = User::factory()->staff()->create();
        $this->product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => null]);
        $color = ProductColor::factory()->create(['product_id' => $this->product->id, 'color_name' => 'Đen']);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id]);
        $this->shoe = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '42', 'size_numeric' => '42', 'price_override' => null]);
        $line = StockImportItem::factory()->create(['variant_id' => $this->shoe->id, 'quantity' => 10, 'unit_cost_amount' => 100, 'subtotal_amount' => 1000]);
        app(InventoryService::class)->receive(new InventoryChange('import:'.$line->stock_import_id, $this->shoe->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, 10, 100));
    }

    public function test_owner_reviews_each_delivered_pair_once_and_the_product_average_follows(): void
    {
        $order = $this->deliveredOrder(quantity: 2);
        [$first, $second] = $order->items->sole()->units->sortBy('unit_no')->values()->all();

        $this->actingAs($this->customer)->get(route('orders.show', $order->order_code))->assertSee('Viết đánh giá');
        $this->post(route('account.reviews.store'), ['unit_id' => $first->id, 'rating' => '4', 'comment' => 'Giày êm, <b>đúng size</b> lắm'])->assertRedirect();
        $this->post(route('account.reviews.store'), ['unit_id' => $second->id, 'rating' => '5', 'comment' => 'Đôi thứ hai cũng rất ổn áp'])->assertRedirect();
        $this->assertSame(['4.50', 2], [$this->product->fresh()->rating_avg, $this->product->fresh()->reviews_count]);

        $this->post(route('account.reviews.store'), ['unit_id' => $first->id, 'rating' => '2.5', 'comment' => 'Sau một tuần thì hơi đau chân'])->assertRedirect();
        $this->assertSame(2, Review::query()->count(), 'Editing keeps one review per pair.');
        $this->assertSame('3.75', $this->product->fresh()->rating_avg);

        $this->post(route('logout'));
        $this->get(route('shoes.show', $this->product->slug))->assertOk()
            ->assertSee('Đánh giá (2)')->assertSee('Minh N.')->assertDontSee('Nguyễn Văn Minh')
            ->assertSee('Đôi thứ hai cũng rất ổn áp')->assertSee('Đã mua: Đen · Size 42');
    }

    public function test_only_the_owner_of_a_delivered_paid_order_can_review(): void
    {
        $pending = $this->placeOrder(1);
        $unit = $pending->items->sole()->units->sole();
        $this->actingAs($this->customer)->from(route('orders.show', $pending->order_code))
            ->post(route('account.reviews.store'), ['unit_id' => $unit->id, 'rating' => '5', 'comment' => 'Chưa nhận hàng mà đánh giá'])
            ->assertSessionHasErrors('review');
        $this->get(route('orders.show', $pending->order_code))->assertDontSee('Viết đánh giá');

        $delivered = $this->deliveredOrder(1);
        $deliveredUnit = $delivered->items->sole()->units->sole();
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('account.reviews.store'), ['unit_id' => $deliveredUnit->id, 'rating' => '5', 'comment' => 'Không phải đơn của tôi'])->assertNotFound();
        $this->actingAs($this->customer)->post(route('account.reviews.store'), ['unit_id' => $deliveredUnit->id, 'rating' => '4.3', 'comment' => 'Số sao không hợp lệ'])->assertSessionHasErrors('rating');
        $this->post(route('account.reviews.store'), ['unit_id' => $deliveredUnit->id, 'rating' => '4', 'comment' => 'ngắn'])->assertSessionHasErrors('comment');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_staff_reply_and_hiding_recompute_the_rating_without_editing_the_customer(): void
    {
        $order = $this->deliveredOrder(1);
        $unit = $order->items->sole()->units->sole();
        $this->actingAs($this->customer)->post(route('account.reviews.store'), ['unit_id' => $unit->id, 'rating' => '1', 'comment' => 'Giao chậm, hộp móp méo']);
        $review = Review::query()->sole();

        $this->actingAs($this->customer)->get(route('admin.reviews.index'))->assertForbidden();
        $this->actingAs($this->staff)->patch(route('admin.reviews.reply', $review), ['admin_reply' => 'Xin lỗi bạn, cửa hàng đã nhắc đơn vị vận chuyển.', 'comment' => 'đổi lời khách'])->assertRedirect();
        $this->patch(route('admin.reviews.visibility', $review), ['visible' => 0])->assertRedirect();

        $review->refresh();
        $this->assertSame('Giao chậm, hộp móp méo', $review->comment);
        $this->assertSame($this->staff->id, $review->replied_by);
        $this->assertSame(['0.00', 0], [$this->product->fresh()->rating_avg, $this->product->fresh()->reviews_count]);
        $this->get(route('shoes.show', $this->product->slug))->assertDontSee('Giao chậm, hộp móp méo');

        $this->patch(route('admin.reviews.visibility', $review), ['visible' => 1]);
        $this->assertSame(['1.00', 1], [$this->product->fresh()->rating_avg, $this->product->fresh()->reviews_count]);
        $this->get(route('admin.reviews.index'))->assertOk()->assertSee('Giao chậm, hộp móp méo')->assertSee('Xin lỗi bạn');
        $this->get(route('shoes.show', $this->product->slug))->assertSee('Phản hồi từ cửa hàng');
    }

    public function test_customer_review_state_groups_identical_units_and_distinguishes_orders_colors_sizes(): void
    {
        // Same order with 2 identical units (same color & size) -> grouped into 1 pending choice with quantity = 2
        $firstOrder = $this->deliveredOrder(2);
        $firstUnits = $firstOrder->items->sole()->units->sortBy('unit_no')->values();

        // Second order with a different color & size of the same product
        $whiteColor = ProductColor::factory()->create(['product_id' => $this->product->id, 'color_name' => 'Trắng']);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $whiteColor->id]);
        $whiteShoe = ProductVariant::factory()->create(['product_color_id' => $whiteColor->id, 'size_value' => '41', 'size_numeric' => '41', 'price_override' => null]);
        $line = StockImportItem::factory()->create(['variant_id' => $whiteShoe->id, 'quantity' => 5, 'unit_cost_amount' => 100, 'subtotal_amount' => 500]);
        app(InventoryService::class)->receive(new InventoryChange('import:'.$line->stock_import_id, $whiteShoe->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, 5, 100));

        $secondOrder = $this->deliveredOrderForVariant($whiteShoe, 1);

        $state = app(ReviewService::class)->customerReviewState($this->customer, $this->product->id);

        $this->assertSame('can_review', $state['status']);
        $this->assertCount(2, $state['pending_units']);

        // Ordered by order_id DESC: secondOrder first, then firstOrder
        $this->assertSame($secondOrder->order_code, $state['pending_units'][0]['order_code']);
        $this->assertSame('Trắng', $state['pending_units'][0]['color_name']);
        $this->assertSame('41', $state['pending_units'][0]['size_value']);
        $this->assertSame(1, $state['pending_units'][0]['quantity']);
        $this->assertNotNull($state['pending_units'][0]['delivered_at']);
        $this->assertNotEmpty($state['pending_units'][0]['thumbnail_url']);

        $this->assertSame($firstUnits[0]->id, $state['pending_units'][1]['unit_id']);
        $this->assertSame($firstOrder->order_code, $state['pending_units'][1]['order_code']);
        $this->assertSame('Đen', $state['pending_units'][1]['color_name']);
        $this->assertSame('42', $state['pending_units'][1]['size_value']);
        $this->assertSame(2, $state['pending_units'][1]['quantity']);
        $this->assertNotNull($state['pending_units'][1]['delivered_at']);
        $this->assertNotEmpty($state['pending_units'][1]['thumbnail_url']);
    }

    public function test_catalog_show_renders_star_rating_picker_and_single_empty_state_without_rating_select(): void
    {
        $order = $this->deliveredOrder(2);
        $firstUnit = $order->items->sole()->units->sortBy('unit_no')->first();

        $response = $this->actingAs($this->customer)->get(route('shoes.show', $this->product->slug));
        $response->assertOk()
            ->assertSee('starRating(')
            ->assertSee('name="rating"', false)
            ->assertSee('name="unit_id" value="'.$firstUnit->id.'"', false)
            ->assertSee('(2 đôi)')
            ->assertDontSee('review-rating-select')
            ->assertDontSee('edit-review-rating')
            ->assertDontSee('review-unit-select')
            ->assertDontSee('Chưa có đánh giá công khai');

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Chưa có đánh giá nào.'));
    }

    public function test_reviewing_grouped_identical_pairs_once_completes_form_and_clears_notification_review_cta(): void
    {
        $order = $this->deliveredOrder(2);
        $firstUnit = $order->items->sole()->units->sortBy('unit_no')->first();

        $this->actingAs($this->customer)
            ->getJson(route('notifications.api.recent'))
            ->assertOk()
            ->assertJsonPath('data.notifications.0.review_cta.label', 'Đánh giá sản phẩm');

        $this->actingAs($this->customer)
            ->post(route('account.reviews.store'), [
                'unit_id' => $firstUnit->id,
                'rating' => '4.5',
                'comment' => 'Mua hai đôi cùng cỡ đều vừa chân và êm.',
            ])
            ->assertRedirect();

        $this->assertSame(1, Review::query()->count());
        $state = app(ReviewService::class)->customerReviewState($this->customer, $this->product->id);
        $this->assertSame('already_reviewed', $state['status']);
        $this->assertSame([], $state['pending_units']);

        $this->actingAs($this->customer)
            ->get(route('shoes.show', $this->product->slug))
            ->assertOk()
            ->assertSee('Sửa đánh giá')
            ->assertDontSee('Bạn có 1 lựa chọn mua hàng sẵn sàng để đánh giá.');

        $this->actingAs($this->customer)
            ->getJson(route('notifications.api.recent'))
            ->assertOk()
            ->assertJsonPath('data.notifications.0.review_cta', null);

        $this->actingAs($this->customer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Đơn hàng đã giao thành công')
            ->assertDontSee('Đánh giá sản phẩm');
    }

    private function deliveredOrder(int $quantity): Order
    {
        return $this->deliveredOrderForVariant($this->shoe, $quantity);
    }

    private function deliveredOrderForVariant(ProductVariant $variant, int $quantity): Order
    {
        $order = $this->placeOrderForVariant($variant, $quantity);
        $workflow = app(OrderWorkflowService::class);
        $order = $workflow->confirmAndPack($order, $this->staff, 'PENDING');
        $order = $workflow->ship($order, $this->staff, 'PACKING', 'GHN', null);
        $proof = $this->createProofImage('proof-'.$order->order_code.'.jpg', $order->order_code);
        $order = $workflow->deliver($order, $this->staff, 'ON_DELIVERY', $proof);

        return $order->fresh(['items.units']);
    }

    private function createProofImage(string $name, string $seed): UploadedFile
    {
        $image = imagecreatetruecolor(240, 60);
        $intSeed = crc32($seed);
        $bgColor = imagecolorallocate($image, 200 + abs($intSeed % 50), 200 + abs(($intSeed >> 3) % 50), 200 + abs(($intSeed >> 6) % 50));
        imagefill($image, 0, 0, $bgColor);
        $textColor = imagecolorallocate($image, 20, 20, 20);
        imagestring($image, 3, 10, 20, $seed, $textColor);
        $temp = tmpfile();
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        fwrite($temp, ob_get_clean());

        return new File($name, $temp);
    }

    private function placeOrder(int $quantity): Order
    {
        return $this->placeOrderForVariant($this->shoe, $quantity);
    }

    private function placeOrderForVariant(ProductVariant $variant, int $quantity): Order
    {
        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        CartItem::query()->where('cart_id', $cart->id)->delete();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => $quantity])->save();
        $service = app(CheckoutService::class);

        return $service->place($this->customer, $address->id, (string) Str::uuid(), $service->quote($this->customer, $address)['fingerprint'], null)->fresh(['items.units']);
    }
}
