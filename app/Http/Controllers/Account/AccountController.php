<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Account overview: profile summary, the default address and the latest orders.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('account.index', [
            'user' => $user,
            'defaultAddress' => $user->addresses()->where('is_default', true)->first(),
            'addressCount' => $user->addresses()->count(),
            'recentOrders' => Order::query()->where('user_id', $user->id)->with('items')->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get(),
        ]);
    }
}
