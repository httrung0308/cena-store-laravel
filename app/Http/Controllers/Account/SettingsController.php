<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PasswordRequest;
use App\Http\Requests\Account\ProfileRequest;
use App\Services\UserAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user's own profile (name, phone) and password. A new password signs out every session.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly UserAccessService $access) {}

    public function edit(): View
    {
        return view('account.settings', ['user' => Auth::user()]);
    }

    public function updateProfile(ProfileRequest $request): RedirectResponse
    {
        $this->access->updateProfile($request->user(), $request->profile());

        return redirect()->route('account.settings')->with('status', 'Đã cập nhật thông tin cá nhân.');
    }

    public function updatePassword(PasswordRequest $request): RedirectResponse
    {
        try {
            $this->access->changePassword($request->user(), (string) $request->validated('current_password'), (string) $request->validated('password'));
        } catch (DomainException $exception) {
            return back()->withErrors($exception->errors);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.');
    }
}
