<?php

namespace App\Http\Middleware;

use App\Enums\RoleSlug;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNonAdminShopping
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->hasRole(RoleSlug::Admin), 403, 'Tài khoản quản trị không dùng để mua sắm. Vui lòng dùng tài khoản khách hàng.');

        return $next($request);
    }
}
