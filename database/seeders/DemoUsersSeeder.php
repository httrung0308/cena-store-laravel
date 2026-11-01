<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Cart;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DemoUsersSeeder extends Seeder
{
    public function run(BusinessClock $clock): void
    {
        if (config('store.demo.enabled') !== true) {
            return;
        }
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo accounts may only be seeded in local or testing.');
        }
        $password = config('store.demo.password');
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            throw new LogicException('Configure a private demo password of 12 to 72 bytes before enabling demo accounts.');
        }
        $accounts = $this->accounts();

        DB::transaction(function () use ($clock, $password, $accounts): void {
            $roles = Role::query()->whereIn('slug', array_map(fn (RoleSlug $role): string => $role->value, RoleSlug::cases()))
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn (Role $role): string => $role->slug->value);
            if ($roles->count() !== count(RoleSlug::cases())) {
                throw new LogicException('Seed the canonical roles before seeding demo accounts.');
            }
            foreach (RoleSlug::cases() as $slug) {
                $account = $accounts[$slug->value];
                $role = $roles->get($slug->value);
                $user = User::withTrashed()->where('email', $account['email'])->lockForUpdate()->first();
                if ($user !== null) {
                    $assignments = UserRole::query()->where('user_id', $user->id)->get();
                    if ($user->trashed() || ! $user->is_active || ! Hash::check($password, $user->password)
                        || $assignments->count() !== 1 || $assignments->first()->role_id !== $role->id) {
                        throw new LogicException('A demo email is already in use or its account was changed. No existing account will be promoted, reset or reactivated.');
                    }
                } else {
                    $user = new User;
                    $user->full_name = $account['full_name'];
                    $user->email = $account['email'];
                    $user->password = $password;
                    $user->is_active = true;
                    $user->auth_version = 1;
                    $user->email_verified_at = null;
                    $user->created_at = $clock->now();
                    $user->updated_at = $clock->now();
                    $user->save();

                    $assignment = new UserRole;
                    $assignment->user_id = $user->id;
                    $assignment->role_id = $role->id;
                    $assignment->assigned_by = null;
                    $assignment->assigned_at = $clock->now();
                    $assignment->save();
                }
                if (! Cart::query()->where('user_id', $user->id)->exists()) {
                    $cart = new Cart;
                    $cart->user_id = $user->id;
                    $cart->version = 1;
                    $cart->created_at = $clock->now();
                    $cart->updated_at = $clock->now();
                    $cart->save();
                }
            }
        });
    }

    /** @return array<string, array{email: string, full_name: string}> */
    private function accounts(): array
    {
        $accounts = config('store.demo.accounts');
        if (! is_array($accounts) || count($accounts) !== count(RoleSlug::cases())) {
            throw new LogicException('Configure exactly the three canonical demo accounts.');
        }
        $seen = [];
        foreach (RoleSlug::cases() as $role) {
            $account = $accounts[$role->value] ?? null;
            if (! is_array($account) || ! isset($account['email'], $account['full_name'])
                || ! is_string($account['email']) || ! is_string($account['full_name'])
                || ! filter_var($account['email'], FILTER_VALIDATE_EMAIL) || ! str_ends_with($account['email'], '.test')
                || $account['email'] !== strtolower($account['email']) || strlen($account['email']) > 255
                || trim($account['full_name']) === '' || mb_strlen($account['full_name']) > 100
                || isset($seen[$account['email']])) {
                throw new LogicException('Demo accounts require unique lowercase .test emails and valid display names.');
            }
            $seen[$account['email']] = true;
        }

        return $accounts;
    }
}
