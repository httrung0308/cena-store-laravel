<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OperationsWorkItemConcurrencyTest extends CommittedAtomicTestCase
{
    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $ids = DB::table('operations_work_items')->where('source_event_key', 'like', $this->namespace.'%')->select('id');
            DB::table('operations_work_item_histories')->whereIn('work_item_id', $ids)->delete();
            DB::table('operations_work_items')->where('source_event_key', 'like', $this->namespace.'%')->delete();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_connections_atomically_replay_the_same_missing_source_event_once(): void
    {
        $directory = storage_path('framework/testing/operations-work-item-'.Str::uuid());
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->fail('Cannot create the private test barrier directory.');
        }
        $connection = DB::connection()->getConfig();
        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_URL' => '',
            'DB_HOST' => $connection['host'],
            'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'],
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];
        $processes = [];
        try {
            foreach ([0, 1] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/operations-work-item-worker.php')], base_path(), $environment);
                $process->setTimeout(35);
                $process->setInput(json_encode([
                    'actor_id' => $this->actor->id,
                    'source_event_key' => $this->namespace.'order-event',
                    'directory' => $directory,
                    'worker' => (string) $worker,
                ], JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }
            $this->waitForWorkers($directory, $processes);
            file_put_contents($directory.'/go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().' '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            $this->assertTrue($results[0]['ok']);
            $this->assertTrue($results[1]['ok']);
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $item = DB::table('operations_work_items')->where('source_event_key', $this->namespace.'order-event')->first();
            $this->assertNotNull($item);
            $this->assertSame(1, DB::table('operations_work_item_histories')->where('work_item_id', $item->id)->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            $resolved = realpath($directory);
            $base = realpath(storage_path('framework/testing'));
            if ($resolved !== false && $base !== false && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'operations-work-item-')) {
                foreach (['ready-0', 'ready-1', 'go'] as $name) {
                    if (is_file($resolved.DIRECTORY_SEPARATOR.$name)) {
                        unlink($resolved.DIRECTORY_SEPARATOR.$name);
                    }
                }
                rmdir($resolved);
            }
        }
    }

    /** @param list<Process> $processes */
    private function waitForWorkers(string $directory, array $processes): void
    {
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Worker exited before the start barrier: '.$process->getOutput().' '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Work item workers did not reach the start barrier.');
            }
            usleep(10000);
            clearstatcache();
        }
    }
}
