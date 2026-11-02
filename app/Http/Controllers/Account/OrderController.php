<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The signed-in customer's own orders. Another user's order code is a 404, never a 403.
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
            ->with(['items.units', 'statusHistories' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->firstOrFail();

        return view('account.orders.show', ['order' => $order]);
    }
}
