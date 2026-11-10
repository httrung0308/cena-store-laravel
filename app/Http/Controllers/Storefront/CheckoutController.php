<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\DomainException;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutOrderRequest;
use App\Models\Address;
use App\Services\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Checkout page (quote for the chosen address) and order placement.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->orderBy('id')->get();
        $currentVersion = (string) config('store.shipping.dataset_version');
        $selectable = $addresses->filter(fn (Address $address): bool => $address->dataset_version === $currentVersion);
        $chosenId = (int) $request->query('address', $request->query('address_id', old('address_id', 0)));
        $address = $selectable->firstWhere('id', $chosenId) ?? $selectable->first();
        $method = PaymentMethod::tryFrom((string) $request->query('payment_method', PaymentMethod::Cod->value)) ?? PaymentMethod::Cod;
        $couponCode = $request->query('coupon_code');
        $quote = $this->checkout->quote($user, $address, $method, is_string($couponCode) ? $couponCode : null);
        if ($quote['lines'] === [] && $quote['problems'] === []) {
            return redirect()->route('shoes.index')->with('status', 'Giỏ hàng đang trống.');
        }

        return view('storefront.checkout.show', [
            'quote' => $quote,
            'addresses' => $addresses,
            'address' => $address,
            'currentVersion' => $currentVersion,
            'checkoutKey' => old('checkout_key', (string) Str::uuid()),
            'paymentMethod' => $method,
            'couponCode' => is_string($couponCode) ? $couponCode : null,
        ]);
    }

    public function store(CheckoutOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->checkout->place(
                $request->user(),
                (int) $request->validated('address_id'),
                (string) $request->validated('checkout_key'),
                (string) $request->validated('fingerprint'),
                $request->validated('customer_note'),
                PaymentMethod::from((string) $request->validated('payment_method')),
                $request->validated('coupon_code'),
            );
        } catch (DomainException $exception) {
            $messages = collect($exception->errors)->flatten()->all();

            return redirect()->route('checkout.show', ['address' => $request->validated('address_id'), 'payment_method' => $request->validated('payment_method'), 'coupon_code' => $request->validated('coupon_code')])
                ->withErrors(['checkout' => $messages === [] ? [$exception->getMessage()] : $messages])
                ->withInput($request->only('customer_note'));
        }

        if ($order->payment_method === PaymentMethod::PrepaidDemo) {
            return redirect()->route('orders.payment.show', $order->order_code)->with('status', 'Đơn hàng đã được tạo. Hoàn tất thanh toán mô phỏng để tiếp tục.');
        }

        return redirect()->route('orders.show', $order->order_code)->with('status', 'Đặt hàng thành công! Cửa hàng sẽ liên hệ xác nhận đơn.');
    }
}
