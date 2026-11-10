<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DemoPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DemoPaymentController extends Controller
{
    public function show(Request $request, string $orderCode): View
    {
        $order = $this->customerOrder($request, $orderCode);
        abort_unless($order->payment_method === PaymentMethod::PrepaidDemo, 404);
        $order->load(['payments' => fn ($query) => $query->where('method', PaymentMethod::PrepaidDemo->value)->orderByDesc('id')]);

        return view('account.orders.payment', ['order' => $order]);
    }

    public function complete(Request $request, string $orderCode, DemoPaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['result' => ['required', 'in:success,failure']]);
        $order = $this->customerOrder($request, $orderCode);
        try {
            $order = $payments->complete($order, $request->user(), $data['result'] === 'success');
        } catch (DomainException $exception) {
            return back()->withErrors(['payment' => collect($exception->errors)->flatten()->all() ?: [$exception->getMessage()]]);
        }

        return redirect()->route('orders.payment.show', $order->order_code)
            ->with('status', $data['result'] === 'success' ? 'Thanh toán mô phỏng thành công.' : 'Thanh toán mô phỏng thất bại. Bạn có thể thử lại.');
    }

    public function retry(Request $request, string $orderCode, DemoPaymentService $payments): RedirectResponse
    {
        $order = $this->customerOrder($request, $orderCode);
        try {
            $payments->retry($order, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['payment' => collect($exception->errors)->flatten()->all() ?: [$exception->getMessage()]]);
        }

        return redirect()->route('orders.payment.show', $order->order_code);
    }

    private function customerOrder(Request $request, string $orderCode): Order
    {
        return Order::query()->where('user_id', $request->user()->id)->where('order_code', $orderCode)->firstOrFail();
    }
}
