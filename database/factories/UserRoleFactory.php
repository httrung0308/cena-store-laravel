<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserRole> */
class UserRoleFactory extends Factory
{
    protected $model = UserRole::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'assigned_at' => now(),
        ];
    }
}
