<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Base data every environment needs: roles, the province/ward snapshot and (only when enabled
 * in local/testing) the demo accounts. Safe to run more than once.
 */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdministrativeUnitSeeder::class,
            DemoUsersSeeder::class,
        ]);
    }
}
