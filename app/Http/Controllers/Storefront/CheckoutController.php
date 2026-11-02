<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutOrderRequest;
use App\Models\Address;
use App\Services\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Checkout page (quote for the chosen address) and COD order placement.
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
        $chosenId = (int) $request->query('address', old('address_id', 0));
        $address = $selectable->firstWhere('id', $chosenId) ?? $selectable->first();
        $quote = $this->checkout->quote($user, $address);
        if ($quote['lines'] === [] && $quote['problems'] === []) {
            return redirect()->route('shoes.index')->with('status', 'Giỏ hàng đang trống.');
        }

        return view('storefront.checkout.show', [
            'quote' => $quote,
            'addresses' => $addresses,
            'address' => $address,
            'currentVersion' => $currentVersion,
            'checkoutKey' => old('checkout_key', (string) Str::uuid()),
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
            );
        } catch (DomainException $exception) {
            $messages = collect($exception->errors)->flatten()->all();

            return redirect()->route('checkout.show', ['address' => $request->validated('address_id')])
                ->withErrors(['checkout' => $messages === [] ? [$exception->getMessage()] : $messages])
                ->withInput($request->only('customer_note'));
        }

        return redirect()->route('orders.show', $order->order_code)->with('status', 'Đặt hàng thành công! Cửa hàng sẽ liên hệ xác nhận đơn.');
    }
}
