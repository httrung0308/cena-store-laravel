<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Identity seed only. Complete FoundationSeeder awaits the reviewed EXT-001/002 dataset. */
class CoreFoundationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([RolesSeeder::class, DemoUsersSeeder::class]);
        });
    }
}
