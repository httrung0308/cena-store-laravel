<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'factory-password',
            'created_at' => now(),
            'updated_at' => now(),
            'email_verified_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function administrator(): static
    {
        return $this->withRole(RoleSlug::Admin);
    }

    public function staff(): static
    {
        return $this->withRole(RoleSlug::Staff);
    }

    public function customer(): static
    {
        return $this->withRole(RoleSlug::Customer);
    }

    /** Assigns the canonical role (created on demand) after the user is saved. */
    public function withRole(RoleSlug $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug): void {
            $role = Role::query()->where('slug', $slug)->first()
                ?? Role::factory()->create(['slug' => $slug, 'name' => $slug->label()]);
            UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'assigned_at' => now()]);
        });
    }
}
