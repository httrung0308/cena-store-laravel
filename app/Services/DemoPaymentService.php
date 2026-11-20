<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentDisposition;
use App\Enums\PaymentMethod;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DemoPaymentService
{
    public function __construct(private readonly BusinessClock $clock) {}

    public function complete(Order $order, User $customer, bool $succeeded): Order
    {
        return DB::transaction(function () use ($order, $customer, $succeeded): Order {
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($locked, $customer);
            if ($locked->payment_status === OrderPaymentStatus::Paid) {
                return $locked;
            }

            $payment = Payment::query()->where('order_id', $locked->id)->where('method', PaymentMethod::PrepaidDemo->value)
                ->where('status', PaymentAttemptStatus::Pending->value)->lockForUpdate()->orderByDesc('id')->first();
            if ($payment === null) {
                throw new DomainException(ErrorCode::StateConflict, ['payment' => ['Không có lượt thanh toán đang chờ. Hãy thử thanh toán lại.']]);
            }

            $now = $this->clock->now();
            $payment->status = $succeeded ? PaymentAttemptStatus::Succeeded : PaymentAttemptStatus::Failed;
            $payment->disposition = $succeeded ? PaymentDisposition::Applied : PaymentDisposition::None;
            $payment->received_amount = $succeeded ? $locked->total_amount : 0;
            $payment->applied_amount = $succeeded ? $locked->total_amount : 0;
            $payment->provider_transaction_id = $succeeded ? 'DEMO-'.$payment->attempt_ref : null;
            $payment->confirmed_by = $succeeded ? $customer->id : null;
            $payment->succeeded_at = $succeeded ? $now : null;
            $payment->updated_at = $now;
            $payment->save();

            if ($succeeded) {
                $locked->paid_amount = $locked->total_amount;
                $locked->payment_status = OrderPaymentStatus::Paid;
                $locked->updated_at = $now;
                $locked->save();
            }

            return $locked;
        });
    }

    public function retry(Order $order, User $customer): Payment
    {
        return DB::transaction(function () use ($order, $customer): Payment {
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($locked, $customer);
            if ($locked->payment_status === OrderPaymentStatus::Paid) {
                throw new DomainException(ErrorCode::StateConflict);
            }

            $pending = Payment::query()->where('order_id', $locked->id)->where('method', PaymentMethod::PrepaidDemo->value)
                ->where('status', PaymentAttemptStatus::Pending->value)->lockForUpdate()->first();
            if ($pending !== null) {
                return $pending;
            }

            $now = $this->clock->now();
            $attempt = new Payment;
            $attempt->order_id = $locked->id;
            $attempt->origin_payment_id = Payment::query()->where('order_id', $locked->id)->where('method', PaymentMethod::PrepaidDemo->value)->orderBy('id')->value('id');
            $attempt->attempt_ref = 'DEMO-'.strtoupper((string) Str::ulid());
            $attempt->method = PaymentMethod::PrepaidDemo;
            $attempt->status = PaymentAttemptStatus::Pending;
            $attempt->expected_amount = $locked->total_amount;
            $attempt->expires_at = $locked->payment_expires_at;
            $attempt->idempotency_key = "order:{$locked->id}:payment:retry:".strtoupper((string) Str::ulid());
            $attempt->created_at = $now;
            $attempt->updated_at = $now;
            $attempt->save();

            return $attempt;
        });
    }

    private function assertPayable(Order $order, User $customer): void
    {
        if ($order->user_id !== $customer->id || $order->payment_method !== PaymentMethod::PrepaidDemo) {
            throw new DomainException(ErrorCode::NotFound);
        }
        if ($order->order_status === OrderStatus::Cancelled || $order->payment_status === OrderPaymentStatus::Refunded) {
            throw new DomainException(ErrorCode::StateConflict, ['payment' => ['Đơn đã hủy hoặc đã hoàn tiền nên không thể thanh toán.']]);
        }
    }
}
