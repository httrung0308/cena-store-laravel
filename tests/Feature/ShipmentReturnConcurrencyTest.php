<?php

namespace Tests\Feature;

use App\Data\InventoryChange;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\OrderStatus;
use App\Enums\OutboxAggregateType;
use App\Enums\RoleSlug;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShipmentReturnItem;
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
        $workflow->confirm($order, $this->actor, OrderStatus::Pending->value);
        $workflow->pack($order->fresh(), $this->actor, OrderStatus::Confirmed->value);
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
            DB::table('shipment_return_events')->whereIn('shipment_return_item_id', $returnIds)->delete();
            DB::table('shipment_return_items')->whereIn('id', $returnIds)->delete();
            DB::table('inventory_movements')->whereIn('variant_id', [$this->variant->id])->delete();
            DB::table('sales_ledger_entries')->whereIn('order_id', $orderIds)->delete();
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
            DB::table('user_roles')->whereIn('user_id', [$this->actor->id, $this->customer->id])->delete();
            DB::table('users')->where('id', $this->customer->id)->delete();
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
