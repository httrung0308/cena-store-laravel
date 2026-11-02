<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\OrderItemUnit;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Verified-purchase reviews (WF-19): one review per delivered and paid pair, owner may edit theirs, staff reply
 * and hide/unhide without touching the customer's words. The product rating cache is recomputed from visible
 * reviews under the product lock on every change.
 */
final class ReviewService
{
    public function __construct(private readonly BusinessClock $clock) {}

    public function submit(User $owner, int $unitId, string $rating, string $comment): Review
    {
        try {
            return DB::transaction(function () use ($owner, $unitId, $rating, $comment): Review {
                $unit = OrderItemUnit::query()->with('item')->whereKey($unitId)->first();
                $order = $unit === null ? null : Order::query()->whereKey($unit->item->order_id)->lockForUpdate()->first();
                if ($order === null || $order->user_id !== $owner->id) {
                    throw new DomainException(ErrorCode::NotFound);
                }
                if ($order->order_status !== OrderStatus::Delivered || $order->payment_status !== OrderPaymentStatus::Paid) {
                    throw new DomainException(ErrorCode::StateConflict, ['review' => ['Chỉ đánh giá được đôi giày đã giao và đã thanh toán.']]);
                }
                $unit = OrderItemUnit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();
                $product = $this->lockProduct($unit);
                $review = Review::query()->where('order_item_unit_id', $unit->id)->lockForUpdate()->first() ?? new Review;
                $now = $this->clock->now();
                if (! $review->exists) {
                    $review->order_item_unit_id = $unit->id;
                    $review->user_id = $owner->id;
                    $review->is_visible = true;
                    $review->created_at = $now;
                }
                $review->rating = $rating;
                $review->comment = $comment;
                $review->updated_at = $now;
                $review->save();
                $this->refresh($product);

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel first review for the same pair won; retry once as an edit of that review.
            return $this->submit($owner, $unitId, $rating, $comment);
        }
    }

    public function reply(User $staff, Review $review, ?string $reply): Review
    {
        return DB::transaction(function () use ($staff, $review, $reply): Review {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $locked->admin_reply = $reply === null || trim($reply) === '' ? null : $reply;
            $locked->replied_by = $locked->admin_reply === null ? null : $staff->id;
            $locked->replied_at = $locked->admin_reply === null ? null : $this->clock->now();
            $locked->updated_at = $this->clock->now();
            $locked->save();

            return $locked;
        });
    }

    public function setVisible(Review $review, bool $visible): Review
    {
        return DB::transaction(function () use ($review, $visible): Review {
            $unit = OrderItemUnit::query()->whereKey($review->order_item_unit_id)->firstOrFail();
            $product = $this->lockProduct($unit);
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $locked->is_visible = $visible;
            $locked->updated_at = $this->clock->now();
            $locked->save();
            $this->refresh($product);

            return $locked;
        });
    }

    /** Product of the pair as bought (the original item's variant), never chosen by the client. */
    public function productIdFor(OrderItemUnit $unit): int
    {
        return (int) DB::table('order_items as oi')
            ->join('product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('product_colors as c', 'c.id', '=', 'v.product_color_id')
            ->where('oi.id', $unit->order_item_id)
            ->value('c.product_id');
    }

    /** Visible reviews of one product, newest first. */
    public function visibleFor(int $productId): Builder
    {
        return $this->reviewsOf($productId)->where('r.is_visible', true)->orderByDesc('r.created_at')->orderByDesc('r.id');
    }

    private function lockProduct(OrderItemUnit $unit): Product
    {
        return Product::withTrashed()->whereKey($this->productIdFor($unit))->lockForUpdate()->firstOrFail();
    }

    private function refresh(Product $product): void
    {
        $stats = $this->reviewsOf($product->id)->where('r.is_visible', true)
            ->selectRaw('COUNT(*) as total, COALESCE(ROUND(AVG(r.rating), 2), 0) as average')->first();
        $product->rating_avg = (string) $stats->average;
        $product->reviews_count = (int) $stats->total;
        $product->save();
    }

    private function reviewsOf(int $productId): Builder
    {
        return DB::table('reviews as r')
            ->join('order_item_units as u', 'u.id', '=', 'r.order_item_unit_id')
            ->join('order_items as oi', 'oi.id', '=', 'u.order_item_id')
            ->join('product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('product_colors as c', 'c.id', '=', 'v.product_color_id')
            ->where('c.product_id', $productId);
    }
}
