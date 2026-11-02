<?php

namespace App\Http\Middleware;

use App\Enums\RoleSlug;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the request only for an active, non-deleted user who currently holds one of the given roles.
 * Usage: ->middleware(['auth', 'role:admin,staff']). Roles are re-read on every request, so a revoked
 * role or a locked account takes effect on the next request.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role): string => RoleSlug::from($role)->value, $roles);
        $user = $request->user();

        abort_unless(
            $allowed !== []
                && $user instanceof User
                && $user->is_active
                && ! $user->trashed()
                && $user->roles()->whereIn('slug', $allowed)->exists(),
            403,
        );

        return $next($request);
    }
}
