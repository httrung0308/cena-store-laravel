<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureConnectionTest extends TestCase
{
    public function test_mysql_uses_only_dedicated_test_database_and_utc(): void
    {
        $actual = DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account, VERSION() AS version, @@session.time_zone AS timezone');
        $this->assertSame('cena_store_test', $actual->db);
        $this->assertContains(explode('@', $actual->account)[0], ['cena_test', 'cena_ci']);
        $this->assertStringStartsWith('8.4.', $actual->version);
        $this->assertSame('+00:00', $actual->timezone);
    }

    public function test_all_six_infrastructure_tables_remain_available_with_domain_schema(): void
    {
        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    public function test_test_account_cannot_read_development_database(): void
    {
        try {
            DB::select('SELECT migration FROM cena_store.migrations LIMIT 1');
            $this->fail('Test account unexpectedly accessed the development database.');
        } catch (QueryException $exception) {
            $this->assertContains((int) $exception->errorInfo[1], [1044, 1142]);
        }
    }
}
