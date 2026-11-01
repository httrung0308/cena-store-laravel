<?php

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OutboxAggregateType;
use App\Exceptions\DomainException;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\OutboxService;
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
    if ($directory === false || $base === false || ! str_starts_with($directory, $base.DIRECTORY_SEPARATOR.'inventory-') || ! preg_match('/^[01]$/D', $input['worker'])) {
        throw new LogicException('Unsafe test barrier path.');
    }
    $wait = static function (string $path): void {
        $deadline = microtime(true) + 20;
        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Test barrier timed out.');
            }
            usleep(10000);
            clearstatcache(true, $path);
        }
    };
    $observed = false;
    DB::connection()->beforeExecuting(function (string $query) use ($input, $directory, &$observed): void {
        if (! $observed && str_starts_with(strtolower(ltrim($query)), 'select') && str_contains($query, 'product_variants') && str_contains(strtolower($query), 'for update')) {
            $observed = true;
            file_put_contents($directory.'/selected-'.$input['worker'], 'ready');
        }
    });
    file_put_contents($directory.'/ready-'.$input['worker'], 'ready');
    $wait($directory.'/go');
    $changes = array_map(static fn (array $change): InventoryChange => new InventoryChange($change['key'], $change['variant'], InventoryAction::from($change['action']), InventoryReferenceType::from($change['reference']), $change['id'], $change['quantity'], $change['cost'] ?? null), $input['changes']);
    $connectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    $result = DB::transaction(function () use ($changes, $input): array {
        ProductVariant::findOrFail($changes[0]->variantId);
        $movements = app(InventoryService::class)->applyBatch($changes);
        $event = app(OutboxService::class)->enqueue($changes[0]->operationKey.':notification', 'INVENTORY_CHANGED', OutboxAggregateType::User, $input['actor'], ['movement_ids' => array_map(fn ($movement): int => $movement->id, $movements)]);

        return ['movements' => array_map(fn ($movement): int => $movement->id, $movements), 'event' => $event->id];
    }, 3);
    echo json_encode(['ok' => true, 'connection' => $connectionId, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'connection' => $connectionId ?? null, 'class' => $exception::class, 'code' => $exception instanceof DomainException ? $exception->errorCode->value : null], JSON_THROW_ON_ERROR);
    exit($exception instanceof DomainException ? 0 : 1);
}
