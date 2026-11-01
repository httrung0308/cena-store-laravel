<?php

namespace Tests\Feature;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use App\Models\SalesLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class AtomicConcurrencyTest extends CommittedAtomicTestCase
{
    public function test_two_independent_mysql_transactions_post_one_ledger_entry_and_one_notification(): void
    {
        $order = $this->order();
        $results = $this->race('ledger', $order->id, [30000, 30000]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result'], $results[1]['result']);
        $this->assertSame(1, SalesLedgerEntry::where('order_id', $order->id)->count());
        $this->assertSame(30000, SalesLedgerEntry::where('order_id', $order->id)->sole()->shipping_revenue_delta);
        $this->assertSame(1, OutboxEvent::where('dedup_key', 'like', $this->namespace.'%')->count());
    }

    public function test_competing_different_ledger_intents_have_one_winner_and_one_explicit_conflict(): void
    {
        $order = $this->order();
        $results = $this->race('ledger', $order->id, [30000, 40000]);
        $success = array_values(array_filter($results, fn (array $result): bool => $result['ok']));
        $failure = array_values(array_filter($results, fn (array $result): bool => ! $result['ok']));
        $this->assertCount(1, $success);
        $this->assertCount(1, $failure);
        $this->assertSame('IDEMPOTENCY_MISMATCH', $failure[0]['code']);
        $this->assertSame(1, SalesLedgerEntry::where('order_id', $order->id)->count());
        $this->assertSame(1, OutboxEvent::where('dedup_key', 'like', $this->namespace.'%')->count());
    }

    public function test_two_enqueue_transactions_race_after_both_read_the_missing_dedup_key(): void
    {
        $results = $this->race('outbox', $this->actor->id, [100, 100]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result'], $results[1]['result']);
        $this->assertSame(1, OutboxEvent::where('dedup_key', 'like', $this->namespace.'%')->count());
    }

    public function test_competing_payloads_cannot_reuse_one_notification_key(): void
    {
        $results = $this->race('outbox', $this->actor->id, [100, 200]);
        $this->assertCount(1, array_filter($results, fn (array $r): bool => $r['ok']));
        $failure = array_values(array_filter($results, fn (array $r): bool => ! $r['ok']));
        $this->assertSame('IDEMPOTENCY_MISMATCH', $failure[0]['code']);
        $this->assertSame(1, OutboxEvent::where('dedup_key', 'like', $this->namespace.'%')->count());
    }

    public function test_two_worker_connections_cannot_claim_the_same_live_lease(): void
    {
        $event = $this->event();
        $results = $this->race('claim', $event->id, [0, 0]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(1, count(array_filter($results, fn (array $r): bool => $r['result']['claimed'])));
        $this->assertSame(OutboxStatus::Processing, $event->fresh()->status);
        $this->assertSame(1, $event->fresh()->attempts);
    }

    /**
     * @param  array{0: int, 1: int}  $amounts
     * @return list<array<string, mixed>>
     */
    private function race(string $mode, int $id, array $amounts): array
    {
        $directory = storage_path('framework/testing/atomic-'.Str::uuid());
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->fail('Cannot create the private test barrier directory.');
        }
        $connection = DB::connection()->getConfig();
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $processes = [];
        try {
            foreach ([0, 1] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/atomic-worker.php')], base_path(), $environment);
                $process->setTimeout(35);
                $process->setInput(json_encode(['mode' => $mode, 'id' => $id, 'amount' => $amounts[$worker], 'key' => $this->namespace.$mode, 'directory' => $directory, 'worker' => (string) $worker], JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }
            $this->waitForFiles($directory, 'ready', $processes);
            file_put_contents($directory.'/go', 'go');
            if ($mode !== 'claim') {
                $this->waitForFiles($directory, 'selected', $processes);
                file_put_contents($directory.'/insert-go', 'go');
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().' '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            $resolved = realpath($directory);
            $base = realpath(storage_path('framework/testing'));
            if ($resolved !== false && $base !== false && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'atomic-')) {
                foreach (['ready-0', 'ready-1', 'selected-0', 'selected-1', 'go', 'insert-go'] as $name) {
                    if (is_file($resolved.DIRECTORY_SEPARATOR.$name)) {
                        unlink($resolved.DIRECTORY_SEPARATOR.$name);
                    }
                }
                rmdir($resolved);
            }
        }
    }

    /** @param list<Process> $processes */
    private function waitForFiles(string $directory, string $prefix, array $processes): void
    {
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/'.$prefix.'-0') || ! is_file($directory.'/'.$prefix.'-1')) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Worker exited before the SQL barrier: '.$process->getOutput().' '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Workers did not reach the SQL barrier.');
            }
            usleep(10000);
            clearstatcache();
        }
    }
}
