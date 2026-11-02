<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Account overview: profile summary and the default address, linking to the address book.
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
        ]);
    }
}
