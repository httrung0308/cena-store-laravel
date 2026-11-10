<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StoreNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/** @extends Factory<StoreNotification> */
class StoreNotificationFactory extends Factory
{
    protected $model = StoreNotification::class;

    private static bool $isPersisting = false;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'notification_key' => 'order-status-history:'.Str::uuid(),
            'order_status' => OrderStatus::Packing,
            'read_at' => null,
            'created_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (StoreNotification $notification): void {
            if (! self::$isPersisting) {
                return;
            }

            $history = $notification->order_status_history_id === null
                ? null
                : OrderStatusHistory::query()->findOrFail($notification->order_status_history_id);
            $order = $notification->order_id === null
                ? $history?->order
                : Order::query()->findOrFail($notification->order_id);
            $user = $notification->user_id === null
                ? null
                : User::withTrashed()->findOrFail($notification->user_id);

            if ($order === null) {
                $user ??= User::factory()->customer()->create();
                $order = Order::factory()->for($user)->create(['order_status' => $notification->order_status]);
            }
            $user ??= $order->user;

            if (! $user instanceof User || $order->user_id !== $user->id) {
                throw new LogicException('A store notification factory requires an order and its owning user.');
            }

            if ($history === null) {
                $history = OrderStatusHistory::withoutEvents(fn (): OrderStatusHistory => OrderStatusHistory::factory()
                    ->for($order, 'order')
                    ->create([
                        'from_status' => $this->previousStatus($notification->order_status),
                        'to_status' => $notification->order_status,
                        'reason_code' => 'FACTORY',
                    ]));
            }

            if ($history->order_id !== $order->id || $history->to_status !== $notification->order_status) {
                throw new LogicException('A store notification factory requires matching order status history.');
            }

            $notification->forceFill([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'order_status_history_id' => $history->id,
            ]);
        });
    }

    public function create($attributes = [], ?Model $parent = null): Model|Collection
    {
        self::$isPersisting = true;

        try {
            return parent::create($attributes, $parent);
        } finally {
            self::$isPersisting = false;
        }
    }

    private function previousStatus(OrderStatus $status): OrderStatus
    {
        return match ($status) {
            OrderStatus::Packing => OrderStatus::Confirmed,
            OrderStatus::OnDelivery => OrderStatus::Packing,
            OrderStatus::Delivered, OrderStatus::Failed => OrderStatus::OnDelivery,
            OrderStatus::Cancelled => OrderStatus::Pending,
            default => throw new LogicException('Unsupported store notification factory status.'),
        };
    }
}
