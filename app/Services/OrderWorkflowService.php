<?php

namespace App\Services;

use App\Data\InventoryChange;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderUnitState;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentDisposition;
use App\Enums\PaymentMethod;
use App\Enums\SalesLedgerType;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\ShipmentDeliveryProof;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Cost;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Order state machine for COD and prepaid demo orders. Every transition locks the order, checks the status the caller
 * saw, and is recorded once under a command key, so a double click neither repeats history nor stock moves.
 * Callers authorise the actor; the customer may only cancel their own PENDING order.
 */
final class OrderWorkflowService
{
    public function __construct(
        private readonly BusinessClock $clock,
        private readonly InventoryService $inventory,
        private readonly SalesLedgerService $ledger,
        private readonly CheckoutService $checkout,
        private readonly RefundService $refunds,
    ) {}

    public function confirm(Order $order, User $actor, string $expected): Order
    {
        return $this->confirmAndPack($order, $actor, $expected);
    }

    /** Confirm and start packing in one staff action; legacy CONFIRMED orders still use pack(). */
    public function confirmAndPack(Order $order, User $actor, string $expected): Order
    {
        return $this->transition($order, $actor, $expected, [OrderStatus::Pending], OrderStatus::Packing, 'STAFF_CONFIRM_AND_PACK', null,
            function (Order $order, CarbonImmutable $now): void {
                $order->confirmed_at = $now;
                $order->packed_at = $now;
            });
    }

    public function pack(Order $order, User $actor, string $expected): Order
    {
        return $this->transition($order, $actor, $expected, [OrderStatus::Confirmed], OrderStatus::Packing, 'STAFF_PACK', null,
            function (Order $order, $now): void {
                $order->packed_at = $now;
            });
    }

    /** Hand over to the carrier: issue reserved stock once and freeze COGS per pair. */
    public function ship(Order $order, User $actor, string $expected, string $carrier, ?string $tracking): Order
    {
        return $this->transition($order, $actor, $expected, [OrderStatus::Packing], OrderStatus::OnDelivery, 'STAFF_SHIP', null,
            function (Order $order, $now) use ($actor, $carrier, $tracking): void {
                if ($order->payment_method === PaymentMethod::PrepaidDemo && $order->payment_status !== OrderPaymentStatus::Paid) {
                    throw new DomainException(ErrorCode::StateConflict, ['order' => ['Đơn trả trước cần được thanh toán trước khi gửi.']]);
                }
                $items = $this->items($order);
                $changes = $items->map(fn (OrderItem $item): InventoryChange => new InventoryChange("order:{$order->id}:dispatch:{$item->variant_id}",
                    $item->variant_id, InventoryAction::OrderDispatched, InventoryReferenceType::Order, $order->id, $item->quantity, null, $actor->id))->values()->all();
                $movements = collect($this->inventory->applyBatch($changes))->keyBy('variant_id');
                foreach ($items as $item) {
                    $basis = $movements->get($item->variant_id)->unit_cost_basis;
                    $cogs = Cost::cogs($basis, $item->quantity);
                    $item->cost_unit_snapshot = $basis;
                    $item->cogs_amount = $cogs;
                    $item->updated_at = $now;
                    $item->save();
                    $units = $item->units->sortBy('unit_no')->values();
                    $shares = Money::allocate($cogs, $units->map(fn (OrderItemUnit $unit): array => ['id' => $unit->id, 'unit_no' => $unit->unit_no, 'weight' => 1])->all());
                    foreach ($units as $unit) {
                        $unit->state = OrderUnitState::Outbound;
                        $unit->original_cogs_amount = $shares[$unit->id];
                        $unit->current_cogs_amount = $shares[$unit->id];
                        $unit->updated_at = $now;
                        $unit->save();
                    }
                }
                $shipment = new Shipment;
                $shipment->order_id = $order->id;
                $shipment->carrier_name = $carrier;
                $shipment->tracking_code = $tracking;
                $shipment->status = ShipmentStatus::InTransit;
                $shipment->created_by = $actor->id;
                $shipment->shipped_at = $now;
                $shipment->created_at = $now;
                $shipment->updated_at = $now;
                $shipment->save();
                $order->shipped_at = $now;
            });
    }

