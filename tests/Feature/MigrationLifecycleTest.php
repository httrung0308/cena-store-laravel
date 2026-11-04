<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_empty_database_migration_full_rollback_and_incremental_upgrade_are_reproducible(): void
    {
        $definition = json_decode(file_get_contents(__DIR__.'/../Fixtures/schema-contract.json'), true, flags: JSON_THROW_ON_ERROR);
        $expected = [...array_keys($definition['tables']), ...$definition['infrastructure'], 'migrations'];
        sort($expected);
        $before = $this->columnDefinitions();

        $this->artisan('migrate:reset', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(['migrations'], Schema::getTableListing(schemaQualified: false));

        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame($expected, $this->tables());
        $this->assertSame($before, $this->columnDefinitions());

        $this->artisan('migrate:rollback', ['--step' => 8, '--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'sessions'], $this->tables());

        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame($expected, $this->tables());
        $this->assertSame($before, $this->columnDefinitions());
    }

    /** @return list<string> */
    private function tables(): array
    {
        $tables = Schema::getTableListing(schemaQualified: false);
        sort($tables);

        return $tables;
    }

    /** @return list<array<string, mixed>> */
    private function columnDefinitions(): array
    {
        return array_map(static fn (object $column): array => (array) $column, DB::select('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [DB::connection()->getDatabaseName()]));
    }
}
