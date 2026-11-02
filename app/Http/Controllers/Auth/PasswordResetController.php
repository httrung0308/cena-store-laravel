<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Forgot password → 6-digit code by email → new password. The challenge token and the browser binding live
 * only in the session; nothing secret ever appears in a URL.
 */
class PasswordResetController extends Controller
{
    private const SESSION = 'password_reset';

    public function __construct(private readonly PasswordResetService $resets) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email']));
        $key = 'password-reset:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau ít phút.'])->withInput();
        }
        RateLimiter::hit($key, 900);

        return $this->issue($request, $email);
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION.'.email');
        if (! is_string($email)) {
            return redirect()->route('password.request');
        }

        return $this->issue($request, $email);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $token = $request->session()->get(self::SESSION.'.challenge');
        if (! is_string($token)) {
            return redirect()->route('password.request');
        }
        if ($request->session()->get(self::SESSION.'.verified') === true) {
            return view('auth.reset-password');
        }

        return view('auth.verify-reset', [
            'email' => $request->session()->get(self::SESSION.'.email'),
            'attemptsLeft' => $this->resets->attemptsLeft($token),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $otp = (string) $request->validate(['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/D']], ['otp.regex' => 'Mã gồm đúng 6 chữ số.'])['otp'];
        [$token, $binding] = $this->binding($request);
        if ($token === null || ! $this->resets->verify($token, $binding, $otp)) {
            return back()->withErrors(['otp' => 'Mã không đúng, đã hết hạn hoặc đã hết lượt thử. Bạn có thể gửi lại mã mới.']);
        }
        $request->session()->put(self::SESSION.'.verified', true);

        return redirect()->route('password.reset');
    }

    public function update(Request $request): RedirectResponse
    {
        $password = (string) $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed', RegisterRequest::passwordFitsHash(...)],
        ], ['password.min' => 'Mật khẩu cần ít nhất 8 ký tự.', 'password.confirmed' => 'Mật khẩu nhập lại không khớp.'])['password'];
        [$token, $binding] = $this->binding($request);
        if ($token === null || $request->session()->get(self::SESSION.'.verified') !== true) {
            return redirect()->route('password.request');
        }
        try {
            $this->resets->reset($token, $binding, $password);
        } catch (DomainException $exception) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('password.request')->withErrors($exception->errors);
        }
        $request->session()->forget(self::SESSION);
        $request->session()->regenerate();

        return redirect()->route('login')->with('status', 'Đã đặt lại mật khẩu. Vui lòng đăng nhập bằng mật khẩu mới.');
    }

    private function issue(Request $request, string $email): RedirectResponse
    {
        $session = $request->session();
        $result = $this->resets->request($email, $session->get(self::SESSION.'.challenge'), $session->get(self::SESSION.'.binding'));
        $session->put(self::SESSION, ['email' => $email, 'challenge' => $result['challenge'], 'binding' => $result['binding'], 'verified' => false]);

        return redirect()->route('password.reset')->with('status', $result['cooldown'] > 0
            ? "Vui lòng chờ {$result['cooldown']} giây trước khi gửi lại mã."
            : 'Nếu email có tài khoản, mã gồm 6 chữ số đã được gửi tới hộp thư. Mã có hiệu lực 15 phút.');
    }

    /** @return array{0: ?string, 1: string} */
    private function binding(Request $request): array
    {
        $token = $request->session()->get(self::SESSION.'.challenge');
        $binding = $request->session()->get(self::SESSION.'.binding');

        return is_string($token) && is_string($binding) ? [$token, $binding] : [null, ''];
    }
}
