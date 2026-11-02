<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\Cart;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\BusinessClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Account sign-up and sign-in rules (WF-01). The session key below stores the user's auth_version
 * so EnsureUserIsActive can end old sessions after a lock or credential change.
 */
final class AuthService
{
    public const SESSION_AUTH_VERSION = 'auth_version';

    public function __construct(private readonly BusinessClock $clock) {}

    /**
     * Creates an active customer with an empty cart in one transaction.
     * Never takes a role or account flag from the request.
     *
     * @param  array{full_name: string, email: string, phone: ?string, password: string}  $data
     */
    public function register(array $data): User
    {
        try {
            return DB::transaction(function () use ($data): User {
                $role = Role::query()->where('slug', RoleSlug::Customer)->first();
                if ($role === null) {
                    throw new LogicException('The customer role is missing. Run RolesSeeder before accepting sign-ups.');
                }
                $now = $this->clock->now();

                $user = new User;
                $user->full_name = $data['full_name'];
                $user->email = $data['email'];
                $user->phone = $data['phone'];
                $user->password = $data['password'];
                $user->is_active = true;
                $user->auth_version = 1;
                $user->created_at = $now;
                $user->updated_at = $now;
                $user->save();

                $assignment = new UserRole;
                $assignment->user_id = $user->id;
                $assignment->role_id = $role->id;
                $assignment->assigned_by = null;
                $assignment->assigned_at = $now;
                $assignment->save();

                $cart = new Cart;
                $cart->user_id = $user->id;
                $cart->version = 1;
                $cart->created_at = $now;
                $cart->updated_at = $now;
                $cart->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email này đã được đăng ký.']);
        }
    }

    /**
     * Checks credentials for an active, non-deleted account and signs it in.
     * Returns null on bad credentials or a locked account (same message for both).
     */
    public function attempt(string $email, string $password, bool $remember): ?User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true], $remember)) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => $this->clock->now()])->saveQuietly();

        return $user;
    }

    public function isBackOffice(User $user): bool
    {
        return $user->roles()->whereIn('slug', [RoleSlug::Admin->value, RoleSlug::Staff->value])->exists();
    }
}
