<?php

use App\Data\OperationsWorkItemDefinition;
use App\Enums\OperationsSourceType;
use App\Models\User;
use App\Services\OperationsWorkItemService;
use App\Support\TestDatabaseGuard;
use Carbon\CarbonImmutable;
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
    if ($directory === false || $base === false || ! str_starts_with($directory, $base.DIRECTORY_SEPARATOR.'operations-work-item-') || ! preg_match('/^[01]$/D', $input['worker'])) {
        throw new LogicException('Unsafe operations work item test barrier path.');
    }
    $wait = static function (string $path): void {
        $deadline = microtime(true) + 20;
        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Operations work item barrier timed out.');
            }
            usleep(10000);
            clearstatcache(true, $path);
        }
    };
    file_put_contents($directory.'/ready-'.$input['worker'], 'ready');
    $wait($directory.'/go');

    $item = DB::transaction(fn () => app(OperationsWorkItemService::class)->create(
        new OperationsWorkItemDefinition(
            OperationsSourceType::Order,
            $input['source_event_key'],
            'ORD-RACE',
            'Đơn hàng',
            'Đối chiếu cùng một sự kiện',
            'Hai kết nối kiểm thử cùng phát lại định nghĩa server-side giống nhau.',
            CarbonImmutable::parse('2026-11-10 08:00:00', 'UTC'),
        ),
        User::query()->findOrFail($input['actor_id']),
    ), 3);

    echo json_encode(['ok' => true, 'id' => $item->id], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'class' => $exception::class], JSON_THROW_ON_ERROR);
    exit(1);
}
