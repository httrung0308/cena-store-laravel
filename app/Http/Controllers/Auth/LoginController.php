<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
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

    public function store(LoginRequest $request, AuthService $auth): RedirectResponse
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

        return $auth->isBackOffice($user)
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->intended(route('home'));
    }
}
