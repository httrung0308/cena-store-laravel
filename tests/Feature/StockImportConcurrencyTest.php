<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class StockImportConcurrencyTest extends CommittedAtomicTestCase
{
    public function test_two_authorized_operators_post_one_document_once_using_independent_mysql_connections(): void
    {
        $existingRole = Role::where('slug', 'staff')->first();
        $first = User::factory()->staff()->create();
        $second = User::factory()->staff()->create();
        $ownedRoleId = $existingRole ? null : $first->roles()->where('slug', 'staff')->value('roles.id');
        $variant = ProductVariant::factory()->create();
        $color = $variant->color;
        $product = $color->product;
        $supplier = Supplier::factory()->create();
        $other = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '43', 'size_numeric' => '43.0']);
        $import = null;
        $processes = [];
        $locked = false;
        $dir = storage_path('framework/testing/stock-import-'.Str::uuid());
        mkdir($dir, 0700, true);
        try {
            $import = app(StockImportService::class)->saveDraft($first, null, ['command_key' => (string) Str::uuid(), 'supplier_id' => $supplier->id, 'items' => [
                ['variant_id' => $variant->id, 'quantity' => 2, 'unit_cost_amount' => 100], ['variant_id' => $other->id, 'quantity' => 3, 'unit_cost_amount' => 100]]]);
            $config = DB::connection()->getConfig();
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
            DB::beginTransaction();
            $locked = true;
            StockImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            foreach ([$first, $second] as $i => $actor) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/stock-import-worker.php')], base_path(), $env);
                $process->setInput(json_encode(['directory' => $dir, 'worker' => (string) $i, 'actor' => $actor->id, 'import' => $import->id], JSON_THROW_ON_ERROR));
                $process->setTimeout(35);
                $process->start();
                $processes[] = $process;
            }
            $this->barrier($dir, 'ready', $processes);
            file_put_contents($dir.'/go', 'go');
            $this->barrier($dir, 'selected', $processes);
            DB::commit();
            $locked = false;
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().' '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertTrue($results[0]['ok']);
            $this->assertTrue($results[1]['ok']);
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
            $this->assertSame($results[0]['posted_by'], $results[1]['posted_by']);
            $this->assertSame('POSTED', $results[1]['status']);
            $this->assertSame(5, (int) ProductVariant::whereIn('id', [$variant->id, $other->id])->sum('stock_qty'));
            $this->assertSame(2, InventoryMovement::where('reference_id', $import->id)->where('reference_type', 'STOCK_IMPORT')->count());
        } finally {
            if ($locked) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            if ($import) {
                DB::table('inventory_movements')->where('reference_type', 'STOCK_IMPORT')->where('reference_id', $import->id)->delete();
                DB::table('stock_import_items')->where('stock_import_id', $import->id)->delete();
                $import->delete();
            }
            DB::table('product_variants')->whereIn('id', [$variant->id, $other->id])->delete();
            DB::table('product_colors')->where('id', $color->id)->delete();
            DB::table('products')->where('id', $product->id)->delete();
            DB::table('categories')->where('id', $product->category_id)->delete();
            $supplier->forceDelete();
            DB::table('user_roles')->whereIn('user_id', [$first->id, $second->id])->delete();
            $first->forceDelete();
            $second->forceDelete();
            if ($ownedRoleId !== null) {
                DB::table('roles')->where('id', $ownedRoleId)->delete();
            }
            $resolved = realpath($dir);
            $base = realpath(storage_path('framework/testing'));
            if ($resolved && $base && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'stock-import-')) {
                foreach (['ready-0', 'ready-1', 'selected-0', 'selected-1', 'go'] as $file) {
                    if (is_file($resolved.DIRECTORY_SEPARATOR.$file)) {
                        unlink($resolved.DIRECTORY_SEPARATOR.$file);
                    }
                }rmdir($resolved);
            }
        }
    }

    private function barrier(string $directory, string $prefix, array $processes): void
    {
        $until = microtime(true) + 25;
        while (! is_file($directory.'/'.$prefix.'-0') || ! is_file($directory.'/'.$prefix.'-1')) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Worker stopped: '.$process->getOutput().' '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $until) {
                $this->fail('Worker barrier timed out');
            }
            usleep(10000);
            clearstatcache();
        }
    }
}
