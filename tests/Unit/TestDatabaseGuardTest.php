<?php

namespace Tests\Unit;

use App\Support\TestDatabaseGuard;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    public function test_dedicated_test_account_is_accepted(): void
    {
        TestDatabaseGuard::assertSafe('mysql', 'cena_store_test', 'cena_test');
        $this->expectNotToPerformAssertions();
    }

    public function test_dedicated_ci_account_is_accepted(): void
    {
        TestDatabaseGuard::assertSafe('mysql', 'cena_store_test', 'cena_ci');
        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('unsafeConnections')]
    public function test_unsafe_connection_is_rejected_before_database_operations(string $driver, string $database, string $username, bool $cached): void
    {
        $this->expectException(LogicException::class);
        TestDatabaseGuard::assertSafe($driver, $database, $username, $cached);
    }

    /** @return array<string, array{string, string, string, bool}> */
    public static function unsafeConnections(): array
    {
        return [
            'development database' => ['mysql', 'cena_store', 'cena_test', false],
            'similar database name' => ['mysql', 'cena_store_test_backup', 'cena_test', false],
            'SQLite' => ['sqlite', 'cena_store_test', 'cena_test', false],
            'root user' => ['mysql', 'cena_store_test', 'root', false],
            'development user' => ['mysql', 'cena_store_test', 'cena_app', false],
            'cached configuration' => ['mysql', 'cena_store_test', 'cena_test', true],
        ];
    }
}
