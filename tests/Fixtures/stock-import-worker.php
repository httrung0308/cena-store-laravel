<?php

use App\Exceptions\DomainException;
use App\Models\StockImport;
use App\Models\User;
use App\Services\StockImportService;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertSafe(config('database.default'), DB::connection()->getDatabaseName(), DB::connection()->getConfig('username'), $app->configurationIsCached());
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $dir = realpath($input['directory']);
    $base = realpath(storage_path('framework/testing'));
    if (! $dir || ! $base || ! str_starts_with($dir, $base.DIRECTORY_SEPARATOR.'stock-import-') || ! in_array($input['worker'], ['0', '1'], true)) {
        throw new LogicException('Unsafe barrier');
    }
    $wait = static function (string $path): void {
        $until = microtime(true) + 20;
        while (! is_file($path)) {
            if (microtime(true) > $until) {
                throw new RuntimeException('Barrier timed out');
            }usleep(10000);
            clearstatcache(true, $path);
        }
    };
    $seen = false;
    DB::connection()->beforeExecuting(function (string $query) use (&$seen, $dir, $input): void {
        if (! $seen && str_starts_with(strtolower(ltrim($query)), 'select') && str_contains($query, 'stock_imports') && str_contains(strtolower($query), 'for update')) {
            $seen = true;
            file_put_contents($dir.'/selected-'.$input['worker'], 'ready');
        }
    });
    $actor = User::findOrFail($input['actor']);
    $import = StockImport::findOrFail($input['import']);
    file_put_contents($dir.'/ready-'.$input['worker'], 'ready');
    $wait($dir.'/go');
    $connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    $result = app(StockImportService::class)->post($actor, $import);
    echo json_encode(['ok' => true, 'connection' => $connection, 'id' => $result->id, 'posted_by' => $result->posted_by, 'status' => $result->status->value], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'class' => $e::class, 'code' => $e instanceof DomainException ? $e->errorCode->value : null], JSON_THROW_ON_ERROR);
    exit(1);
}
