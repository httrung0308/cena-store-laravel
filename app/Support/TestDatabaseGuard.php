<?php

namespace App\Support;

use LogicException;

class TestDatabaseGuard
{
    public static function assertSafe(string $driver, string $database, string $username, bool $cached = false): void
    {
        $allowed = ($database === 'cena_store_test' && in_array($username, ['cena_test', 'cena_ci'], true))
            || ($database === 'cena_store_test_codex' && $username === 'cena_test_codex');
        if ($cached || $driver !== 'mysql' || ! $allowed) {
            throw new LogicException('Tests require uncached MySQL and an exact dedicated test database/account pair.');
        }
    }
}
