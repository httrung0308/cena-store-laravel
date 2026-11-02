<?php

namespace App\Http\Controllers\Account;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Account overview: profile summary, the default address and the latest orders.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isAdminAccount = $user->hasRole(RoleSlug::Admin);

        return view('account.index', [
            'user' => $user,
            'isAdminAccount' => $isAdminAccount,
            'defaultAddress' => $isAdminAccount ? null : $user->addresses()->where('is_default', true)->first(),
            'addressCount' => $isAdminAccount ? 0 : $user->addresses()->count(),
            'recentOrders' => $isAdminAccount ? new Collection : Order::query()->where('user_id', $user->id)->with('items')->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get(),
        ]);
    }
}
