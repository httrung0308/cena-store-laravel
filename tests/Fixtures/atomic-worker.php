<?php

use App\Enums\OutboxAggregateType;
use App\Enums\SalesLedgerType;
use App\Exceptions\DomainException;
use App\Services\OutboxService;
use App\Services\SalesLedgerService;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertSafe(config('database.default'), DB::connection()->getDatabaseName(), DB::connection()->getConfig('username'), $app->configurationIsCached());
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $directory = realpath($input['directory']);
    $base = realpath(storage_path('framework/testing'));
    if ($directory === false || $base === false || ! str_starts_with($directory, $base.DIRECTORY_SEPARATOR.'atomic-') || ! preg_match('/^[01]$/D', $input['worker'])) {
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
    if ($input['mode'] !== 'claim') {
        $observed = false;
        DB::listen(function (QueryExecuted $query) use ($input, $directory, $wait, &$observed): void {
            $table = $input['mode'] === 'ledger' ? 'sales_ledger_entries' : 'outbox_events';
            if (! $observed && str_starts_with(strtolower(ltrim($query->sql)), 'select') && str_contains($query->sql, $table) && in_array($input['key'], $query->bindings, true)) {
                $observed = true;
                file_put_contents($directory.'/selected-'.$input['worker'], 'ready');
                $wait($directory.'/insert-go');
            }
        });
    }
    file_put_contents($directory.'/ready-'.$input['worker'], 'ready');
    $wait($directory.'/go');
    $result = match ($input['mode']) {
        'ledger' => DB::transaction(function () use ($input): array {
            $entry = app(SalesLedgerService::class)->postUnique($input['key'], SalesLedgerType::ShippingRecognized, ['shipping_revenue_delta' => $input['amount']], ['order_id' => $input['id']], '2026-10-31T03:00:00Z');
            $event = app(OutboxService::class)->enqueue($input['key'].':mail', 'ORDER_DELIVERED', OutboxAggregateType::Order, $input['id'], ['order_id' => $input['id']]);

            return ['entry' => $entry->id, 'event' => $event->id];
        }, 3),
        'outbox' => DB::transaction(function () use ($input): array {
            $event = app(OutboxService::class)->enqueue($input['key'], 'USER_UPDATED', OutboxAggregateType::User, $input['id'], ['amount' => $input['amount']]);

            return ['event' => $event->id];
        }, 3),
        'claim' => ['claimed' => app(OutboxService::class)->claim($input['id']) !== null],
        default => throw new LogicException('Unknown test worker operation.'),
    };
    echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'class' => $exception::class, 'code' => $exception instanceof DomainException ? $exception->errorCode->value : null], JSON_THROW_ON_ERROR);
    exit($exception instanceof DomainException ? 0 : 1);
}
