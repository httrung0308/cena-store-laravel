<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Process\Process;

class InventoryConcurrencyTest extends CommittedAtomicTestCase
{
    /** @var array<string, list<int>> */
    private array $ownedIds = [];

    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::table('order_items')->whereIn('order_id', $this->orderIds)->delete();
            foreach (['inventory_movements', 'stock_import_items', 'stock_imports', 'product_variants', 'product_colors', 'products', 'categories', 'suppliers'] as $table) {
                DB::table($table)->whereIn('id', $this->ownedIds[$table] ?? [])->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_service_refuses_to_start_its_own_business_transaction(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('surrounding business transaction');
        app(InventoryService::class)->applyBatch([new InventoryChange('outside:transaction', 1, InventoryAction::OrderReserved, InventoryReferenceType::Order, 1, 1)]);
    }

    public function test_two_orders_competing_for_last_pair_have_one_winner_without_overselling(): void
    {
        $variant = $this->variant(1);
        $first = $this->line($variant);
        $second = $this->line($variant);
        $results = $this->race([[$this->command('first', $variant, $first)], [$this->command('second', $variant, $second)]]);
        $this->oneFailure($results, 'STOCK_UNAVAILABLE');
        $this->assertSame([1, 1], [$variant->fresh()->stock_qty, $variant->fresh()->reserved_qty]);
        $this->assertSame(1, InventoryMovement::where('variant_id', $variant->id)->where('action', 'ORDER_RESERVED')->count());
        $this->assertSame(1, OutboxEvent::where('dedup_key', 'like', $this->namespace.'%')->count());
    }

    public function test_same_operation_on_two_connections_changes_stock_and_outbox_once(): void
    {
        $variant = $this->variant(2);
        $line = $this->line($variant);
        $command = $this->command('same', $variant, $line);
        $results = $this->race([[$command], [$command]]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result'], $results[1]['result']);
        $this->assertSame(1, $variant->fresh()->reserved_qty);
        $this->assertSame(1, OutboxEvent::where('dedup_key', 'like', $this->namespace.'%')->count());
    }

    public function test_same_key_with_competing_quantities_has_one_explicit_idempotency_conflict(): void
    {
        $variant = $this->variant(3);
        $line = $this->line($variant, 2);
        $results = $this->race([[$this->command('key', $variant, $line)], [$this->command('key', $variant, $line, quantity: 2)]]);
        $this->oneFailure($results, 'IDEMPOTENCY_MISMATCH');
        $winner = InventoryMovement::where('operation_key', $this->namespace.'key')->sole();
        $this->assertSame($winner->reserved_delta, $variant->fresh()->reserved_qty);
    }

    public function test_same_key_for_different_variants_never_leaves_a_losing_bucket_update(): void
    {
        $first = $this->variant(1);
        $second = $this->variant(1);
        $results = $this->race([[$this->command('cross-key', $first, $this->line($first))], [$this->command('cross-key', $second, $this->line($second))]]);
        $this->oneFailure($results, 'IDEMPOTENCY_MISMATCH');
        $this->assertSame(1, $first->fresh()->reserved_qty + $second->fresh()->reserved_qty);
        $this->assertSame(1, InventoryMovement::where('operation_key', $this->namespace.'cross-key')->count());
    }

    public function test_opposite_input_order_for_two_skus_uses_consistent_lock_order_and_atomic_batches(): void
    {
        $first = $this->variant(2);
        $second = $this->variant(2);
        $results = $this->race([
            [$this->command('one-b', $second, $this->line($second)), $this->command('one-a', $first, $this->line($first))],
            [$this->command('two-a', $first, $this->line($first)), $this->command('two-b', $second, $this->line($second))],
        ]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame([2, 2], [$first->fresh()->reserved_qty, $second->fresh()->reserved_qty]);
        DB::transaction(fn () => app(InventoryService::class)->assertConsistent([$second->id, $first->id]));
    }

    public function test_release_racing_dispatch_cannot_consume_the_same_hold_twice(): void
    {
        $variant = $this->variant(1);
        $line = $this->line($variant);
        DB::transaction(fn () => app(InventoryService::class)->reserve(new InventoryChange($this->namespace.'held', $variant->id, InventoryAction::OrderReserved, InventoryReferenceType::Order, $line->order_id, 1)));
        $results = $this->race([[$this->command('release', $variant, $line, 'ORDER_RELEASED')], [$this->command('dispatch', $variant, $line, 'ORDER_DISPATCHED')]]);
        $this->oneFailure($results, 'STATE_CONFLICT');
        $this->assertSame(0, $variant->fresh()->reserved_qty);
        $this->assertContains($variant->fresh()->stock_qty, [0, 1]);
    }

    private function variant(int $stock): ProductVariant
    {
        $variant = ProductVariant::factory()->create();
        $color = $variant->color;
        $product = $color->product;
        foreach (['product_variants' => $variant->id, 'product_colors' => $color->id, 'products' => $product->id, 'categories' => $product->category_id] as $table => $id) {
            $this->ownedIds[$table][] = $id;
        }
        $import = StockImport::factory()->create(['created_by' => $this->actor->id]);
        $line = StockImportItem::factory()->create(['stock_import_id' => $import->id, 'variant_id' => $variant->id, 'quantity' => $stock, 'subtotal_amount' => $stock * 100]);
        $this->ownedIds['stock_imports'][] = $import->id;
        $this->ownedIds['stock_import_items'][] = $line->id;
        $this->ownedIds['suppliers'][] = $import->supplier_id;
        $movement = DB::transaction(fn () => app(InventoryService::class)->receive(new InventoryChange($this->namespace.'import:'.$import->id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $import->id, $stock, '100')));
        $this->ownedIds['inventory_movements'][] = $movement->id;

        return $variant->refresh();
    }

    private function line(ProductVariant $variant, int $quantity = 1): OrderItem
    {
        return OrderItem::factory()->create(['order_id' => $this->order()->id, 'variant_id' => $variant->id, 'quantity' => $quantity, 'subtotal_amount' => $quantity * 100, 'net_amount' => $quantity * 100]);
    }

    private function command(string $key, ProductVariant $variant, OrderItem $line, string $action = 'ORDER_RESERVED', int $quantity = 1): array
    {
        return ['key' => $this->namespace.$key, 'variant' => $variant->id, 'reference' => 'ORDER', 'id' => $line->order_id, 'action' => $action, 'quantity' => $quantity];
    }

    private function oneFailure(array $results, string $code): void
    {
        $this->assertCount(1, array_filter($results, fn (array $r): bool => $r['ok']));
        $failed = array_values(array_filter($results, fn (array $r): bool => ! $r['ok']));
        $this->assertCount(1, $failed);
        $this->assertSame($code, $failed[0]['code']);
    }

    /** @param array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} $commands */
    private function race(array $commands): array
    {
        $directory = storage_path('framework/testing/inventory-'.Str::uuid());
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
        $parentLocked = false;
        try {
            $ids = array_unique(array_column(array_merge(...$commands), 'variant'));
            sort($ids, SORT_NUMERIC);
            DB::beginTransaction();
            $parentLocked = true;
            ProductVariant::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            foreach ([0, 1] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/inventory-worker.php')], base_path(), $environment);
                $process->setTimeout(35);
                $process->setInput(json_encode(['changes' => $commands[$worker], 'actor' => $this->actor->id, 'directory' => $directory, 'worker' => (string) $worker], JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }
            $this->waitForFiles($directory, 'ready', $processes);
            file_put_contents($directory.'/go', 'go');
            $this->waitForFiles($directory, 'selected', $processes);
            DB::commit();
            $parentLocked = false;
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().' '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);

            return $results;
        } finally {
            if ($parentLocked) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            $this->ownedIds['inventory_movements'] = InventoryMovement::whereIn('variant_id', $this->ownedIds['product_variants'] ?? [])->pluck('id')->all();
            $resolved = realpath($directory);
            $base = realpath(storage_path('framework/testing'));
            if ($resolved !== false && $base !== false && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'inventory-')) {
                foreach (['ready-0', 'ready-1', 'selected-0', 'selected-1', 'go'] as $name) {
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
