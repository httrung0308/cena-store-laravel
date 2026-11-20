<?php

namespace App\Http\Resources\Customer;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreNotification;
use App\Services\StoreNotificationService;
use Illuminate\Http\Request;

class StoreNotificationResource extends OwnedResource
{
    /** @return array<string, mixed> */
    public static function present(StoreNotification $notification): array
    {
        $order = $notification->order;

        return [
            'id' => $notification->id,
            'order_code' => $order?->order_code,
            'order_status' => $notification->order_status->value,
            'order_status_label' => $notification->order_status->label(),
            'headline' => self::headlineFor($notification->order_status),
            'item_summary' => self::itemSummaryFor($order),
            'thumbnail_url' => self::thumbnailUrlFor($order),
            'review_cta' => self::reviewCtaFor($notification, $order),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'url' => $order === null ? null : route('orders.show', $order->order_code),
        ];
    }

    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        $this->resource->loadMissing(app(StoreNotificationService::class)->summaryRelations());

        return self::present($this->resource);
    }

    protected function ownerId(): ?int
    {
        return $this->user_id;
    }

    private static function headlineFor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Packing => 'Đơn hàng đang được đóng gói',
            OrderStatus::OnDelivery => 'Đơn hàng đang được vận chuyển',
            OrderStatus::Delivered => 'Đơn hàng đã giao thành công',
            OrderStatus::Failed => 'Giao hàng không thành công',
            OrderStatus::Cancelled => 'Đơn hàng đã bị hủy',
            default => $status->label(),
        };
    }

    private static function itemSummaryFor(?Order $order): string
    {
        if ($order === null || $order->items->isEmpty()) {
            return 'Đơn hàng';
        }

        /** @var OrderItem $firstItem */
        $firstItem = $order->items->first();
        $size = is_string($firstItem->size_value_snapshot) && $firstItem->size_value_snapshot !== ''
            ? 'Size '.$firstItem->size_value_snapshot
            : null;
        $parts = array_values(array_filter([
            $firstItem->product_name_snapshot,
            $firstItem->color_name_snapshot,
            $size,
        ], static fn (?string $value): bool => is_string($value) && $value !== ''));

        $summary = $parts !== [] ? implode(' · ', $parts) : 'Đơn hàng';
        $extraCount = $order->items->count() - 1;
        if ($extraCount > 0) {
            $summary .= " · Cùng {$extraCount} sản phẩm khác";
        }

        return $summary;
    }

    private static function thumbnailUrlFor(?Order $order): ?string
    {
        if ($order === null || $order->items->isEmpty()) {
            return null;
        }

        /** @var OrderItem|null $firstItem */
        $firstItem = $order->items->first();
        $thumbnail = $firstItem?->variant?->color?->thumbnail;

        return $thumbnail?->thumbnailUrl(64);
    }

    /**
     * @return array{label: string, prompt: string, url: string}|null
     */
    private static function reviewCtaFor(StoreNotification $notification, ?Order $order): ?array
    {
        if ($notification->order_status !== OrderStatus::Delivered || $order === null) {
            return null;
        }

        if ($order->user_id !== $notification->user_id
            || $order->order_status !== OrderStatus::Delivered
            || $order->payment_status !== OrderPaymentStatus::Paid) {
            return null;
        }

        $eligibleItems = $order->items->filter(
            static fn (OrderItem $item): bool => $item->units->isNotEmpty()
                && $item->units->every(static fn ($unit): bool => $unit->review === null)
        );

        if ($eligibleItems->isEmpty()) {
            return null;
        }

        $allEligibleHaveActiveProduct = $eligibleItems->every(static function (OrderItem $item): bool {
            $product = $item->variant?->color?->product;

            return $product instanceof Product
                && $product->is_active
                && is_string($product->slug)
                && $product->slug !== '';
        });

        $distinctProductIds = $eligibleItems
            ->map(static fn (OrderItem $item): ?Product => $item->variant?->color?->product)
            ->filter(static fn (?Product $product): bool => $product instanceof Product)
            ->unique('id')
            ->values();

        $url = ($allEligibleHaveActiveProduct && $distinctProductIds->count() === 1)
            ? route('shoes.show', $distinctProductIds->first()->slug).'#product-reviews'
            : route('orders.show', $order->order_code);

        return [
            'label' => 'Đánh giá sản phẩm',
            'prompt' => 'Chia sẻ cảm nhận về sản phẩm bạn vừa nhận.',
            'url' => $url,
        ];
    }
}
