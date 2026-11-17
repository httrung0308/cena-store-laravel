<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderStatus;
use App\Enums\OutboxAggregateType;
use App\Enums\RoleSlug;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OrderWorkflowConcurrencyTest extends CommittedAtomicTestCase
{
    private User $secondStaff;

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

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.shipping.dataset_version' => 'factory-v1', 'store.shipping.hcm_province_code' => 'none']);
        $staffRole = Role::query()->where('slug', RoleSlug::Staff->value)->first()
            ?? Role::factory()->staff()->create();
        UserRole::factory()->create(['user_id' => $this->actor->id, 'role_id' => $staffRole->id, 'assigned_at' => now()]);
        $this->secondStaff = User::factory()->staff()->create();
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
            DB::table('user_roles')->whereIn('user_id', [$this->actor->id, $this->secondStaff->id, $this->customer->id])->delete();
            DB::table('users')->whereIn('id', [$this->secondStaff->id, $this->customer->id])->delete();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_staff_racing_to_confirm_and_pack_same_pending_order_transition_once(): void
    {
        $order = Order::query()->whereKey($this->orderIds[0])->firstOrFail();
        $initialNotifications = DB::table('notifications')->where('order_id', $order->id)->count();

        $results = $this->runInlineWorkers(
            'confirm-pack',
            [
                ['order_id' => $order->id, 'actor_id' => $this->actor->id],
                ['order_id' => $order->id, 'actor_id' => $this->secondStaff->id],
            ],
            <<<'PHP'
            $order = \App\Models\Order::findOrFail((int) $input['order_id']);
            $actor = \App\Models\User::findOrFail((int) $input['actor_id']);
            $updated = app(\App\Services\OrderWorkflowService::class)->confirmAndPack(
                $order,
                $actor,
                \App\Enums\OrderStatus::Pending->value,
            );
            return ['status' => $updated->order_status->value];
            PHP,
        );

        $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
        foreach ($results as $result) {
            $this->assertTrue(
                $result['ok'] === true || $result['code'] === ErrorCode::StateConflict->value,
                'Expected idempotent success or StateConflict, got: '.json_encode($result),
            );
        }
        $this->assertSame(OrderStatus::Packing, $order->fresh()->order_status);
        $this->assertSame(1, OrderStatusHistory::query()->where('order_id', $order->id)->where('command_key', "order:{$order->id}:action:STAFF_CONFIRM_AND_PACK")->count());
        $this->assertSame([2, 1, 0], [$this->variant->fresh()->stock_qty, $this->variant->fresh()->reserved_qty, $this->variant->fresh()->quarantine_qty]);
        $this->assertLessThanOrEqual($initialNotifications + 1, DB::table('notifications')->where('order_id', $order->id)->count());
    }

    public function test_two_staff_racing_to_ship_same_packing_order_dispatch_once(): void
    {
        $order = Order::query()->whereKey($this->orderIds[0])->firstOrFail();
        app(OrderWorkflowService::class)->confirmAndPack($order, $this->actor, OrderStatus::Pending->value);
        $notificationsBeforeShip = DB::table('notifications')->where('order_id', $order->id)->count();

        $results = $this->runInlineWorkers(
            'ship',
            [
                ['order_id' => $order->id, 'actor_id' => $this->actor->id],
                ['order_id' => $order->id, 'actor_id' => $this->secondStaff->id],
            ],
            <<<'PHP'
            $order = \App\Models\Order::findOrFail((int) $input['order_id']);
            $actor = \App\Models\User::findOrFail((int) $input['actor_id']);
            $updated = app(\App\Services\OrderWorkflowService::class)->ship(
                $order,
                $actor,
                \App\Enums\OrderStatus::Packing->value,
                'GHN',
                'TRACK-CONC-1',
            );
            return ['status' => $updated->order_status->value];
            PHP,
        );

        $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
        foreach ($results as $result) {
            $this->assertTrue(
                $result['ok'] === true || $result['code'] === ErrorCode::StateConflict->value,
                'Expected idempotent success or StateConflict, got: '.json_encode($result),
            );
        }
        $this->assertSame(OrderStatus::OnDelivery, $order->fresh()->order_status);
        $this->assertSame(1, Shipment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderStatusHistory::query()->where('order_id', $order->id)->where('command_key', "order:{$order->id}:action:STAFF_SHIP")->count());
        $this->assertSame(1, InventoryMovement::query()->where('reference_type', InventoryReferenceType::Order)->where('reference_id', $order->id)->where('action', InventoryAction::OrderDispatched)->count());
        $this->assertSame([1, 0, 0], [$this->variant->fresh()->stock_qty, $this->variant->fresh()->reserved_qty, $this->variant->fresh()->quarantine_qty]);
        $this->assertLessThanOrEqual($notificationsBeforeShip + 1, DB::table('notifications')->where('order_id', $order->id)->count());
    }

    /**
     * @param  list<array<string, mixed>>  $workerPayloads
     * @return list<array<string, mixed>>
     */
    private function runInlineWorkers(string $prefix, array $workerPayloads, string $body): array
    {
        $directory = storage_path('framework/testing/order-workflow-'.$prefix.'-'.Str::uuid());
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
            foreach ($workerPayloads as $worker => $payload) {
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
            if ($resolved !== false && $base !== false && str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'order-workflow-'.$prefix.'-')) {
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
                    $this->fail('Worker exited before the barrier: '.$process->getOutput().' '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Order workflow workers did not reach the barrier.');
            }
            usleep(10000);
            clearstatcache();
        }
    }
}
