<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Customer',
            'slug' => RoleSlug::Customer,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
