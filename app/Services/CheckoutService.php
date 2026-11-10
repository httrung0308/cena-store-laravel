<?php

namespace App\Services;

use App\Data\InventoryChange;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderUnitState;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponType;
use App\Enums\OutboxAggregateType;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductImageSourceType;
use App\Exceptions\DomainException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemUnit;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\CanonicalPayload;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Checkout creates a read-only quote, then locks cart/coupon/variants and
 * variants, re-prices under lock, creates order/items/units/payment/history, reserves stock and empties the cart.
 * The same checkout key replays the same order; a different payload under that key is rejected.
 */
final class CheckoutService
{
    public function __construct(
        private readonly BusinessClock $clock,
        private readonly InventoryService $inventory,
        private readonly OutboxService $outbox,
    ) {}

    /**
     * @return array{version: int, lines: list<array<string, mixed>>, problems: list<string>, subtotal: int, shipping_base_fee: int, merchandise_discount: int, shipping_discount: int, shipping_fee: int, total: int, coupon_code: ?string, coupon_error: ?string, fingerprint: ?string}
     */
    public function quote(User $user, ?Address $address, PaymentMethod $method = PaymentMethod::Cod, ?string $couponCode = null): array
    {
        $cart = Cart::query()->where('user_id', $user->id)->first();
        $items = $cart === null ? collect() : CartItem::query()->where('cart_id', $cart->id)->orderBy('id')->get();
        $variants = $this->variants($items->pluck('variant_id')->all(), false);

        return $this->price($user, $cart?->version ?? 0, $items, $variants, $address, $method, $couponCode);
    }

