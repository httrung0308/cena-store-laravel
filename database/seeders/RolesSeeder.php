<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(BusinessClock $clock): void
    {
        $now = $clock->now()->format('Y-m-d H:i:s.u');
        $rows = array_map(static fn (RoleSlug $role): array => [
            'slug' => $role->value,
            'name' => $role->label(),
            'description' => 'Vai trò nền tảng Cena Store.',
            'created_at' => $now,
            'updated_at' => $now,
        ], RoleSlug::cases());

        DB::table('roles')->upsert($rows, ['slug'], ['name', 'description']);
    }
}
