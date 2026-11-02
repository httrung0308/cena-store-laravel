<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelOrderRequest;
use App\Models\Order;
use App\Services\OrderWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in customer's own orders and self-cancel while PENDING. Another user's order code is a 404, never a 403.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()->where('user_id', $request->user()->id)
            ->withCount('items')->with('items')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(10);

        return view('account.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, string $orderCode): View
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('order_code', $orderCode)
            ->with(['items.units.review', 'statusHistories' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->firstOrFail();

        return view('account.orders.show', ['order' => $order]);
    }

    public function cancel(CancelOrderRequest $request, string $orderCode, OrderWorkflowService $workflow): RedirectResponse
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('order_code', $orderCode)->firstOrFail();
        try {
            $workflow->cancel($order, $request->user(), (string) $request->validated('expected_status'), (string) $request->validated('reason'), true);
        } catch (DomainException $exception) {
            return back()->withErrors(['order' => 'Đơn không còn ở trạng thái có thể tự hủy. Vui lòng liên hệ cửa hàng.']);
        }

        return redirect()->route('orders.show', $order->order_code)->with('status', 'Đã hủy đơn hàng.');
    }
}