    /** COD delivery requires a private proof image; prepaid demo orders are already paid at checkout. */
    public function deliver(Order $order, User $actor, string $expected, ?UploadedFile $proof): Order
    {
        if ($order->order_status !== OrderStatus::OnDelivery || $expected !== OrderStatus::OnDelivery->value) {
            throw new DomainException(ErrorCode::StateConflict, ['order' => ['Đơn không ở trạng thái đang vận chuyển.']]);
        }
        $proofPath = null;
        if ($order->payment_method === PaymentMethod::Cod) {
            if ($proof === null) {
                throw new DomainException(ErrorCode::InvalidPayload, ['proof' => ['Đơn COD cần ảnh minh chứng giao hàng.']]);
            }
            $proofPath = $proof->store('shipment-proofs', 'private');
        } elseif ($order->payment_method !== PaymentMethod::PrepaidDemo) {
            throw new DomainException(ErrorCode::StateConflict);
        }

        try {
            $delivered = $this->transition($order, $actor, $expected, [OrderStatus::OnDelivery], OrderStatus::Delivered, 'STAFF_DELIVER', null,
            function (Order $order, $now) use ($actor, $proofPath, $proof): void {
                if ($order->payment_method === PaymentMethod::PrepaidDemo && $order->payment_status !== OrderPaymentStatus::Paid) {
                    throw new DomainException(ErrorCode::StateConflict, ['order' => ['Đơn trả trước chưa được thanh toán.']]);
                }
                $payment = Payment::query()->where('order_id', $order->id)->where('status', PaymentAttemptStatus::Pending)->lockForUpdate()->first();
                if ($order->payment_method === PaymentMethod::Cod && ($payment === null || $payment->method !== PaymentMethod::Cod)) {
                    throw new DomainException(ErrorCode::StateConflict);
                }
                if ($order->payment_method === PaymentMethod::Cod) {
                    $payment->status = PaymentAttemptStatus::Succeeded;
                    $payment->disposition = PaymentDisposition::Applied;
                    $payment->received_amount = $order->total_amount;
                    $payment->applied_amount = $order->total_amount;
                    $payment->confirmed_by = $actor->id;
                    $payment->succeeded_at = $now;
                    $payment->save();
                    $order->paid_amount = $order->total_amount;
                    $order->payment_status = OrderPaymentStatus::Paid;
                }
                $order->delivered_at = $now;
                $this->checkout->consumeCoupon($order, $now);

                $shipment = Shipment::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
                if ($proofPath !== null) {
                    $deliveryProof = new ShipmentDeliveryProof;
                    $deliveryProof->shipment_id = $shipment->id;
                    $deliveryProof->uploaded_by = $actor->id;
                    $deliveryProof->path = $proofPath;
                    $deliveryProof->sha256 = hash_file('sha256', $proof->getRealPath());
                    $deliveryProof->mime_type = $proof->getMimeType();
                    $deliveryProof->size_bytes = $proof->getSize();
                    $deliveryProof->created_at = $now;
                    $deliveryProof->save();
                }
                $shipment->status = ShipmentStatus::Delivered;
                $shipment->delivered_at = $now;
                $shipment->updated_at = $now;
                $shipment->save();

                foreach ($this->items($order) as $item) {
                    foreach ($item->units as $unit) {
                        $unit->state = OrderUnitState::OwnedByCustomer;
                        $unit->exchange_deadline_at = $this->clock->exchangeDeadline($now, $item->exchange_days_snapshot);
                        $unit->warranty_deadline_at = $this->clock->warrantyDeadline($now, $item->warranty_months_snapshot);
                        $unit->updated_at = $now;
                        $unit->save();
                        $this->ledger->postUnique("sale:unit:{$unit->id}", SalesLedgerType::SaleRecognized,
                            ['merchandise_revenue_delta' => $unit->net_amount, 'cogs_delta' => (int) $unit->current_cogs_amount],
                            ['order_id' => $order->id, 'order_item_unit_id' => $unit->id], $now);
                    }
                }
                $this->ledger->postUnique("shipping:order:{$order->id}", SalesLedgerType::ShippingRecognized,
                    ['shipping_revenue_delta' => $order->shipping_fee], ['order_id' => $order->id], $now);
            });
            if ($proofPath !== null && ! ShipmentDeliveryProof::query()->where('path', $proofPath)->exists()) {
                Storage::disk('private')->delete($proofPath);
            }

            return $delivered;
        } catch (\Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('private')->delete($proofPath);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw new DomainException(ErrorCode::Conflict, ['proof' => ['Ảnh này đã được dùng làm minh chứng cho một đơn hàng khác.']]);
            }
            throw $exception;
        }
    }

    /** Cancel before dispatch: release the reservation and void the pending COD attempt. */
    public function cancel(Order $order, User $actor, string $expected, string $reason, bool $byCustomer): Order
    {
        $allowed = $byCustomer ? [OrderStatus::Pending] : [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Packing];

        return $this->transition($order, $actor, $expected, $allowed, OrderStatus::Cancelled, $byCustomer ? 'CUSTOMER_CANCEL' : 'STAFF_CANCEL', $reason,
            function (Order $order, $now) use ($actor, $reason): void {
                $this->checkout->releaseCoupon($order, $now);
                $items = $this->items($order);
                $changes = $items->map(fn (OrderItem $item): InventoryChange => new InventoryChange("order:{$order->id}:release:{$item->variant_id}",
                    $item->variant_id, InventoryAction::OrderReleased, InventoryReferenceType::Order, $order->id, $item->quantity, null, $actor->id))->values()->all();
                $this->inventory->applyBatch($changes);
                foreach ($items as $item) {
                    foreach ($item->units as $unit) {
                        $unit->state = OrderUnitState::Cancelled;
                        $unit->updated_at = $now;
                        $unit->save();
                    }
                }
                $this->voidPendingPayment($order, $now);
                if ($order->payment_method === PaymentMethod::PrepaidDemo && $order->paid_amount > $order->refunded_amount) {
                    $this->refunds->requestOrderCancellationRefund($order, $actor);
                }
                $order->cancel_reason = $reason;
                $order->cancelled_at = $now;
            });
    }

    /** Delivery failed: the parcel is on its way back; stock returns only through the return-receipt workflow. */
    public function fail(Order $order, User $actor, string $expected, string $reason): Order
    {
        return $this->transition($order, $actor, $expected, [OrderStatus::OnDelivery], OrderStatus::Failed, 'DELIVERY_FAILED', $reason,
            function (Order $order, $now) use ($reason): void {
                $this->checkout->releaseCoupon($order, $now);
                $shipment = Shipment::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
                $shipment->status = ShipmentStatus::Returning;
                $shipment->failed_at = $now;
                $shipment->note = $reason;
                $shipment->updated_at = $now;
                $shipment->save();
                $this->voidPendingPayment($order, $now);
                $order->cancel_reason = $reason;
                $order->failed_at = $now;
            });
    }

    /**
     * @param  list<OrderStatus>  $from
     * @param  Closure(Order, CarbonImmutable): void  $effects
     */
    private function transition(Order $order, User $actor, string $expected, array $from, OrderStatus $to, string $reasonCode, ?string $note, Closure $effects): Order
    {
        return DB::transaction(function () use ($order, $actor, $expected, $from, $to, $reasonCode, $note, $effects): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $commandKey = "order:{$locked->id}:to:{$to->value}";
            if (OrderStatusHistory::query()->where('command_key', $commandKey)->exists()) {
                return $locked;
            }
            if ($locked->order_status->value !== $expected || ! in_array($locked->order_status, $from, true)) {
                throw new DomainException(ErrorCode::StateConflict, ['order' => ['Trạng thái đơn đã thay đổi, vui lòng tải lại trang.']]);
            }
            $now = $this->clock->now();
            $previous = $locked->order_status;
            $effects($locked, $now);
            $locked->order_status = $to;
            $locked->updated_at = $now;
            $locked->save();
            $this->checkout->history($locked, $actor, $previous, $to, $reasonCode, $note, $commandKey);

            return $locked;
        });
    }

    /** @return Collection<int, OrderItem> */
    private function items(Order $order): Collection
    {
        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('variant_id')->lockForUpdate()->get();
        $units = OrderItemUnit::query()->whereIn('order_item_id', $items->modelKeys())->orderBy('id')->lockForUpdate()->get()->groupBy('order_item_id');
        foreach ($items as $item) {
            $item->setRelation('units', $units->get($item->id, new Collection));
        }

        return $items;
    }

    private function voidPendingPayment(Order $order, $now): void
    {
        Payment::query()->where('order_id', $order->id)->where('status', PaymentAttemptStatus::Pending)->lockForUpdate()->get()
            ->each(function (Payment $payment) use ($now): void {
                $payment->status = PaymentAttemptStatus::Failed;
                $payment->updated_at = $now;
                $payment->save();
            });
    }
}
