<?php

use App\Exceptions\DomainException;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentReturnService;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertSafe(config('database.default'), DB::connection()->getDatabaseName(), DB::connection()->getConfig('username'), $app->configurationIsCached());
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $directory = realpath($input['directory']);
    $base = realpath(storage_path('framework/testing'));
    if ($directory === false || $base === false || ! str_starts_with($directory, $base.DIRECTORY_SEPARATOR.'shipment-return-')
        || ! preg_match('/^[0-9]+$/D', (string) $input['shipment']) || ! preg_match('/^[0-9]+$/D', (string) $input['unit'])
        || ! preg_match('/^[0-9]+$/D', (string) $input['actor']) || ! preg_match('/^[01]$/D', $input['worker'])) {
        throw new LogicException('Unsafe test barrier input.');
    }
    $deadline = microtime(true) + 25;
    file_put_contents($directory.'/ready-'.$input['worker'], 'ready');
    while (! is_file($directory.'/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Test barrier timed out.');
        }
        usleep(10000);
        clearstatcache(true, $directory.'/go');
    }

    $connectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    $returnItem = app(ShipmentReturnService::class)->receiveUnit(
        Shipment::findOrFail((int) $input['shipment']),
        (int) $input['unit'],
        User::findOrFail((int) $input['actor']),
        'Nhận kiện đồng thời: hộp, tem và đôi giày đã được kiểm đếm.',
    );

    echo json_encode(['ok' => true, 'connection' => $connectionId, 'return_item' => $returnItem->id], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'connection' => $connectionId ?? null,
        'class' => $exception::class,
        'code' => $exception instanceof DomainException ? $exception->errorCode->value : null,
    ], JSON_THROW_ON_ERROR);
    exit($exception instanceof DomainException ? 0 : 1);
}
