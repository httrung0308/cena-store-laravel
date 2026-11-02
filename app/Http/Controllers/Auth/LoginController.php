<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthService $auth, CartService $carts): RedirectResponse
    {
        $key = $request->throttleKey();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($key).' giây.',
            ]);
        }

        $user = $auth->attempt($request->validated('email'), $request->validated('password'), $request->boolean('remember'));
        if ($user === null) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(AuthService::SESSION_AUTH_VERSION, $user->auth_version);
        if ($user->hasRole(RoleSlug::Admin)) {
            // Admin accounts never shop: no guest-cart merge and never sent back to a shopping page.
            $intended = (string) $request->session()->pull('url.intended', '');
            $path = (string) parse_url($intended, PHP_URL_PATH);

            return $intended !== '' && ! preg_match('#^/(checkout|cart|account/orders|account/reviews)(/|$)#', $path)
                ? redirect()->to($intended)
                : redirect()->route('admin.dashboard');
        }
        $carts->mergeGuestCart($user, $request->session());

        return $auth->isBackOffice($user)
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->intended(route('home'));
    }
}
