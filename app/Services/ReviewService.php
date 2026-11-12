<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RoleSlug;
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

    /** Visible reviews filtered by star tier (1..5, grouping half-stars up cleanly) or store replies. */
    public function visibleFilteredFor(int $productId, ?int $starTier = null, bool $withReplyOnly = false): Builder
    {
        $query = $this->visibleFor($productId);

        if ($starTier !== null && $starTier >= 1 && $starTier <= 5) {
            if ($starTier === 1) {
                $query->where('r.rating', '<=', 1.0);
            } else {
                $query->where('r.rating', '>', (string) ($starTier - 1).'.0')
                    ->where('r.rating', '<=', (string) $starTier.'.0');
            }
        }

        if ($withReplyOnly) {
            $query->whereNotNull('r.admin_reply')->where('r.admin_reply', '!=', '');
        }

        return $query;
    }

    /** Public aggregate statistics derived strictly from visible reviews (never includes hidden reviews). */
    public function summaryFor(int $productId): array
    {
        $rows = $this->reviewsOf($productId)
            ->where('r.is_visible', true)
            ->get(['r.rating', 'r.admin_reply']);

        $total = $rows->count();
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $replyCount = 0;
        $sum = 0.0;

        foreach ($rows as $row) {
            $rating = (float) $row->rating;
            $sum += $rating;
            $bucket = max(1, min(5, (int) ceil($rating)));
            $breakdown[$bucket]++;
            if (is_string($row->admin_reply) && trim($row->admin_reply) !== '') {
                $replyCount++;
            }
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round($sum / $total, 2) : 0.0,
            'breakdown' => $breakdown,
            'with_reply_count' => $replyCount,
        ];
    }

    /**
     * Eligibility state of the signed-in visitor for reviewing one product based strictly on delivered & paid pairs.
     *
     * @return array{
     *     status: 'guest'|'admin'|'can_review'|'already_reviewed'|'pending_delivery'|'not_purchased',
     *     pending_units: list<array{unit_id: int, order_code: string, color_name: string, size_value: string}>,
     *     reviewed_units: list<array{unit_id: int, order_code: string, color_name: string, size_value: string, rating: string, comment: string, is_visible: bool}>,
     *     pending_order: ?array{order_code: string, order_url: string}
     * }
     */
    public function customerReviewState(?User $user, int $productId): array
    {
        $empty = [
            'status' => 'guest',
            'pending_units' => [],
            'reviewed_units' => [],
            'pending_order' => null,
        ];

        if ($user === null) {
            return $empty;
        }

        if ($user->hasRole(RoleSlug::Admin)) {
            return [...$empty, 'status' => 'admin'];
        }

        $units = DB::table('order_item_units as u')
            ->join('order_items as oi', 'oi.id', '=', 'u.order_item_id')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('product_colors as c', 'c.id', '=', 'v.product_color_id')
            ->leftJoin('reviews as r', 'r.order_item_unit_id', '=', 'u.id')
            ->where('c.product_id', $productId)
            ->where('o.user_id', $user->id)
            ->orderByDesc('o.id')
            ->orderBy('u.unit_no')
            ->get([
                'u.id as unit_id',
                'o.order_code',
                'o.order_status',
                'o.payment_status',
                'c.color_name',
                'oi.size_value_snapshot',
                'r.id as review_id',
                'r.rating as review_rating',
                'r.comment as review_comment',
                'r.is_visible as review_is_visible',
            ]);

        $pendingUnits = [];
        $reviewedUnits = [];
        $pendingOrder = null;

        foreach ($units as $row) {
            $eligibleOrder = $row->order_status === OrderStatus::Delivered->value
                && $row->payment_status === OrderPaymentStatus::Paid->value;

            if (! $eligibleOrder) {
                if ($pendingOrder === null && $row->order_status !== OrderStatus::Cancelled->value) {
                    $pendingOrder = [
                        'order_code' => $row->order_code,
                        'order_url' => route('orders.show', $row->order_code),
                    ];
                }

                continue;
            }

            if ($row->review_id === null) {
                $pendingUnits[] = [
                    'unit_id' => (int) $row->unit_id,
                    'order_code' => $row->order_code,
                    'color_name' => $row->color_name,
                    'size_value' => $row->size_value_snapshot,
                ];
            } else {
                $reviewedUnits[] = [
                    'unit_id' => (int) $row->unit_id,
                    'order_code' => $row->order_code,
                    'color_name' => $row->color_name,
                    'size_value' => $row->size_value_snapshot,
                    'rating' => number_format((float) $row->review_rating, 1, '.', ''),
                    'comment' => (string) $row->review_comment,
                    'is_visible' => (bool) $row->review_is_visible,
                ];
            }
        }

        if ($pendingUnits !== []) {
            $status = 'can_review';
        } elseif ($reviewedUnits !== []) {
            $status = 'already_reviewed';
        } elseif ($pendingOrder !== null) {
            $status = 'pending_delivery';
        } else {
            $status = 'not_purchased';
        }

        return [
            'status' => $status,
            'pending_units' => $pendingUnits,
            'reviewed_units' => $reviewedUnits,
            'pending_order' => $pendingOrder,
        ];
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
