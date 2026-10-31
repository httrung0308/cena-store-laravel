<?php

namespace Tests\Feature;

use App\Enums\AfterSalesEventType;
use App\Enums\HistoryVisibility;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderUnitState;
use App\Enums\RoleSlug;
use App\Http\Resources\Admin\OrderItemResource as AdminOrderItemResource;
use App\Http\Resources\Admin\OrderResource as AdminOrderResource;
use App\Http\Resources\Customer\AfterSalesHistoryResource;
use App\Http\Resources\Customer\AfterSalesRequestResource;
use App\Http\Resources\Customer\OrderResource;
use App\Http\Resources\Customer\ProductResource;
use App\Http\Resources\Customer\ReviewResource;
use App\Models\AfterSalesAttachment;
use App\Models\AfterSalesHistory;
use App\Models\AfterSalesRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class ModelSerializationTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('customerResources')]
    public function test_customer_resources_ignore_unknown_columns_and_never_expose_sensitive_keys(string $class, string $resource, ?string $owner): void
    {
        $model = $class::factory()->create()->refresh();
        $actor = $this->owner($model, $owner);
        $model->setAttribute('unlisted_private_field', 'UNLISTED-PRIVATE-COLUMN');

        $body = $this->body(new $resource($model), $actor);

        $this->assertSame($model->id, $body['id']);
        $this->assertSafeCustomerData($body);
        $this->assertStringNotContainsString('UNLISTED-PRIVATE-COLUMN', json_encode($body, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('ownedResources')]
    public function test_owned_resources_return_404_for_another_user_or_guest(string $class, string $resource): void
    {
        $model = $class::factory()->create()->refresh();
        $other = User::factory()->create();

        $this->assertDenied(fn () => $this->body(new $resource($model), $other), 404);
        $this->assertDenied(fn () => $this->body(new $resource($model), null), 404);
    }

    #[DataProvider('adminResources')]
    public function test_admin_resources_whitelist_attributes_without_serializing_credentials_or_proof_paths(string $class, string $resource): void
    {
        $model = $class::factory()->create()->refresh();
        $admin = User::factory()->administrator()->create();
        $model->setAttribute('unlisted_private_field', 'UNLISTED-PRIVATE-COLUMN');

        $body = $this->body(new $resource($model), $admin);

        $this->assertSame($model->id, $body['id']);
        foreach (['password', 'remember_token', 'otp_hash', 'challenge_token', 'session_binding_hash', 'destination_ciphertext', 'proof_path', 'confirmation_evidence_path', 'customer_reported_evidence_path', 'evidence_path', 'default_owner_id', 'thumbnail_color_id', 'pending_order_id', 'active_unit_id', 'unlisted_private_field'] as $field) {
            $this->assertArrayNotHasKey($field, $body);
        }
        $this->assertStringNotContainsString('UNLISTED-PRIVATE-COLUMN', json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function test_admin_financial_data_requires_an_active_current_admin_role(): void
    {
        $order = Order::factory()->create();
        $customer = $order->user;
        $staff = User::factory()->create();
        $role = Role::factory()->create(['slug' => RoleSlug::Staff, 'name' => 'Staff']);
        UserRole::factory()->create(['user_id' => $staff->id, 'role_id' => $role->id, 'assigned_at' => now()]);
        $inactive = User::factory()->administrator()->inactive()->create();
        $deleted = User::factory()->administrator()->create();
        $deleted->delete();

        foreach ([$customer, $staff, $inactive, $deleted, null] as $actor) {
            $this->assertDenied(fn () => $this->body(new AdminOrderResource($order), $actor), 403);
        }
        $admin = User::factory()->administrator()->create();
        $admin->load('roles');
        $admin->roles()->detach();
        $this->assertDenied(fn () => $this->body(new AdminOrderResource($order), $admin), 403);
    }

    public function test_owner_order_resource_keeps_purchase_snapshots_after_the_entire_catalog_chain_is_hidden(): void
    {
        $unit = $this->deliveredUnit();
        $item = $unit->item;
        $snapshot = $item->product_name_snapshot;
        $variant = $item->variant;
        $color = $variant->color;
        $product = $color->product;
        $category = $product->category;
        $product->forceFill(['name' => 'RENAMED-LIVE-PRODUCT', 'is_active' => false])->save();
        $variant->forceFill(['is_active' => false])->save();
        $variant->delete();
        $color->delete();
        $product->delete();
        $category->delete();
        $order = Order::with(['items.units', 'items.variant.historicalColor.historicalProduct.historicalCategory'])->findOrFail($item->order_id);

        $body = $this->body(new OrderResource($order), $order->user);

        $this->assertSame($snapshot, $body['items'][0]['product_name_snapshot']);
        $this->assertSame('DELIVERED', $body['order_status']);
        $this->assertSame('PAID', $body['payment_status']);
        $this->assertIsInt($body['total_amount']);
        $this->assertSame('OWNED_BY_CUSTOMER', $body['items'][0]['units'][0]['state']);
        $this->assertTrue($order->items[0]->variant->trashed());
        $this->assertTrue($order->items[0]->variant->historicalColor->historicalProduct->historicalCategory->trashed());
        $this->assertSafeCustomerData($body);
        $this->assertStringNotContainsString('RENAMED-LIVE-PRODUCT', json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function test_rma_filters_internal_history_and_private_attachments_even_when_every_relation_is_loaded(): void
    {
        $unit = $this->deliveredUnit();
        $rma = AfterSalesRequest::factory()->for($unit, 'unit')->create(['staff_note' => 'INTERNAL-STAFF-SECRET', 'returned_cogs_amount' => 25, 'repair_expense_amount' => 7]);
        AfterSalesHistory::factory()->for($rma, 'afterSalesRequest')->create(['event_type' => AfterSalesEventType::Note, 'visibility' => HistoryVisibility::Customer, 'note' => 'Customer-safe progress update.']);
        $internal = AfterSalesHistory::factory()->for($rma, 'afterSalesRequest')->create(['event_type' => AfterSalesEventType::Note, 'visibility' => HistoryVisibility::Internal, 'note' => 'INTERNAL-HISTORY-SECRET']);
        AfterSalesAttachment::factory()->for($rma, 'afterSalesRequest')->create(['path' => 'private/PRIVATE-RMA-PROOF.jpg']);
        $rma->load(['unit.item.order', 'sourceVariant.historicalColor.historicalProduct', 'histories', 'attachments']);
        $owner = $unit->item->order->user;

        $body = $this->body(new AfterSalesRequestResource($rma), $owner);

        $this->assertCount(1, $body['histories']);
        $this->assertSame('Customer-safe progress update.', $body['histories'][0]['note']);
        $this->assertSame(['id', 'mime_type', 'size_bytes', 'created_at'], array_keys($body['attachments'][0]));
        $this->assertSafeCustomerData($body);
        foreach (['INTERNAL-STAFF-SECRET', 'INTERNAL-HISTORY-SECRET', 'PRIVATE-RMA-PROOF'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($body, JSON_THROW_ON_ERROR));
        }
        $this->assertDenied(fn () => $this->body(new AfterSalesHistoryResource($internal), $owner), 404);
    }

    public function test_admin_resource_can_return_cost_but_customer_resource_remains_safe_for_an_admin_personal_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $unit = $this->deliveredUnit($admin);
        $adminBody = $this->body(new AdminOrderItemResource($unit->item), $admin);
        $customerBody = $this->body(new OrderResource($unit->item->order->load('items.units')), $admin);

        $this->assertSame('25.1234', $adminBody['cost_unit_snapshot']);
        $this->assertSame(25, $adminBody['cogs_amount']);
        $this->assertSafeCustomerData($customerBody);
    }

    public function test_product_resource_serialization_does_not_add_queries_to_an_eager_loaded_collection(): void
    {
        $product = Product::factory()->create();
        $color = ProductColor::factory()->for($product)->create();
        ProductImage::factory()->for($color, 'color')->thumbnail()->create();
        ProductVariant::factory()->for($color, 'color')->stocked(3, '123.4567')->create(['reserved_qty' => 1]);
        $loaded = Product::with(['colors.images', 'colors.variants', 'colors.thumbnail'])->findOrFail($product->id);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $body = $this->body(new ProductResource($loaded), null);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries);
        $this->assertSame(2, $body['colors'][0]['variants'][0]['available_qty']);
        $this->assertStringContainsString('/storage/products/', $body['colors'][0]['thumbnail']['url']);
        $this->assertSafeCustomerData($body);
    }

    public function test_hidden_review_and_inactive_owner_do_not_get_customer_data(): void
    {
        $review = Review::factory()->create(['is_visible' => false]);
        $this->assertDenied(fn () => $this->body(new ReviewResource($review), null), 404);
        $order = Order::factory()->create();
        $owner = $order->user;
        $owner->forceFill(['is_active' => false])->save();
        $this->assertDenied(fn () => $this->body(new OrderResource($order), $owner), 404);
    }

    public function test_hidden_review_is_omitted_without_breaking_the_owners_order_document(): void
    {
        $unit = $this->deliveredUnit();
        Review::factory()->for($unit, 'unit')->create(['is_visible' => false, 'comment' => 'HIDDEN-REVIEW-CONTENT']);
        $order = $unit->item->order->load('items.units.review');

        $body = $this->body(new OrderResource($order), $order->user);

        $this->assertSame($order->id, $body['id']);
        $this->assertNull($body['items'][0]['units'][0]['review']);
        $this->assertStringNotContainsString('HIDDEN-REVIEW-CONTENT', json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, array{class-string, class-string, ?string}> */
    public static function customerResources(): array
    {
        $result = [];
        foreach (self::contract()['customer'] as $table => $c) {
            $result[$table] = [$c['class'], $c['resource'], $c['owner']];
        }

        return $result;
    }

    /** @return array<string, array{class-string, class-string}> */
    public static function ownedResources(): array
    {
        $result = [];
        foreach (self::contract()['customer'] as $table => $c) {
            if ($c['owner'] !== null) {
                $result[$table] = [$c['class'], $c['resource']];
            }
        }

        return $result;
    }

    /** @return array<string, array{class-string, class-string}> */
    public static function adminResources(): array
    {
        return self::contract()['admin'];
    }

    private function owner(object $model, ?string $path): ?User
    {
        if ($path === null) {
            return null;
        }
        if ($path === 'self') {
            return $model;
        }
        $ids = explode('.', $path);
        $column = array_pop($ids);
        foreach ($ids as $relation) {
            $model = $model->{$relation};
        }

        return User::findOrFail($model->{$column});
    }

    /** @return array<string, mixed> */
    private function body(JsonResource $resource, ?User $actor): array
    {
        $request = Request::create('/resource-contract-test', 'GET');
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $actor);

        return $resource->response($request)->getData(true)['data'];
    }

    private function assertDenied(callable $operation, int $status): void
    {
        try {
            $operation();
            $this->fail('Unauthorized serialization was accepted.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    /** @param array<string|int, mixed> $data */
    private function assertSafeCustomerData(array $data): void
    {
        $forbidden = ['password', 'remember_token', 'auth_version', 'otp_hash', 'challenge_token', 'session_binding_hash', 'destination_ciphertext', 'staff_note', 'proof_path', 'evidence_path', 'confirmation_evidence_path', 'customer_reported_evidence_path', 'path', 'disk', 'sha256', 'storage_path', 'default_owner_id', 'thumbnail_color_id', 'pending_order_id', 'active_unit_id', 'guest_token_hash'];
        foreach ($data as $key => $value) {
            $this->assertNotContains($key, $forbidden);
            $this->assertDoesNotMatchRegularExpression('/cost|cogs/i', (string) $key);
            if (is_array($value)) {
                $this->assertSafeCustomerData($value);
            }
        }
    }

    private function deliveredUnit(?User $owner = null): OrderItemUnit
    {
        $this->freezeTime();
        $order = Order::factory()->create(['user_id' => ($owner ?? User::factory()->create())->id, 'order_status' => OrderStatus::Delivered, 'payment_status' => OrderPaymentStatus::Paid, 'paid_amount' => 100, 'delivered_at' => now()]);
        $item = OrderItem::factory()->for($order)->create(['cost_unit_snapshot' => '25.1234', 'cogs_amount' => 25]);

        return OrderItemUnit::factory()->for($item, 'item')->create(['state' => OrderUnitState::OwnedByCustomer, 'original_cogs_amount' => 25, 'current_cogs_amount' => 25]);
    }

    /** @return array<string, mixed> */
    private static function contract(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/resource-contract.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