    public function place(User $user, int $addressId, string $checkoutKey, string $fingerprint, ?string $note, PaymentMethod $method = PaymentMethod::Cod, ?string $couponCode = null): Order
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(fn (): Order => $this->placeLocked($user, $addressId, $checkoutKey, $fingerprint, $note, $method, $couponCode));
            } catch (UniqueConstraintViolationException $exception) {
                // A parallel request with the same key won: replay its order on the next pass.
                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }

        throw new DomainException(ErrorCode::Conflict);
    }

    private function placeLocked(User $user, int $addressId, string $checkoutKey, string $fingerprint, ?string $note, PaymentMethod $method, ?string $couponCode): Order
    {
        User::query()->whereKey($user->id)->lockForUpdate()->first();
        $existing = Order::query()->where('user_id', $user->id)->where('checkout_key', $checkoutKey)->lockForUpdate()->first();
        if ($existing !== null) {
            if (! hash_equals($existing->checkout_fingerprint, $fingerprint)) {
                throw new DomainException(ErrorCode::IdempotencyMismatch);
            }

            return $existing;
        }

        $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();
        $items = $cart === null ? collect() : CartItem::query()->where('cart_id', $cart->id)->orderBy('id')->lockForUpdate()->get();
        if ($cart === null || $items->isEmpty()) {
            throw new DomainException(ErrorCode::EmptyCart);
        }
        $address = Address::query()->where('user_id', $user->id)->whereKey($addressId)->first();
        if ($address === null || $address->dataset_version !== (string) config('store.shipping.dataset_version')) {
            throw new DomainException(ErrorCode::InvalidAddress, ['address_id' => ['Vui lòng chọn một địa chỉ hợp lệ trong sổ địa chỉ.']]);
        }
        $coupon = $this->coupon($couponCode, true);
        $variants = $this->variants($items->pluck('variant_id')->all(), true);
        $quote = $this->price($user, $cart->version, $items, $variants, $address, $method, $couponCode, $coupon);
        if ($quote['problems'] !== []) {
            throw new DomainException(ErrorCode::StockUnavailable, ['cart' => $quote['problems']]);
        }
        if ($quote['coupon_error'] !== null) {
            throw new DomainException(ErrorCode::CouponExhausted, ['coupon_code' => [$quote['coupon_error']]]);
        }
        if (! hash_equals((string) $quote['fingerprint'], $fingerprint)) {
            throw new DomainException(ErrorCode::PriceChanged, ['cart' => ['Giỏ hàng, giá hoặc phí vận chuyển vừa thay đổi. Vui lòng kiểm tra lại đơn.']]);
        }

        $now = $this->clock->now();
        $order = new Order;
        $order->order_code = 'ORD-'.$now->setTimezone((string) config('store.business_timezone'))->format('Ymd').'-'.strtoupper((string) Str::ulid());
        $order->user_id = $user->id;
        $order->coupon_id = $coupon?->id;
        $order->coupon_code_snapshot = $coupon?->code;
        $order->subtotal_amount = $quote['subtotal'];
        $order->merchandise_discount_amount = $quote['merchandise_discount'];
        $order->shipping_base_fee = $quote['shipping_base_fee'];
        $order->shipping_discount_amount = $quote['shipping_discount'];
        $order->shipping_fee = $quote['shipping_fee'];
        $order->total_amount = Money::orderTotal($quote['total'], config('store.limits.order_total'));
        $order->order_status = OrderStatus::Pending;
        $order->payment_method = $method;
        $order->payment_status = OrderPaymentStatus::Unpaid;
        $order->shipping_name = $address->recipient_name;
        $order->shipping_phone = $address->phone;
        $order->shipping_address_snapshot = [
            'street' => $address->street_address,
            'ward_code' => $address->ward_code_snapshot,
            'ward_name' => $address->ward_name_snapshot,
            'province_code' => $address->province_code_snapshot,
            'province_name' => $address->province_name_snapshot,
            'dataset_version' => $address->dataset_version,
        ];
        $order->customer_note = $note;
        $order->checkout_key = $checkoutKey;
        $order->checkout_fingerprint = $fingerprint;
        $order->source_cart_version = max(1, $cart->version);
        $order->payment_expires_at = $this->clock->deadline($method === PaymentMethod::Cod ? 'cod_confirmation' : 'online_payment');
        $order->created_at = $now;
        $order->updated_at = $now;
        $order->save();

        $changes = [];
        foreach ($quote['lines'] as $line) {
            $variant = $variants->get($line['variant_id']);
            $color = $variant->historicalColor;
            $product = $color->historicalProduct;
            $item = new OrderItem;
            $item->order_id = $order->id;
            $item->variant_id = $variant->id;
            $item->product_name_snapshot = $product->name;
            $item->style_color_snapshot = $color->style_color_code;
            $item->color_name_snapshot = $color->color_name;
            $item->size_value_snapshot = $variant->size_value;
            $item->sku_snapshot = $variant->sku;
            $item->thumbnail_snapshot = $line['thumbnail_url'];
            $item->quantity = $line['quantity'];
            $item->original_unit_price = Money::integer($product->base_price);
            $item->unit_price = $line['unit_price'];
            $item->subtotal_amount = $line['line_total'];
            $item->discount_amount = $line['discount_amount'];
            $item->net_amount = $line['net_amount'];
            $item->exchange_days_snapshot = (int) $product->exchange_days;
            $item->warranty_months_snapshot = (int) $product->warranty_months;
            $item->created_at = $now;
            $item->updated_at = $now;
            $item->save();
            $unitDiscounts = Money::allocate($line['discount_amount'], array_map(fn (int $unitNo): array => [
                'id' => $unitNo,
                'unit_no' => $unitNo,
                'weight' => 1,
            ], range(1, $line['quantity'])));
            for ($unitNo = 1; $unitNo <= $line['quantity']; $unitNo++) {
                $unit = new OrderItemUnit;
                $unit->order_item_id = $item->id;
                $unit->unit_no = $unitNo;
                $unit->current_variant_id = $variant->id;
                $unit->state = OrderUnitState::Allocated;
                $unit->gross_amount = $line['unit_price'];
                $unit->discount_amount = $unitDiscounts[$unitNo];
                $unit->net_amount = Money::subtract($line['unit_price'], $unitDiscounts[$unitNo]);
                $unit->created_at = $now;
                $unit->updated_at = $now;
                $unit->save();
            }
            $changes[] = new InventoryChange("order:{$order->id}:reserve:{$variant->id}", $variant->id, InventoryAction::OrderReserved,
                InventoryReferenceType::Order, $order->id, $line['quantity'], null, $user->id);
        }
        $this->inventory->applyBatch($changes);

        if ($coupon !== null) {
            $redemption = new CouponRedemption;
            $redemption->coupon_id = $coupon->id;
            $redemption->user_id = $user->id;
            $redemption->order_id = $order->id;
            $redemption->status = CouponRedemptionStatus::Reserved;
            $redemption->merchandise_discount_amount = $quote['merchandise_discount'];
            $redemption->shipping_discount_amount = $quote['shipping_discount'];
            $redemption->reserved_at = $now;
            $redemption->created_at = $now;
            $redemption->updated_at = $now;
            $redemption->save();
        }

        $payment = new Payment;
        $payment->order_id = $order->id;
        $payment->attempt_ref = $method->value.'-'.strtoupper((string) Str::ulid());
        $payment->method = $method;
        $payment->status = PaymentAttemptStatus::Pending;
        $payment->expected_amount = $order->total_amount;
        $payment->expires_at = $order->payment_expires_at;
        $payment->idempotency_key = "order:{$order->id}:payment:{$method->value}:initial";
        $payment->created_at = $now;
        $payment->updated_at = $now;
        $payment->save();

        $this->history($order, $user, null, OrderStatus::Pending, 'ORDER_CREATED', null, "order:{$order->id}:created");
        $this->outbox->enqueue("order:{$order->id}:created", 'ORDER_CREATED', OutboxAggregateType::Order, $order->id,
            ['order_id' => $order->id, 'order_code' => $order->order_code]);

        CartItem::query()->where('cart_id', $cart->id)->delete();
        $cart->version += 1;
        $cart->updated_at = $now;
        $cart->save();

        return $order;
    }

    /** Append one timeline row; the actor's roles are snapshotted. */
    public function history(Order $order, ?User $actor, ?OrderStatus $from, OrderStatus $to, string $reasonCode, ?string $note, string $commandKey): OrderStatusHistory
    {
        $history = new OrderStatusHistory;
        $history->order_id = $order->id;
        $history->actor_id = $actor?->id;
        $history->actor_name_snapshot = $actor?->full_name ?? 'System';
        $history->actor_roles_snapshot = $actor?->roles()->get()->map(fn ($role): string => $role->slug->value)->values()->all();
        $history->from_status = $from;
        $history->to_status = $to;
        $history->reason_code = $reasonCode;
        $history->note = $note;
        $history->command_key = $commandKey;
        $history->created_at = $this->clock->now();
        $history->save();

        return $history;
    }

    public function consumeCoupon(Order $order, \Carbon\CarbonImmutable $now): void
    {
        $redemption = CouponRedemption::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($redemption !== null && $redemption->status === CouponRedemptionStatus::Reserved) {
            $redemption->status = CouponRedemptionStatus::Consumed;
            $redemption->consumed_at = $now;
            $redemption->updated_at = $now;
            $redemption->save();
        }
    }

    public function releaseCoupon(Order $order, \Carbon\CarbonImmutable $now): void
    {
        $redemption = CouponRedemption::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($redemption !== null && $redemption->status === CouponRedemptionStatus::Reserved) {
            $redemption->status = CouponRedemptionStatus::Released;
            $redemption->released_at = $now;
            $redemption->updated_at = $now;
            $redemption->save();
        }
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, ProductVariant>
     */
    private function variants(array $ids, bool $lock): Collection
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        $query = ProductVariant::withTrashed()->with(['historicalColor.historicalProduct.category.parent', 'historicalColor.thumbnail'])->whereKey($ids)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CartItem>  $items
     * @param  Collection<int, ProductVariant>  $variants
     * @return array{version: int, lines: list<array<string, mixed>>, problems: list<string>, subtotal: int, shipping_base_fee: int, merchandise_discount: int, shipping_discount: int, shipping_fee: int, total: int, coupon_code: ?string, coupon_error: ?string, fingerprint: ?string}
     */
    private function price(User $user, int $version, $items, Collection $variants, ?Address $address, PaymentMethod $method, ?string $couponCode, ?Coupon $lockedCoupon = null): array
    {
        $lines = [];
        $problems = [];
        $subtotal = 0;
        if ($items->count() > (int) config('store.limits.cart_lines')) {
            $problems[] = 'Giỏ hàng có quá nhiều dòng sản phẩm.';
        }
        foreach ($items as $item) {
            $variant = $variants->get($item->variant_id);
            $color = $variant?->historicalColor;
            $product = $color?->historicalProduct;
            $label = trim(($product?->name ?? 'Sản phẩm').' - '.($color?->color_name ?? '').' - size '.($variant?->size_value ?? ''));
            $available = $variant === null ? 0 : max(0, $variant->stock_qty - $variant->reserved_qty);
            if ($variant === null || ! $this->sellable($variant)) {
                $problems[] = "{$label} đã ngừng bán.";

                continue;
            }
            if ($item->quantity > $available || $item->quantity > (int) config('store.limits.variant_quantity')) {
                $problems[] = $available === 0 ? "{$label} đã hết hàng." : "{$label} chỉ còn {$available} đôi.";

                continue;
            }
            $unitPrice = Money::integer($variant->price_override ?? $product->promotion_price ?? $product->base_price);
            $lineTotal = Money::multiply($unitPrice, $item->quantity);
            $subtotal = Money::add($subtotal, $lineTotal);
            $lines[] = [
                'variant_id' => $variant->id,
                'product_name' => $product->name,
                'color_name' => $color->color_name,
                'size_value' => $variant->size_value,
                'thumbnail_url' => $this->thumbnailUrl($color->thumbnail),
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }
        $shippingBaseFee = $address === null || $lines === [] ? 0 : $this->shippingFee($subtotal, $address);
        $coupon = $couponCode === null || trim($couponCode) === '' ? null : ($lockedCoupon ?? $this->coupon($couponCode, false));
        $merchandiseDiscount = 0;
        $shippingDiscount = 0;
        $couponError = null;
        if ($couponCode !== null && trim($couponCode) !== '' && $coupon === null) {
            $couponError = 'Mã coupon không hợp lệ, đã hết hạn hoặc hết lượt sử dụng.';
        } elseif ($coupon !== null) {
            $couponProblem = $this->couponProblem($coupon, $user, $subtotal, $shippingBaseFee);
            if ($couponProblem !== null) {
                $couponError = $couponProblem;
            } elseif ($coupon->type === CouponType::Percentage) {
                $merchandiseDiscount = Money::percentage($subtotal, $coupon->value, $coupon->max_discount_amount);
            } elseif ($coupon->type === CouponType::Fixed) {
                $merchandiseDiscount = min($subtotal, $coupon->value);
            } elseif ($coupon->type === CouponType::FreeShipping) {
                $shippingDiscount = $shippingBaseFee;
            }
        }
        if ($coupon !== null && $merchandiseDiscount > 0) {
            $shares = Money::allocate($merchandiseDiscount, array_map(fn (array $line): array => [
                'id' => $line['variant_id'],
                'unit_no' => $line['variant_id'],
                'weight' => $line['line_total'],
            ], $lines));
            foreach ($lines as &$line) {
                $line['discount_amount'] = $shares[$line['variant_id']];
                $line['net_amount'] = Money::subtract($line['line_total'], $line['discount_amount']);
            }
            unset($line);
        } else {
            foreach ($lines as &$line) {
                $line['discount_amount'] = 0;
                $line['net_amount'] = $line['line_total'];
            }
            unset($line);
        }
        $shippingFee = Money::subtract($shippingBaseFee, $shippingDiscount);
        $total = Money::add(Money::subtract($subtotal, $merchandiseDiscount), $shippingFee);
        $fingerprint = $address === null || $lines === [] || $problems !== [] || $couponError !== null ? null : CanonicalPayload::fingerprint([
            'version' => $version,
            'address_id' => $address->id,
            'payment_method' => $method->value,
            'coupon_code' => $coupon?->code,
            'lines' => array_map(fn (array $line): array => [$line['variant_id'], $line['quantity'], $line['unit_price']], $lines),
            'merchandise_discount' => $merchandiseDiscount,
            'shipping_discount' => $shippingDiscount,
            'shipping_fee' => $shippingFee,
            'total' => $total,
        ]);

        return ['version' => $version, 'lines' => $lines, 'problems' => $problems, 'subtotal' => $subtotal, 'shipping_base_fee' => $shippingBaseFee,
            'merchandise_discount' => $merchandiseDiscount, 'shipping_discount' => $shippingDiscount, 'shipping_fee' => $shippingFee, 'total' => $total,
            'coupon_code' => $coupon?->code, 'coupon_error' => $couponError, 'fingerprint' => $fingerprint];
    }

    private function coupon(?string $code, bool $lock): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }
        $query = Coupon::query()->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))]);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function couponProblem(Coupon $coupon, User $user, int $subtotal, int $shippingFee): ?string
    {
        $now = $this->clock->now();
        if (! $coupon->is_active || ($coupon->starts_at !== null && $coupon->starts_at->greaterThan($now))
            || ($coupon->expires_at !== null && $coupon->expires_at->lessThan($now))) {
            return 'Mã coupon chưa có hiệu lực hoặc đã hết hạn.';
        }
        if ($subtotal < $coupon->min_order_amount) {
            return 'Đơn hàng chưa đạt giá trị tối thiểu của coupon.';
        }
        if ($coupon->usage_limit !== null && $coupon->couponRedemptions()->whereIn('status', [CouponRedemptionStatus::Reserved->value, CouponRedemptionStatus::Consumed->value])->count() >= $coupon->usage_limit) {
            return 'Coupon đã hết lượt sử dụng.';
        }
        if ($coupon->couponRedemptions()->where('user_id', $user->id)->whereIn('status', [CouponRedemptionStatus::Reserved->value, CouponRedemptionStatus::Consumed->value])->count() >= $coupon->per_user_limit) {
            return 'Bạn đã sử dụng hết lượt của coupon này.';
        }
        if ($coupon->type === CouponType::FreeShipping && $shippingFee === 0) {
            return 'Đơn hàng đã được miễn phí vận chuyển nên không áp dụng coupon này.';
        }

        return null;
    }

    /** Store policy: free from the threshold, otherwise HCM or other-province flat fee. */
    public function shippingFee(int $merchandiseNet, Address $address): int
    {
        if ($merchandiseNet >= (int) config('store.shipping.free_threshold')) {
            return 0;
        }

        return $address->province_code_snapshot === (string) config('store.shipping.hcm_province_code')
            ? (int) config('store.shipping.hcm_fee')
            : (int) config('store.shipping.other_fee');
    }

    private function sellable(ProductVariant $variant): bool
    {
        $color = $variant->historicalColor;
        $product = $color?->historicalProduct;
        $category = $product?->category;

        return $variant->is_active && ! $variant->trashed()
            && $color !== null && $color->is_active && ! $color->trashed()
            && $product !== null && $product->is_active && ! $product->trashed()
            && $category !== null && $category->is_active
            && ($category->parent_id === null || ($category->parent !== null && $category->parent->is_active));
    }

    private function thumbnailUrl(mixed $image): ?string
    {
        if ($image === null) {
            return null;
        }
        $url = $image->source_type === ProductImageSourceType::Upload ? Storage::disk('public')->url($image->storage_path) : $image->external_url;

        return is_string($url) && mb_strlen($url) <= 1000 ? $url : null;
    }
}
