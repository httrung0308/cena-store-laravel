<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\RoleSlug;
use App\Exceptions\DomainException;
use App\Models\PasswordResetChallenge;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Account changes that affect access (WF-03). Every write locks the admin role row first as a mutex, so two
 * concurrent requests can never remove or lock the last active Admin. Access changes bump auth_version, which
 * signs the user out of every existing session on their next request.
 */
final class UserAccessService
{
    public function __construct(private readonly BusinessClock $clock) {}

    /** @param array{full_name: string, phone: ?string} $profile */
    public function updateProfile(User $target, array $profile): User
    {
        return DB::transaction(function () use ($target, $profile): User {
            $user = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $user->full_name = $profile['full_name'];
            $user->phone = $profile['phone'];
            $user->updated_at = $this->clock->now();
            $user->save();

            return $user;
        });
    }

    public function changePassword(User $target, string $current, string $new): User
    {
        return DB::transaction(function () use ($target, $current, $new): User {
            $user = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($current, $user->password)) {
                throw new DomainException(ErrorCode::ValidationFailed, ['current_password' => ['Mật khẩu hiện tại không đúng.']]);
            }
            $user->password = $new;
            $user->auth_version += 1;
            $user->updated_at = $this->clock->now();
            $user->save();
            PasswordResetChallenge::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);

            return $user;
        });
    }

    public function setActive(User $actor, User $target, bool $active): User
    {
        return DB::transaction(function () use ($actor, $target, $active): User {
            $adminRole = $this->lockAdminRole();
            $user = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $active && $user->id === $actor->id) {
                throw new DomainException(ErrorCode::Forbidden, ['user' => ['Bạn không thể tự khóa tài khoản của mình.']]);
            }
            if (! $active && $this->isActiveAdmin($user, $adminRole) && $this->otherActiveAdmins($user, $adminRole) === 0) {
                throw new DomainException(ErrorCode::Forbidden, ['user' => ['Không thể khóa Admin cuối cùng đang hoạt động.']]);
            }
            if ($user->is_active !== $active) {
                $user->is_active = $active;
                $user->auth_version += 1;
                $user->updated_at = $this->clock->now();
                $user->save();
            }

            return $user;
        });
    }

    /** @param list<RoleSlug> $roles */
    public function setRoles(User $actor, User $target, array $roles): User
    {
        if ($roles === []) {
            throw new DomainException(ErrorCode::ValidationFailed, ['roles' => ['Người dùng cần ít nhất một vai trò.']]);
        }

        return DB::transaction(function () use ($actor, $target, $roles): User {
            $adminRole = $this->lockAdminRole();
            $user = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $slugs = array_values(array_unique(array_map(fn (RoleSlug $role): string => $role->value, $roles)));
            $wanted = Role::query()->whereIn('slug', $slugs)->get()->keyBy('id');
            if ($wanted->count() !== count($slugs)) {
                throw new DomainException(ErrorCode::NotFound, ['roles' => ['Vai trò chưa được khởi tạo trong hệ thống.']]);
            }
            $current = UserRole::query()->where('user_id', $user->id)->lockForUpdate()->get()->keyBy('role_id');
            $losesAdmin = $current->has($adminRole->id) && ! $wanted->has($adminRole->id);
            if ($losesAdmin && $user->id === $actor->id) {
                throw new DomainException(ErrorCode::Forbidden, ['roles' => ['Bạn không thể tự bỏ quyền Admin của mình.']]);
            }
            if ($losesAdmin && $user->is_active && $this->otherActiveAdmins($user, $adminRole) === 0) {
                throw new DomainException(ErrorCode::Forbidden, ['roles' => ['Không thể bỏ quyền của Admin cuối cùng đang hoạt động.']]);
            }
            $removed = $current->keys()->diff($wanted->keys());
            $added = $wanted->keys()->diff($current->keys());
            if ($removed->isEmpty() && $added->isEmpty()) {
                return $user;
            }
            UserRole::query()->where('user_id', $user->id)->whereIn('role_id', $removed->all())->delete();
            $now = $this->clock->now();
            foreach ($added as $roleId) {
                $assignment = new UserRole;
                $assignment->user_id = $user->id;
                $assignment->role_id = $roleId;
                $assignment->assigned_by = $actor->id;
                $assignment->assigned_at = $now;
                $assignment->save();
            }
            $user->auth_version += 1;
            $user->updated_at = $now;
            $user->save();

            return $user;
        });
    }

    private function lockAdminRole(): Role
    {
        return Role::query()->where('slug', RoleSlug::Admin->value)->lockForUpdate()->firstOrFail();
    }

    private function isActiveAdmin(User $user, Role $adminRole): bool
    {
        return $user->is_active && UserRole::query()->where('user_id', $user->id)->where('role_id', $adminRole->id)->exists();
    }

    private function otherActiveAdmins(User $user, Role $adminRole): int
    {
        return UserRole::query()->where('role_id', $adminRole->id)->where('user_id', '!=', $user->id)
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))
            ->count();
    }
}
