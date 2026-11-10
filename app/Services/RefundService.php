<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentDisposition;
use App\Enums\PaymentMethod;
use App\Enums\RefundDestinationType;
use App\Enums\RefundReasonType;
use App\Enums\RefundStatus;
use App\Enums\RoleSlug;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class RefundService
{
    public function __construct(private readonly BusinessClock $clock) {}

    public function requestOrderCancellationRefund(Order $order, User $actor): Refund
    {
        $existing = Refund::query()->where('operation_key', "order:{$order->id}:cancel-refund")->first();
        if ($existing !== null) {
            return $existing;
        }
        $payment = Payment::query()->where('order_id', $order->id)->where('method', PaymentMethod::PrepaidDemo->value)
            ->where('status', PaymentAttemptStatus::Succeeded->value)->where('disposition', PaymentDisposition::Applied->value)->lockForUpdate()->first();
        $amount = Money::subtract($order->paid_amount, $order->refunded_amount);
        if ($payment === null || $amount < 1 || $amount > $payment->applied_amount) {
            throw new DomainException(ErrorCode::StateConflict, ['refund' => ['Không tìm thấy khoản thanh toán hợp lệ để hoàn.']]);
        }

        $now = $this->clock->now();
        $refund = new Refund;
        $refund->order_id = $order->id;
        $refund->payment_id = $payment->id;
        $refund->reason_type = RefundReasonType::OrderCancelled;
        $refund->status = RefundStatus::Requested;
        $refund->merchandise_amount = Money::subtract($amount, min($order->shipping_fee, $amount));
        $refund->shipping_amount = min($order->shipping_fee, $amount);
        $refund->amount = $amount;
        $refund->operation_key = "order:{$order->id}:cancel-refund";
        $refund->destination_type = RefundDestinationType::Cash;
        $refund->requested_by = $actor->id;
        $refund->created_at = $now;
        $refund->updated_at = $now;
        $refund->save();
        $order->payment_status = OrderPaymentStatus::RefundPending;
        $order->updated_at = $now;
        $order->save();

        return $refund;
    }

    public function approveCancellation(Refund $refund, User $admin): Refund
    {
        if (! $admin->hasRole(RoleSlug::Admin)) {
            throw new DomainException(ErrorCode::Forbidden);
        }

        return DB::transaction(function () use ($refund, $admin): Refund {
            $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === RefundStatus::Approved && $order->payment_status === OrderPaymentStatus::Refunded) {
                return $locked;
            }
            if ($order->order_status->value !== 'CANCELLED' || $locked->reason_type !== RefundReasonType::OrderCancelled
                || $locked->status !== RefundStatus::Requested) {
                throw new DomainException(ErrorCode::StateConflict);
            }
            $held = (int) Refund::query()->where('payment_id', $locked->payment_id)
                ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Approved->value, RefundStatus::Processing->value, RefundStatus::Succeeded->value])
                ->where('id', '<>', $locked->id)->sum('amount');
            if (Money::add($held, $locked->amount) > $order->paid_amount) {
                throw new DomainException(ErrorCode::StateConflict, ['refund' => ['Tổng tiền hoàn vượt số tiền đã thu.']]);
            }

            $now = $this->clock->now();
            $locked->status = RefundStatus::Approved;
            $locked->approved_by = $admin->id;
            $locked->updated_at = $now;
            $locked->save();
            $order->refunded_amount = Money::add($order->refunded_amount, $locked->amount);
            $order->payment_status = OrderPaymentStatus::Refunded;
            $order->updated_at = $now;
            $order->save();

            return $locked;
        });
    }
}
