<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderUnitState;
use App\Enums\SalesLedgerType;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\SalesLedgerEntry;
use App\Models\ShipmentReturnEvent;
use App\Models\ShipmentReturnItem;
use App\Models\StockImportItem;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\ShipmentReturnService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FailedDeliveryReturnTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    private User $customer;

    private ProductVariant $shoe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
        $this->customer = User::factory()->customer()->create();
        $this->shoe = $this->variant(stock: 8, cost: 300_000);
    }

    public function test_staff_can_receive_partial_returns_and_duplicate_unit_requests_once_into_quarantine(): void
    {
        $order = $this->failedOrder(quantity: 2);
        $units = $order->items->sole()->units->sortBy('unit_no')->values();
        $this->actingAs($this->staff);

        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $units[0]->id, 'note' => 'Đã nhận đủ hộp, tem và đôi giày đầu tiên.'])->assertRedirect();
        $this->assertSame(ShipmentStatus::Returning, $order->shipment->fresh()->status);
        $this->assertSame([6, 0, 1], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty, $this->shoe->fresh()->quarantine_qty]);

        $firstReturn = ShipmentReturnItem::query()->where('order_item_unit_id', $units[0]->id)->sole();
        try {
            DB::transaction(fn () => app(InventoryService::class)->receiveQuarantine(new InventoryChange(
                'alternate-return-receipt:'.$firstReturn->id,
                $this->shoe->id,
                InventoryAction::ShipmentReturnReceived,
                InventoryReferenceType::ShipmentReturnItem,
                $firstReturn->id,
                1,
                $firstReturn->cost_basis,
                $this->staff->id,
                'Yêu cầu nhận nội bộ lặp bằng khóa khác.',
            )));
            $this->fail('A different operation key must not receive the same unit twice.');
        } catch (DomainException $exception) {
            $this->assertSame(ErrorCode::StateConflict, $exception->errorCode);
        }
        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $units[0]->id, 'note' => 'Yêu cầu lặp cho cùng đôi bằng một nội dung khác.'])->assertRedirect();
        $this->assertSame($firstReturn->id, ShipmentReturnItem::query()->where('order_item_unit_id', $units[0]->id)->sole()->id);
        $this->assertSame(1, InventoryMovement::query()->where('reference_type', InventoryReferenceType::ShipmentReturnItem)->where('reference_id', $firstReturn->id)->where('action', InventoryAction::ShipmentReturnReceived)->count());

        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $units[1]->id, 'note' => 'Đã nhận đủ hộp, tem và đôi giày thứ hai.'])->assertRedirect();
        $this->assertSame(ShipmentStatus::ReturnReceived, $order->shipment->fresh()->status);
        $this->assertSame([6, 0, 2], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty, $this->shoe->fresh()->quarantine_qty]);
        $this->assertSame(OrderUnitState::ReturnedToStore, $units[0]->fresh()->state);
        $this->assertSame(OrderUnitState::ReturnedToStore, $units[1]->fresh()->state);
        $this->assertSame(2, ShipmentReturnEvent::query()->whereHas('returnItem', fn ($query) => $query->where('shipment_id', $order->shipment->id))->where('event_type', ShipmentReturnEvent::RECEIVED)->count());
    }

    public function test_unit_from_another_order_cannot_be_received_on_this_shipment(): void
    {
        $first = $this->failedOrder(quantity: 1);
        $second = $this->failedOrder(quantity: 1);
        $foreignUnit = $second->items->sole()->units->sole();

        $this->actingAs($this->staff)->from(route('admin.shipment-returns.show', $first->shipment))
            ->post(route('admin.shipment-returns.receive', $first->shipment), ['unit_id' => $foreignUnit->id, 'note' => 'Đôi này thuộc một đơn hàng và kiện khác.'])
            ->assertRedirect()->assertSessionHasErrors();

        $this->assertDatabaseMissing('shipment_return_items', ['order_item_unit_id' => $foreignUnit->id]);
        $this->assertSame(ShipmentStatus::Returning, $first->shipment->fresh()->status);
        $this->assertSame(OrderUnitState::Outbound, $foreignUnit->fresh()->state);
        $this->assertSame(0, $this->shoe->fresh()->quarantine_qty);
    }

    public function test_missing_sku_damage_and_evidence_incidents_are_audited_without_inventing_stock(): void
    {
        $order = $this->failedOrder(quantity: 3);
        $units = $order->items->sole()->units->sortBy('unit_no')->values();
        $this->actingAs($this->staff);
        $missingNote = 'Trong kiện chỉ có hai đôi; đôi này chưa quay về.';
        $this->post(route('admin.shipment-returns.incidents', $order->shipment), ['unit_id' => $units[0]->id, 'incident_type' => ShipmentReturnEvent::MISSING_UNIT, 'note' => $missingNote])->assertRedirect();
        $this->post(route('admin.shipment-returns.incidents', $order->shipment), ['unit_id' => $units[0]->id, 'incident_type' => ShipmentReturnEvent::MISSING_UNIT, 'note' => 'Yêu cầu lặp lại không ghi thêm incident.'])->assertRedirect();
        $this->post(route('admin.shipment-returns.incidents', $order->shipment), ['unit_id' => $units[1]->id, 'incident_type' => ShipmentReturnEvent::SKU_MISMATCH, 'note' => 'Tem ngoài kiện ghi SKU khác với SKU trong đơn.'])->assertRedirect();
        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $units[2]->id, 'note' => 'Đã nhận đôi thứ ba và đưa vào kho cách ly để kiểm tra.'])->assertRedirect();
        $returnItem = ShipmentReturnItem::query()->where('order_item_unit_id', $units[2]->id)->sole();
        $this->post(route('admin.shipment-returns.incidents', $order->shipment), ['unit_id' => $units[2]->id, 'incident_type' => ShipmentReturnEvent::DAMAGED_UNIT, 'note' => 'Mũi giày móp sâu, có vết rách trên thân giày.'])->assertRedirect();
        $this->post(route('admin.shipment-returns.incidents', $order->shipment), ['unit_id' => $units[2]->id, 'incident_type' => ShipmentReturnEvent::NO_EVIDENCE, 'note' => 'Kiện không có biên bản bàn giao hoặc ảnh xác nhận.'])->assertRedirect();

        $this->assertSame(1, ShipmentReturnEvent::query()->where('shipment_id', $order->shipment->id)->where('order_item_unit_id', $units[0]->id)->where('event_type', ShipmentReturnEvent::MISSING_UNIT)->count());
        $this->assertDatabaseHas('shipment_return_events', ['shipment_id' => $order->shipment->id, 'order_item_unit_id' => $units[1]->id, 'event_type' => ShipmentReturnEvent::SKU_MISMATCH]);
        $this->assertDatabaseHas('shipment_return_events', ['shipment_return_item_id' => null, 'order_item_unit_id' => $units[2]->id, 'event_type' => ShipmentReturnEvent::DAMAGED_UNIT]);
        $this->assertDatabaseHas('shipment_return_events', ['shipment_return_item_id' => null, 'order_item_unit_id' => $units[2]->id, 'event_type' => ShipmentReturnEvent::NO_EVIDENCE]);
        $this->assertSame([5, 0, 1], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty, $this->shoe->fresh()->quarantine_qty]);
        $this->assertSame(OrderUnitState::Outbound, $units[0]->fresh()->state);
        $this->assertSame(OrderUnitState::Outbound, $units[1]->fresh()->state);
        $this->assertSame(OrderUnitState::ReturnedToStore, $units[2]->fresh()->state);
        $this->get(route('admin.shipment-returns.show', $order->shipment))->assertOk()->assertSee($missingNote)->assertSee('SKU không khớp')->assertSee('Thiếu chứng cứ');
        $this->assertSame($returnItem->id, ShipmentReturnItem::query()->where('order_item_unit_id', $units[2]->id)->sole()->id);
    }

    public function test_restock_uses_the_frozen_outgoing_cost_after_average_cost_changes(): void
    {
        $order = $this->failedOrder(quantity: 8);
        $unit = $order->items->sole()->units->sole();
        $this->receive($this->shoe, 1, 900_000);
        $this->assertSame('900000.0000', $this->shoe->fresh()->average_cost);
        $this->actingAs($this->staff);
        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $unit->id, 'note' => 'Đã nhận đủ hộp và tem, cần kiểm tra ngoại quan.'])->assertRedirect();
        $returnItem = ShipmentReturnItem::query()->where('order_item_unit_id', $unit->id)->sole();

        $this->post(route('admin.shipment-return-items.restock', $returnItem), ['note' => 'Kiểm tra đạt: giày và hộp nguyên vẹn, đủ phụ kiện để bán lại.'])->assertRedirect();
        $movement = InventoryMovement::query()->where('reference_type', InventoryReferenceType::ShipmentReturnItem)->where('reference_id', $returnItem->id)->where('action', InventoryAction::ReturnRestocked)->sole();
        $this->assertSame('300000.0000', $movement->unit_cost_basis);
        $this->assertSame([2, 0, 0, '600000.0000'], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty, $this->shoe->fresh()->quarantine_qty, $this->shoe->fresh()->average_cost]);
        $this->post(route('admin.shipment-return-items.restock', $returnItem), ['note' => 'Kiểm tra đạt: giày và hộp nguyên vẹn, đủ phụ kiện để bán lại.'])->assertRedirect();
        $this->assertSame(1, InventoryMovement::query()->where('reference_id', $returnItem->id)->where('action', InventoryAction::ReturnRestocked)->count());
    }

    public function test_write_off_requires_staff_proposal_and_admin_approval_and_records_one_loss(): void
    {
        $order = $this->failedOrder(quantity: 1);
        $unit = $order->items->sole()->units->sole();
        $this->actingAs($this->staff);
        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $unit->id, 'note' => 'Đã nhận lại và giữ riêng để đánh giá hư hỏng.'])->assertRedirect();
        $returnItem = ShipmentReturnItem::query()->where('order_item_unit_id', $unit->id)->sole();
        $proposalNote = 'Đế bị nứt và hộp thấm nước, không đủ điều kiện đưa bán.';
        $this->actingAs($this->admin)->from(route('admin.shipment-returns.show', $order->shipment))
            ->post(route('admin.shipment-return-items.write-off-proposal', $returnItem), ['note' => 'Admin không được thay Staff gửi đề xuất ghi hỏng.'])
            ->assertForbidden();
        $this->actingAs($this->staff);
        $this->post(route('admin.shipment-return-items.write-off-proposal', $returnItem), ['note' => $proposalNote])->assertRedirect();
        $this->assertDatabaseHas('shipment_return_events', ['shipment_return_item_id' => $returnItem->id, 'event_type' => ShipmentReturnEvent::WRITE_OFF_PROPOSED, 'note' => $proposalNote]);
        $this->post(route('admin.shipment-return-items.write-off-approval', $returnItem), ['note' => 'Đã xem chứng cứ và đồng ý ghi nhận đôi hàng này là tổn thất.'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.shipment-return-items.write-off-approval', $returnItem), ['note' => 'Đã xem chứng cứ và đồng ý ghi nhận đôi hàng này là tổn thất.'])->assertRedirect();
        $this->assertSame([7, 0, 0], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty, $this->shoe->fresh()->quarantine_qty]);
        $this->assertSame(1, InventoryMovement::query()->where('reference_id', $returnItem->id)->where('action', InventoryAction::ReturnWrittenOff)->count());
        $loss = SalesLedgerEntry::query()->where('type', SalesLedgerType::InventoryLoss)->where('inventory_movement_id', InventoryMovement::query()->where('reference_id', $returnItem->id)->where('action', InventoryAction::ReturnWrittenOff)->value('id'))->sole();
        $this->assertSame(300_000, $loss->expense_delta);
        $this->assertSame(0, SalesLedgerEntry::query()->where('order_id', $order->id)->whereIn('type', [SalesLedgerType::SaleRecognized, SalesLedgerType::SaleReversed])->count());

        $this->post(route('admin.shipment-return-items.write-off-approval', $returnItem), ['note' => 'Đã xem chứng cứ và đồng ý ghi nhận đôi hàng này là tổn thất.'])->assertRedirect();
        $this->assertSame(1, SalesLedgerEntry::query()->where('type', SalesLedgerType::InventoryLoss)->where('order_id', $order->id)->count());
        $this->assertSame(1, ShipmentReturnEvent::query()->where('shipment_return_item_id', $returnItem->id)->where('event_type', ShipmentReturnEvent::WRITE_OFF_APPROVED)->count());
    }

    public function test_any_error_after_receipt_movement_rolls_back_all_return_writes(): void
    {
        $order = $this->failedOrder(quantity: 1);
        $unit = $order->items->sole()->units->sole();
        $actor = $this->staff;
        $failEventInsert = true;
        DB::listen(function (QueryExecuted $query) use (&$failEventInsert): void {
            if ($failEventInsert && str_starts_with(strtolower(ltrim($query->sql)), 'insert into `shipment_return_events`')) {
                $failEventInsert = false;
                throw new RuntimeException('Injected event persistence failure.');
            }
        });

        try {
            app(ShipmentReturnService::class)->receiveUnit($order->shipment, $unit->id, $actor, 'Đã nhận đủ hộp và phụ kiện của đôi hàng.');
            $this->fail('The injected persistence failure should abort receipt.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected event persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('shipment_return_items', ['order_item_unit_id' => $unit->id]);
        $this->assertDatabaseMissing('inventory_movements', ['reference_type' => InventoryReferenceType::ShipmentReturnItem->value]);
        $this->assertSame(OrderUnitState::Outbound, $unit->fresh()->state);
        $this->assertSame([7, 0, 0], [$this->shoe->fresh()->stock_qty, $this->shoe->fresh()->reserved_qty, $this->shoe->fresh()->quarantine_qty]);
    }

    public function test_only_back_office_users_can_open_return_pages_and_admin_cannot_make_staff_proposal(): void
    {
        $order = $this->failedOrder(quantity: 1);
        $this->actingAs($this->customer)->get(route('admin.shipment-returns.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.shipment-returns.show', $order->shipment))->assertOk();
        $this->post(route('admin.shipment-returns.receive', $order->shipment), ['unit_id' => $order->items->sole()->units->sole()->id, 'note' => 'Đã nhận đủ hộp và phụ kiện của đôi hàng.'])->assertForbidden();
        $this->app['auth']->guard()->logout();
        $this->get(route('admin.shipment-returns.index'))->assertRedirect(route('login'));
    }

    private function failedOrder(int $quantity): Order
    {
        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $cart = Cart::query()->where('user_id', $this->customer->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => ($cart->version ?? 0) + 1])->save();
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $this->shoe->id, 'quantity' => $quantity])->save();
        $service = app(CheckoutService::class);
        $order = $service->place($this->customer, $address->id, (string) Str::uuid(), $service->quote($this->customer, $address)['fingerprint']);
        $this->actingAs($this->staff);
        $this->transition($order, 'confirm');
        $this->transition($order->fresh(), 'pack');
        $this->transition($order->fresh(), 'ship', ['carrier_name' => 'GHN']);
        $this->transition($order->fresh(), 'fail', ['reason' => 'Khách vắng mặt sau ba lần liên hệ']);

        return $order->fresh(['shipment', 'items.units']);
    }

    private function transition(Order $order, string $action, array $fields = []): void
    {
        $this->post(route('admin.orders.transition', [$order, $action]), ['expected_status' => $order->fresh()->order_status->value] + $fields)->assertRedirect();
    }

    private function variant(int $stock, int $cost): ProductVariant
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'base_price' => 1_000_000, 'promotion_price' => 800_000, 'warranty_months' => 6, 'exchange_days' => 7]);
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41', 'price_override' => null]);
        $this->receive($variant, $stock, $cost);

        return $variant->fresh();
    }

    private function receive(ProductVariant $variant, int $quantity, int $cost): InventoryMovement
    {
        $line = StockImportItem::factory()->create(['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost_amount' => $cost, 'subtotal_amount' => $quantity * $cost]);

        return app(InventoryService::class)->receive(new InventoryChange('failed-return-test:import:'.$line->stock_import_id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $line->stock_import_id, $quantity, $cost));
    }
}
