<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ProfileRequest;
use App\Http\Requests\Admin\UserRolesRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\UserAccessService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-only user management: search, profile edit, lock/unlock and role assignment.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserAccessService $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(RoleSlug::class)],
            'active' => ['nullable', 'in:0,1'],
        ]);
        $users = User::query()->with('roles')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($inner) => $inner->whereRaw("full_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->whereHas('roles', fn ($roles) => $roles->where('slug', $role)))
            ->when(isset($filters['active']), fn ($query) => $query->where('is_active', $filters['active'] === '1'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'filters' => $filters]);
    }

    public function show(User $user): View
    {
        $user->load('roles');

        return view('admin.users.show', [
            'user' => $user,
            'orderCount' => Order::query()->where('user_id', $user->id)->count(),
            'addressCount' => $user->addresses()->count(),
        ]);
    }

    public function update(ProfileRequest $request, User $user): RedirectResponse
    {
        $this->access->updateProfile($user, $request->profile());

        return redirect()->route('admin.users.show', $user)->with('status', 'Đã cập nhật thông tin.');
    }

    public function active(Request $request, User $user): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        return $this->attempt(fn () => $this->access->setActive($request->user(), $user, (bool) $active), $user,
            (bool) $active ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }

    public function roles(UserRolesRequest $request, User $user): RedirectResponse
    {
        return $this->attempt(fn () => $this->access->setRoles($request->user(), $user, $request->roles()), $user, 'Đã cập nhật vai trò.');
    }

    private function attempt(Closure $change, User $user, string $message): RedirectResponse
    {
        try {
            $change();
        } catch (DomainException $exception) {
            return back()->withErrors($exception->errors === [] ? ['user' => $exception->getMessage()] : $exception->errors);
        }

        return redirect()->route('admin.users.show', $user)->with('status', $message);
    }
}
