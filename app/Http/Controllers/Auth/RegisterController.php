<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, AuthService $auth): RedirectResponse
    {
        $user = $auth->register($request->registration());

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(AuthService::SESSION_AUTH_VERSION, $user->auth_version);

        return redirect()->intended(route('home'))->with('status', 'Đăng ký thành công. Chào mừng bạn đến với Cena!');
    }
}
