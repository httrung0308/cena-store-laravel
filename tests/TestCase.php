<?php

namespace Tests;

use App\Support\TestDatabaseGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        TestDatabaseGuard::assertSafe(
            $app['config']->get('database.default'),
            $app['db']->connection()->getDatabaseName(),
            $app['db']->connection()->getConfig('username'),
            $app->configurationIsCached(),
        );

        return $app;
    }
}
