<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StoreNotification;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class StoreNotificationService
{
    /** @var list<OrderStatus> */
    private const NOTIFIABLE_STATUSES = [
        OrderStatus::Packing,
        OrderStatus::OnDelivery,
        OrderStatus::Delivered,
        OrderStatus::Cancelled,
        OrderStatus::Failed,
    ];

    public function __construct(private readonly BusinessClock $clock) {}

    public function recordForStatusHistory(OrderStatusHistory $history): ?StoreNotification
    {
        if (! in_array($history->to_status, self::NOTIFIABLE_STATUSES, true)) {
            return null;
        }
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Order status notifications require the source transaction.');
        }

        $order = Order::query()->whereKey($history->order_id)->with('user')->first();
        if ($order === null || $order->order_status !== $history->to_status) {
            return null;
        }
        $owner = $order->user;
        if (! $owner instanceof User) {
            return null;
        }

        $existing = StoreNotification::query()->where('order_status_history_id', $history->id)->lockForUpdate()->first();
        if ($existing !== null) {
            return $existing;
        }
        $notification = new StoreNotification;
        $notification->forceFill([
            'notification_key' => 'order-status-history:'.$history->id,
            'user_id' => $owner->id,
            'order_id' => $order->id,
            'order_status_history_id' => $history->id,
            'order_status' => $history->to_status,
            'read_at' => null,
            'created_at' => $history->created_at ?? $this->clock->now(),
        ])->save();

        return $notification->refresh();
    }

    public function unreadCount(User $user): int
    {
        return StoreNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /**
     * @return Collection<int, StoreNotification>
     */
    public function recentForUser(User $user, int $limit = 6): Collection
    {
        return StoreNotification::query()
            ->where('user_id', $user->id)
            ->with('order:id,order_code')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function markRead(User $user, int $notificationId): StoreNotification
    {
        return DB::transaction(function () use ($user, $notificationId): StoreNotification {
            $notification = StoreNotification::query()
                ->where('user_id', $user->id)
                ->whereKey($notificationId)
                ->lockForUpdate()
                ->first();
            if ($notification === null) {
                throw new DomainException(ErrorCode::NotFound);
            }
            if ($notification->read_at === null) {
                $notification->read_at = $this->clock->now();
                $notification->save();
            }

            return $notification->refresh();
        }, 3);
    }

    public function markAllRead(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            return StoreNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => $this->clock->now()]);
        }, 3);
    }
}
