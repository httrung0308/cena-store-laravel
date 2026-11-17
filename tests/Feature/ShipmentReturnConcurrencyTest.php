<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderStatus;
use App\Enums\OutboxAggregateType;
use App\Enums\ReturnDisposition;
use App\Enums\RoleSlug;
use App\Enums\SalesLedgerType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesLedgerEntry;
use App\Models\ShipmentReturnEvent;
use App\Models\ShipmentReturnItem;
use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderWorkflowService;
use App\Services\ShipmentReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ShipmentReturnConcurrencyTest extends CommittedAtomicTestCase
{
    private User $customer;

    private ProductVariant $variant;

    private int $addressId;

    private int $cartId;

    private int $stockImportId;

    private int $stockImportItemId;

    private int $supplierId;

    private int $productId;

    private int $colorId;

    private int $categoryId;

    /** @var list<int> */
    private array $extraUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $staffRole = Role::query()->where('slug', RoleSlug::Staff->value)->first()
            ?? Role::factory()->staff()->create();
        UserRole::factory()->create(['user_id' => $this->actor->id, 'role_id' => $staffRole->id, 'assigned_at' => now()]);
        $this->customer = User::factory()->customer()->create();

        $this->categoryId = Category::factory()->create()->id;
        $product = Product::factory()->create(['category_id' => $this->categoryId, 'base_price' => 1_000_000, 'promotion_price' => 800_000]);
        $this->productId = $product->id;
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        $this->colorId = $color->id;
        $this->variant = ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '41', 'size_numeric' => '41', 'price_override' => null]);

        $import = StockImport::factory()->create(['created_by' => $this->actor->id]);
        $this->stockImportId = $import->id;
        $this->supplierId = $import->supplier_id;
        $line = StockImportItem::factory()->create(['stock_import_id' => $import->id, 'variant_id' => $this->variant->id, 'quantity' => 2, 'unit_cost_amount' => 300_000, 'subtotal_amount' => 600_000]);
        $this->stockImportItemId = $line->id;
        DB::transaction(fn () => app(InventoryService::class)->receive(new InventoryChange(
            $this->namespace.'import:'.$import->id,
            $this->variant->id,
            InventoryAction::ImportPosted,
            InventoryReferenceType::StockImport,
            $import->id,
            2,
            300_000,
        )));

        $address = Address::factory()->create(['user_id' => $this->customer->id]);
        $this->addressId = $address->id;
        $cart = new Cart;
        $cart->forceFill(['user_id' => $this->customer->id, 'version' => 1])->save();
        $this->cartId = $cart->id;
        (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $this->variant->id, 'quantity' => 1])->save();
        $checkout = app(CheckoutService::class);
        $order = $checkout->place($this->customer, $address->id, (string) Str::uuid(), $checkout->quote($this->customer, $address)['fingerprint'], null);
        $this->orderIds[] = $order->id;
        $workflow = app(OrderWorkflowService::class);
        $workflow->confirmAndPack($order, $this->actor, OrderStatus::Pending->value);
        $workflow->ship($order->fresh(), $this->actor, OrderStatus::Packing->value, 'GHN', null);
        $workflow->fail($order->fresh(), $this->actor, OrderStatus::OnDelivery->value, 'Khách vắng mặt sau ba lần liên hệ');
        $this->variant->refresh();
    }

    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $orderIds = $this->orderIds;
            $shipmentIds = DB::table('shipments')->whereIn('order_id', $orderIds)->pluck('id')->all();
            $returnIds = DB::table('shipment_return_items')->whereIn('shipment_id', $shipmentIds)->pluck('id')->all();
            $workItemKeys = array_merge(
                array_map(fn (int $id): string => "shipment:{$id}:failed-return", $shipmentIds),
                array_map(fn (int $id): string => "shipment-return:{$id}:write-off-approval", $returnIds),
            );
            $workItemIds = DB::table('operations_work_items')->whereIn('source_event_key', $workItemKeys)->pluck('id')->all();
            DB::table('operations_work_item_histories')->whereIn('work_item_id', $workItemIds)->delete();
            DB::table('operations_work_items')->whereIn('id', $workItemIds)->delete();
            DB::table('shipment_return_events')->whereIn('shipment_return_item_id', $returnIds)->delete();
            DB::table('shipment_return_items')->whereIn('id', $returnIds)->delete();
            DB::table('sales_ledger_entries')->whereIn('order_id', $orderIds)->delete();
            DB::table('inventory_movements')->whereIn('variant_id', [$this->variant->id])->delete();
            DB::table('payment_events')->whereIn('payment_id', DB::table('payments')->whereIn('order_id', $orderIds)->select('id'))->delete();
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            DB::table('outbox_events')->where('aggregate_type', OutboxAggregateType::Order->value)->whereIn('aggregate_id', $orderIds)->delete();
            DB::table('notifications')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_status_histories')->whereIn('order_id', $orderIds)->delete();
            DB::table('shipments')->whereIn('id', $shipmentIds)->delete();
            DB::table('order_item_units')->whereIn('order_item_id', DB::table('order_items')->whereIn('order_id', $orderIds)->select('id'))->delete();
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
            DB::table('cart_items')->where('cart_id', $this->cartId)->delete();
            DB::table('carts')->where('id', $this->cartId)->delete();
            DB::table('addresses')->where('id', $this->addressId)->delete();
            DB::table('stock_import_items')->where('id', $this->stockImportItemId)->delete();
            DB::table('stock_imports')->where('id', $this->stockImportId)->delete();
            DB::table('product_variants')->where('id', $this->variant->id)->delete();
            DB::table('product_colors')->where('id', $this->colorId)->delete();
            DB::table('products')->where('id', $this->productId)->delete();
            DB::table('categories')->where('id', $this->categoryId)->delete();
            DB::table('suppliers')->where('id', $this->supplierId)->delete();
            $userIdsToDelete = array_values(array_unique(array_merge([$this->customer->id], $this->extraUserIds)));
            DB::table('user_roles')->whereIn('user_id', array_merge([$this->actor->id], $userIdsToDelete))->delete();
            DB::table('users')->whereIn('id', $userIdsToDelete)->delete();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_worker_connections_racing_to_receive_one_unit_create_one_quarantine_entry(): void
    {
        $order = Order::query()->whereKey($this->orderIds[0])->with(['shipment', 'items.units'])->firstOrFail();
        $unitId = $order->items->sole()->units->sole()->id;
        $directory = storage_path('framework/testing/shipment-return-'.Str::uuid());
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
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/shipment-return-worker.php')], base_path(), $environment);
                $process->setTimeout(35);
                $process->setInput(json_encode([
                    'shipment' => $order->shipment->id,
                    'unit' => $unitId,
                    'actor' => $this->actor->id,
                    'directory' => $directory,
                    'worker' => (string) $worker,
                ], JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }
            $this->waitForReadyWorkers($directory, $processes);
            file_put_contents($directory.'/go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().' '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            $this->assertTrue($results[0]['ok']);
            $this->assertTrue($results[1]['ok']);
            $this->assertSame($results[0]['return_item'], $results[1]['return_item']);
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
            $this->assertSame(1, ShipmentReturnItem::query()->where('order_item_unit_id', $unitId)->count());
            $this->assertSame([1, 0, 1], [$this->variant->fresh()->stock_qty, $this->variant->fresh()->reserved_qty, $this->variant->fresh()->quarantine_qty]);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            $resolved = realpath($directory);
            $base = realpath(storage_path('framework/testing'));
            if ($resolved !== false && $base !== false && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'shipment-return-')) {
                foreach (['ready-0', 'ready-1', 'go'] as $name) {
                    if (is_file($resolved.DIRECTORY_SEPARATOR.$name)) {
                        unlink($resolved.DIRECTORY_SEPARATOR.$name);
                    }
                }
                rmdir($resolved);
            }
        }
    }

    public function test_two_worker_connections_racing_to_restock_one_return_item_restock_once(): void
    {
        $order = Order::query()->whereKey($this->orderIds[0])->with(['shipment', 'items.units'])->firstOrFail();
        $unitId = $order->items->sole()->units->sole()->id;
        $returnItem = app(ShipmentReturnService::class)->receiveUnit(
            $order->shipment,
            $unitId,
            $this->actor,
            'Đã nhận đủ hộp và tem để kiểm tra đồng thời.',
        );

        $results = $this->runInlineWorkers(
            'restock',
            ['return_item_id' => $returnItem->id, 'actor_id' => $this->actor->id],
            <<<'PHP'
            $returnItem = \App\Models\ShipmentReturnItem::findOrFail((int) $input['return_item_id']);
            $actor = \App\Models\User::findOrFail((int) $input['actor_id']);
            $updated = app(\App\Services\ShipmentReturnService::class)->restock(
                $returnItem,
                $actor,
                'Kiểm tra đạt đồng thời: giày và hộp nguyên vẹn để bán lại.',
            );
            return ['disposition' => $updated->disposition->value];
            PHP,
        );

        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(ReturnDisposition::Restock->value, $results[0]['disposition']);
        $this->assertSame(ReturnDisposition::Restock->value, $results[1]['disposition']);
        $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
        $this->assertSame(1, InventoryMovement::query()->where('reference_type', InventoryReferenceType::ShipmentReturnItem)->where('reference_id', $returnItem->id)->where('action', InventoryAction::ReturnRestocked)->count());
        $this->assertSame(1, ShipmentReturnEvent::query()->where('shipment_return_item_id', $returnItem->id)->where('event_type', ShipmentReturnEvent::RESTOCKED)->count());
        $this->assertSame([2, 0, 0], [$this->variant->fresh()->stock_qty, $this->variant->fresh()->reserved_qty, $this->variant->fresh()->quarantine_qty]);
    }

    public function test_two_worker_connections_racing_to_approve_write_off_post_one_inventory_loss(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->extraUserIds[] = $admin->id;
        $order = Order::query()->whereKey($this->orderIds[0])->with(['shipment', 'items.units'])->firstOrFail();
        $unitId = $order->items->sole()->units->sole()->id;
        $service = app(ShipmentReturnService::class);
        $returnItem = $service->receiveUnit(
            $order->shipment,
            $unitId,
            $this->actor,
            'Đã nhận lại và giữ riêng để đánh giá hư hỏng đồng thời.',
        );
        $service->proposeWriteOff($returnItem, $this->actor, 'Đế giày nứt nặng và hộp rách, đề xuất ghi hỏng.');

        $results = $this->runInlineWorkers(
            'writeoff',
            ['return_item_id' => $returnItem->id, 'actor_id' => $admin->id],
            <<<'PHP'
            $returnItem = \App\Models\ShipmentReturnItem::findOrFail((int) $input['return_item_id']);
            $actor = \App\Models\User::findOrFail((int) $input['actor_id']);
            $updated = app(\App\Services\ShipmentReturnService::class)->approveWriteOff(
                $returnItem,
                $actor,
                'Admin duyệt ghi hỏng đồng thời sau khi kiểm tra chứng cứ.',
            );
            return ['disposition' => $updated->disposition->value];
            PHP,
        );

        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(ReturnDisposition::WriteOff->value, $results[0]['disposition']);
        $this->assertSame(ReturnDisposition::WriteOff->value, $results[1]['disposition']);
        $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
        $this->assertSame(1, InventoryMovement::query()->where('reference_type', InventoryReferenceType::ShipmentReturnItem)->where('reference_id', $returnItem->id)->where('action', InventoryAction::ReturnWrittenOff)->count());
        $this->assertSame(1, SalesLedgerEntry::query()->where('order_id', $order->id)->where('type', SalesLedgerType::InventoryLoss)->count());
        $this->assertSame(1, ShipmentReturnEvent::query()->where('shipment_return_item_id', $returnItem->id)->where('event_type', ShipmentReturnEvent::WRITE_OFF_APPROVED)->count());
        $this->assertSame([1, 0, 0], [$this->variant->fresh()->stock_qty, $this->variant->fresh()->reserved_qty, $this->variant->fresh()->quarantine_qty]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function runInlineWorkers(string $prefix, array $payload, string $body): array
    {
        $directory = storage_path('framework/testing/shipment-return-'.$prefix.'-'.Str::uuid());
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
        $script = <<<PHP
        require 'vendor/autoload.php';
        try {
            \$app = require 'bootstrap/app.php';
            \$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
            \\App\\Support\\TestDatabaseGuard::assertSafe(config('database.default'), \\Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName(), \\Illuminate\\Support\\Facades\\DB::connection()->getConfig('username'), \$app->configurationIsCached());
            \$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
            \$directory = realpath(\$input['directory']);
            \$deadline = microtime(true) + 25;
            file_put_contents(\$directory.'/ready-'.\$input['worker'], 'ready');
            while (! is_file(\$directory.'/go')) {
                if (microtime(true) >= \$deadline) {
                    throw new RuntimeException('Test barrier timed out.');
                }
                usleep(10000);
                clearstatcache(true, \$directory.'/go');
            }
            \$connectionId = \\Illuminate\\Support\\Facades\\DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            \$result = (static function (array \$input): array {
                {$body}
            })(\$input);
            echo json_encode(array_merge(['ok' => true, 'connection' => \$connectionId], \$result), JSON_THROW_ON_ERROR);
        } catch (Throwable \$exception) {
            echo json_encode([
                'ok' => false,
                'connection' => \$connectionId ?? null,
                'class' => \$exception::class,
                'code' => \$exception instanceof \\App\\Exceptions\\DomainException ? \$exception->errorCode->value : null,
                'message' => \$exception->getMessage(),
            ], JSON_THROW_ON_ERROR);
            exit(\$exception instanceof \\App\\Exceptions\\DomainException ? 0 : 1);
        }
        PHP;

        $processes = [];
        try {
            foreach ([0, 1] as $worker) {
                $process = new Process([PHP_BINARY, '-r', $script], base_path(), $environment);
                $process->setTimeout(35);
                $process->setInput(json_encode(array_merge($payload, [
                    'directory' => $directory,
                    'worker' => (string) $worker,
                ]), JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }
            $this->waitForReadyWorkers($directory, $processes);
            file_put_contents($directory.'/go', 'go');
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
            if ($resolved !== false && $base !== false && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'shipment-return-'.$prefix.'-')) {
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
    private function waitForReadyWorkers(string $directory, array $processes): void
    {
        $deadline = microtime(true) + 25;
        while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Worker exited before the receipt barrier: '.$process->getOutput().' '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Receipt workers did not reach the barrier.');
            }
            usleep(10000);
            clearstatcache();
        }
    }
}
