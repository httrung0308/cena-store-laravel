<?php

namespace App\Support;

use LogicException;

class TestDatabaseGuard
{
    public static function assertSafe(string $driver, string $database, string $username, bool $cached = false): void
    {
        if ($cached || $driver !== 'mysql' || $database !== 'cena_store_test' || ! in_array($username, ['cena_test', 'cena_ci'], true)) {
            throw new LogicException('Tests require uncached MySQL cena_store_test and a dedicated cena_test/cena_ci account.');
        }
    }
}
