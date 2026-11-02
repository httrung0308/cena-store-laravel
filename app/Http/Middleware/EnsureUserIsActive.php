<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthService;
use App\Support\ExceptionRenderer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every web request. The user row is loaded fresh per request, so a locked account or a
 * bumped auth_version (password change, forced sign-out) ends the old session on its next request.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $session = $request->session();
        if (! $session->has(AuthService::SESSION_AUTH_VERSION)) {
            // Signed in from the "remember me" cookie: adopt the current version for this new session.
            $session->put(AuthService::SESSION_AUTH_VERSION, $user->auth_version);
        }

        if ($user->is_active && (int) $session->get(AuthService::SESSION_AUTH_VERSION) === $user->auth_version) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();

        if (ExceptionRenderer::wantsJson($request)) {
            abort(401);
        }

        return redirect()->route('login')->withErrors(['email' => 'Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.']);
    }
}
