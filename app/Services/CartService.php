<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\ProductImageSourceType;
use App\Exceptions\DomainException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Server-side cart (WF-09). A guest cart is found by the hash of a random token kept in the session;
 * a signed-in user's cart by user_id. Nothing about the owner, price or stock is taken from the client.
 * The cart never reserves stock: availability is checked here and again at checkout.
 */
final class CartService
{
    public const SESSION_GUEST_TOKEN = 'cart.guest_token';

    public const SESSION_NOTICES = 'cart.notices';

    private const GUEST_CART_DAYS = 30;

    public function __construct(private readonly BusinessClock $clock) {}

    public function find(?User $user, Session $session): ?Cart
    {
        if ($user !== null) {
            return Cart::query()->where('user_id', $user->id)->first();
        }
        $token = $session->get(self::SESSION_GUEST_TOKEN);

        return is_string($token) ? Cart::query()->where('guest_token_hash', hash('sha256', $token))->first() : null;
    }

    /**
     * Adds quantity pairs of one variant. The total in the cart may not exceed min(available, 10).
     */
    public function add(?User $user, Session $session, int $variantId, int $quantity, ?int $version): Cart
    {
        return $this->mutate($user, $session, $version, true, function (Cart $cart) use ($variantId, $quantity): void {
            $variant = $this->variant($variantId);
            if ($variant === null || ! $this->isSellable($variant)) {
                throw new DomainException(ErrorCode::ProductUnavailable, ['variant_id' => ['Sản phẩm hiện không thể mua.']]);
            }
            $item = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variant->id)->lockForUpdate()->first();
            if ($item === null && CartItem::query()->where('cart_id', $cart->id)->count() >= (int) config('store.limits.cart_lines')) {
                throw new DomainException(ErrorCode::InvalidQuantity, ['variant_id' => ['Giỏ hàng tối đa '.config('store.limits.cart_lines').' sản phẩm khác nhau.']]);
            }
            $this->assertWithinLimit($variant, ($item?->quantity ?? 0) + $quantity);

            $item ??= $this->newItem($cart, $variant);
            $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
            $item->updated_at = $this->clock->now();
            $item->save();
        });
    }

    /**
     * Sets an absolute quantity (1..10). Lowering is always allowed; raising must fit the stock still available.
     */
    public function update(?User $user, Session $session, int $itemId, int $quantity, int $version): Cart
    {
        return $this->mutate($user, $session, $version, false, function (Cart $cart) use ($itemId, $quantity): void {
            $item = $this->ownedItem($cart, $itemId);
            if ($quantity > $item->quantity) {
                $variant = $this->variant($item->variant_id);
                if ($variant === null || ! $this->isSellable($variant)) {
                    throw new DomainException(ErrorCode::ProductUnavailable, ['quantity' => ['Sản phẩm hiện không thể mua.']]);
                }
                $this->assertWithinLimit($variant, $quantity);
            }
            $item->quantity = $quantity;
            $item->updated_at = $this->clock->now();
            $item->save();
        });
    }

    public function remove(?User $user, Session $session, int $itemId, int $version): Cart
    {
        return $this->mutate($user, $session, $version, false, function (Cart $cart) use ($itemId): void {
            $this->ownedItem($cart, $itemId)->delete();
        });
    }

    public function clear(?User $user, Session $session, int $version): Cart
    {
        return $this->mutate($user, $session, $version, false, function (Cart $cart): void {
            CartItem::query()->where('cart_id', $cart->id)->delete();
        });
    }

    /**
     * Moves the guest cart into the user's cart after sign-in. Quantities are added per variant and capped at
     * min(available, 10); lines that cannot be bought are dropped. Returns the notices to show the customer.
     * Runs in one transaction: on failure both carts stay as they were. A repeated call finds no guest cart
     * and does nothing.
     *
     * @return list<string>
     */
    public function mergeGuestCart(User $user, Session $session): array
    {
        $token = $session->get(self::SESSION_GUEST_TOKEN);
        if (! is_string($token)) {
            return [];
        }
        $hash = hash('sha256', $token);

        $notices = DB::transaction(function () use ($user, $hash): array {
            $guestId = Cart::query()->where('guest_token_hash', $hash)->value('id');
            if ($guestId === null) {
                return [];
            }
            $userCart = $this->userCart($user);
            $carts = Cart::query()->whereKey([$guestId, $userCart->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $guest = $carts->get($guestId);
            if ($guest === null) {
                return [];
            }
            $userCart = $carts->get($userCart->id);

            $notices = [];
            $lines = CartItem::query()->where('cart_id', $userCart->id)->lockForUpdate()->get()->keyBy('variant_id');
            $guestItems = CartItem::query()->where('cart_id', $guest->id)->orderBy('variant_id')->get();
            foreach ($guestItems as $guestItem) {
                $variant = $this->variant($guestItem->variant_id);
                $label = $variant === null ? 'Một sản phẩm' : $this->label($variant);
                if ($variant === null || ! $this->isSellable($variant) || $this->limitFor($variant) === 0) {
                    $notices[] = "{$label} không còn bán hoặc đã hết hàng nên không được chuyển vào giỏ.";

                    continue;
                }
                $existing = $lines->get($variant->id);
                if ($existing === null && $lines->count() >= (int) config('store.limits.cart_lines')) {
                    $notices[] = "{$label} không được thêm vì giỏ đã đủ ".config('store.limits.cart_lines').' sản phẩm.';

                    continue;
                }
                $wanted = ($existing?->quantity ?? 0) + $guestItem->quantity;
                $allowed = min($wanted, $this->limitFor($variant));
                if ($allowed < $wanted) {
                    $notices[] = "{$label}: chỉ giữ được {$allowed} đôi trong giỏ.";
                }
                $item = $existing ?? $this->newItem($userCart, $variant);
                $item->quantity = max($allowed, $existing?->quantity ?? 0);
                $item->updated_at = $this->clock->now();
                $item->save();
                $lines->put($variant->id, $item);
            }

            $guest->delete();
            if ($guestItems->isNotEmpty()) {
                $this->bump($userCart);
            }

            return $notices;
        });

        $session->forget(self::SESSION_GUEST_TOKEN);
        if ($notices !== []) {
            $session->flash(self::SESSION_NOTICES, $notices);
        }

        return $notices;
    }

    /**
     * Cart JSON for the drawer: server prices, availability and eligibility only.
     *
     * @return array{version: int, count: int, subtotal: int, items: list<array<string, mixed>>, notices: list<string>, checkout_note: string}
     */
    public function present(?Cart $cart, array $notices = []): array
    {
        $items = [];
        $count = 0;
        $subtotal = 0;
        if ($cart !== null) {
            $cartItems = CartItem::query()->where('cart_id', $cart->id)->orderBy('id')->get();
            $variants = ProductVariant::withTrashed()->with(['historicalColor.historicalProduct', 'historicalColor.thumbnail'])
                ->whereKey($cartItems->pluck('variant_id'))->get()->keyBy('id');
            foreach ($cartItems as $item) {
                $variant = $variants->get($item->variant_id);
                $color = $variant?->historicalColor;
                $product = $color?->historicalProduct;
                $sellable = $variant !== null && $this->isSellable($variant);
                $available = $variant === null ? 0 : $this->available($variant);
                $price = $variant === null ? 0 : $this->unitPrice($variant);
                $lineTotal = Money::multiply($price, $item->quantity);
                $eligible = $sellable && $item->quantity <= $available;
                $count += $item->quantity;
                if ($eligible) {
                    $subtotal = Money::add($subtotal, $lineTotal);
                }
                $items[] = [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'product_name' => $product?->name ?? 'Sản phẩm',
                    'product_url' => $product !== null && Route::has('shoes.show') ? route('shoes.show', $product->slug) : null,
                    'color_name' => $color?->color_name,
                    'size_value' => $variant?->size_value,
                    'thumbnail_url' => $this->thumbnailUrl($color?->thumbnail),
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                    'quantity' => $item->quantity,
                    'available' => $available,
                    'max_quantity' => $variant === null ? 0 : $this->limitFor($variant),
                    'eligible' => $eligible,
                    'notice' => match (true) {
                        ! $sellable => 'Sản phẩm đã ngừng bán.',
                        $available === 0 => 'Sản phẩm đã hết hàng.',
                        $item->quantity > $available => "Chỉ còn {$available} đôi.",
                        default => null,
                    },
                ];
            }
        }

        return [
            'version' => $cart?->version ?? 0,
            'count' => $count,
            'subtotal' => $subtotal,
            'items' => $items,
            'notices' => array_values($notices),
            'checkout_note' => 'Giá và tồn kho được kiểm tra lại khi đặt hàng.',
        ];
    }

    /**
     * @param  callable(Cart): void  $change
     */
    private function mutate(?User $user, Session $session, ?int $version, bool $createIfMissing, callable $change): Cart
    {
        return DB::transaction(function () use ($user, $session, $version, $createIfMissing, $change): Cart {
            [$cart, $justCreated] = $this->lockedCart($user, $session, $createIfMissing);
            if ($cart === null) {
                throw new DomainException(ErrorCode::NotFound);
            }
            // A client that has never seen a cart sends version 0; that matches a cart created by this request.
            if ($version !== null && ! ($version === 0 && $justCreated) && $cart->version !== $version) {
                throw new DomainException(ErrorCode::CartStale);
            }
            $change($cart);
            $this->bump($cart);

            return $cart;
        });
    }

    /**
     * @return array{0: ?Cart, 1: bool} the locked cart and whether this request created it
     */
    private function lockedCart(?User $user, Session $session, bool $create): array
    {
        if ($user !== null) {
            $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($cart !== null || ! $create) {
                return [$cart, false];
            }

            return [$this->lockCreated(fn (): Cart => $this->userCart($user)), true];
        }

        $token = $session->get(self::SESSION_GUEST_TOKEN);
        if (is_string($token)) {
            $cart = Cart::query()->where('guest_token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if ($cart !== null || ! $create) {
                return [$cart, false];
            }
        } elseif (! $create) {
            return [null, false];
        }

        $token = Str::random(64);
        $session->put(self::SESSION_GUEST_TOKEN, $token);

        return [$this->lockCreated(function () use ($token): Cart {
            $cart = new Cart;
            $cart->user_id = null;
            $cart->guest_token_hash = hash('sha256', $token);
            $cart->version = 1;
            $cart->expires_at = $this->clock->now()->addDays(self::GUEST_CART_DAYS);
            $cart->created_at = $this->clock->now();
            $cart->updated_at = $this->clock->now();
            $cart->save();

            return $cart;
        }), true];
    }

    /** @param callable(): Cart $create */
    private function lockCreated(callable $create): Cart
    {
        $cart = $create();

        return Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
    }

    private function userCart(User $user): Cart
    {
        $cart = Cart::query()->where('user_id', $user->id)->first();
        if ($cart !== null) {
            return $cart;
        }
        try {
            $cart = new Cart;
            $cart->user_id = $user->id;
            $cart->version = 1;
            $cart->created_at = $this->clock->now();
            $cart->updated_at = $this->clock->now();
            $cart->save();

            return $cart;
        } catch (UniqueConstraintViolationException) {
            return Cart::query()->where('user_id', $user->id)->firstOrFail();
        }
    }

    private function bump(Cart $cart): void
    {
        $cart->version = $cart->version + 1;
        $cart->updated_at = $this->clock->now();
        if ($cart->user_id === null) {
            $cart->expires_at = $this->clock->now()->addDays(self::GUEST_CART_DAYS);
        }
        $cart->save();
    }

    private function newItem(Cart $cart, ProductVariant $variant): CartItem
    {
        $item = new CartItem;
        $item->cart_id = $cart->id;
        $item->variant_id = $variant->id;
        $item->quantity = 0;
        $item->created_at = $this->clock->now();

        return $item;
    }

    /** Another cart's item (or one already removed) is reported as not found. */
    private function ownedItem(Cart $cart, int $itemId): CartItem
    {
        $item = CartItem::query()->where('cart_id', $cart->id)->whereKey($itemId)->lockForUpdate()->first();
        if ($item === null) {
            throw new DomainException(ErrorCode::NotFound);
        }

        return $item;
    }

    private function variant(int $variantId): ?ProductVariant
    {
        return ProductVariant::withTrashed()->with('historicalColor.historicalProduct')->find($variantId);
    }

    private function isSellable(ProductVariant $variant): bool
    {
        $color = $variant->historicalColor;
        $product = $color?->historicalProduct;

        return $variant->is_active && ! $variant->trashed()
            && $color !== null && $color->is_active && ! $color->trashed()
            && $product !== null && $product->is_active && ! $product->trashed();
    }

    private function available(ProductVariant $variant): int
    {
        return max(0, $variant->stock_qty - $variant->reserved_qty);
    }

    private function limitFor(ProductVariant $variant): int
    {
        return min($this->available($variant), (int) config('store.limits.variant_quantity'));
    }

    private function assertWithinLimit(ProductVariant $variant, int $wanted): void
    {
        $limit = $this->limitFor($variant);
        if ($limit === 0) {
            throw new DomainException(ErrorCode::StockUnavailable, ['quantity' => ['Size này đã hết hàng.']]);
        }
        if ($wanted > $limit) {
            throw new DomainException(ErrorCode::StockUnavailable, ['quantity' => ["Mỗi size chỉ có thể có tối đa {$limit} đôi trong giỏ."]]);
        }
    }

    /** Variant price, else promotion price, else base price (integer VND). */
    private function unitPrice(ProductVariant $variant): int
    {
        $product = $variant->historicalColor?->historicalProduct;

        return Money::integer($variant->price_override ?? $product?->promotion_price ?? $product?->base_price ?? 0);
    }

    private function label(ProductVariant $variant): string
    {
        $color = $variant->historicalColor;

        return trim(($color?->historicalProduct?->name ?? 'Sản phẩm').' - '.($color?->color_name ?? '').' - size '.$variant->size_value);
    }

    private function thumbnailUrl(mixed $image): ?string
    {
        if ($image === null) {
            return null;
        }

        return $image->source_type === ProductImageSourceType::Upload
            ? Storage::disk('public')->url($image->storage_path)
            : $image->external_url;
    }
}
