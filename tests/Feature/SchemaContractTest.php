<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaContractTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, int> */
    private array $fixtureIds = [];

    public function test_exact_canonical_table_set_includes_only_six_infrastructure_tables(): void
    {
        $expected = [...array_keys(self::contract()['tables']), ...self::contract()['infrastructure'], 'migrations'];
        $actual = Schema::getTableListing(schemaQualified: false);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
        $this->assertCount(44, $actual);
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    #[DataProvider('domainTables')]
    public function test_real_columns_foreign_keys_indexes_and_enforced_checks_match_canonical_schema(string $table): void
    {
        $expected = self::contract()['tables'][$table];
        $database = DB::connection()->getDatabaseName();
        $actual = collect(DB::select('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$database, $table]))->keyBy('COLUMN_NAME');
        $this->assertSame(array_keys($expected['columns']), $actual->keys()->all(), $table.' columns');
        foreach ($expected['columns'] as $name => $column) {
            $found = $actual[$name];
            $this->assertSame($column['type'], $found->COLUMN_TYPE, $table.'.'.$name.' type');
            $this->assertSame($column['nullable'], $found->IS_NULLABLE === 'YES', $table.'.'.$name.' nullable');
            $this->assertSame($column['default'], $found->COLUMN_DEFAULT, $table.'.'.$name.' default');
            $this->assertSame($column['auto_increment'], str_contains($found->EXTRA, 'auto_increment'));
            if (isset($column['generated'])) {
                $this->assertSame('STORED GENERATED', $found->EXTRA);
                $this->assertNotSame('', $found->GENERATION_EXPRESSION);
            }
        }
        $options = DB::selectOne('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $table]);
        $this->assertSame('InnoDB', $options->ENGINE);
        $this->assertSame('utf8mb4_unicode_ci', $options->TABLE_COLLATION);
        $indexes = DB::select('SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS names FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? GROUP BY INDEX_NAME, NON_UNIQUE', [$database, $table]);
        $keys = array_map(static fn (object $index): array => ['columns' => explode(',', $index->names), 'unique' => (int) $index->NON_UNIQUE === 0], $indexes);
        $this->assertContains(['columns' => ['id'], 'unique' => true], $keys);
        foreach ($expected['indexes'] as $index) {
            $this->assertContains($index, $keys, $table.' index '.implode(',', $index['columns']));
        }
        $foreignKeys = DB::select('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$database, $table]);
        $this->assertCount(count($expected['foreign_keys']), $foreignKeys);
        foreach ($expected['foreign_keys'] as $key) {
            $found = collect($foreignKeys)->firstWhere('COLUMN_NAME', $key['column']);
            $this->assertNotNull($found, $table.'.'.$key['column']);
            $this->assertSame($key['table'], $found->REFERENCED_TABLE_NAME);
            $this->assertSame($key['references'], $found->REFERENCED_COLUMN_NAME);
            $this->assertSame($key['on_delete'], $found->DELETE_RULE);
            $this->assertSame('RESTRICT', $found->UPDATE_RULE);
        }
        $checks = DB::select("SELECT CONSTRAINT_NAME, ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'CHECK'", [$database, $table]);
        $names = array_column($checks, 'CONSTRAINT_NAME');
        $expectedNames = $expected['checks'] ?? [];
        sort($names);
        sort($expectedNames);
        $this->assertSame($expectedNames, $names);
        foreach ($checks as $constraint) {
            $this->assertSame('YES', $constraint->ENFORCED);
        }
    }

    #[DataProvider('invalidMutations')]
    public function test_mysql_rejects_invalid_values_without_changing_the_row(string $table, array $changes, int $error): void
    {
        $id = $this->row($table);
        $before = (array) DB::table($table)->find($id);

        $this->assertRejected(fn () => DB::table($table)->where('id', $id)->update($changes), $error);

        $this->assertSame($before, (array) DB::table($table)->find($id));
    }

    public function test_duplicate_sku_and_size_are_rejected_even_after_soft_delete(): void
    {
        $id = $this->row('product_variants');
        $copy = (array) DB::table('product_variants')->find($id);
        unset($copy['id']);
        DB::table('product_variants')->where('id', $id)->update(['deleted_at' => '2026-10-31']);
        $copy['size_value'] = '43';
        $copy['size_numeric'] = '43.0';
        $this->assertRejected(fn () => DB::table('product_variants')->insert($copy), 1062);
        $copy['sku'] = 'SECOND-SKU';
        $copy['size_value'] = '42';
        $copy['size_numeric'] = '42.0';
        $this->assertRejected(fn () => DB::table('product_variants')->insert($copy), 1062);
        $this->assertDatabaseCount('product_variants', 1);
    }

    #[DataProvider('conditionalUniqueSlots')]
    public function test_conditional_unique_slot_is_enforced_and_released_by_source_changes(string $table, string $helper, array $activate, array $release, array $uniqueChanges, string $ownerColumn): void
    {
        $id = $this->row($table);
        if ($activate !== []) {
            DB::table($table)->where('id', $id)->update($activate);
        }
        $copy = (array) DB::table($table)->find($id);
        unset($copy['id'], $copy[$helper]);
        $copy = array_replace($copy, $uniqueChanges);

        $this->assertRejected(fn () => DB::table($table)->insert($copy), 1062);
        DB::table($table)->where('id', $id)->update($release);
        $next = DB::table($table)->insertGetId($copy);

        $this->assertNull(DB::table($table)->where('id', $id)->value($helper));
        $this->assertSame($copy[$ownerColumn], DB::table($table)->where('id', $next)->value($helper));
        $this->assertDatabaseCount($table, 2);
    }

    #[DataProvider('terminalRmaStatuses')]
    public function test_terminal_rma_releases_the_unit_slot_without_deleting_history(string $status): void
    {
        $id = $this->row('after_sales_requests');
        DB::table('after_sales_requests')->where('id', $id)->update(['status' => $status]);
        $copy = (array) DB::table('after_sales_requests')->find($id);
        unset($copy['id'], $copy['active_unit_id']);
        $next = DB::table('after_sales_requests')->insertGetId(array_replace($copy, ['request_code' => 'RMA-NEXT', 'command_key' => 'RMA-NEXT', 'status' => 'NEW']));

        $this->assertNull(DB::table('after_sales_requests')->where('id', $id)->value('active_unit_id'));
        $this->assertSame($copy['order_item_unit_id'], DB::table('after_sales_requests')->where('id', $next)->value('active_unit_id'));
        $this->assertDatabaseCount('after_sales_requests', 2);
    }

    public function test_nondefault_addresses_and_nonthumbnail_images_can_coexist(): void
    {
        foreach (['addresses' => 'default_owner_id', 'product_images' => 'thumbnail_color_id'] as $table => $helper) {
            $id = $this->row($table);
            $copy = (array) DB::table($table)->find($id);
            unset($copy['id'], $copy[$helper]);
            $next = DB::table($table)->insertGetId($copy);
            $this->assertNull(DB::table($table)->where('id', $next)->value($helper));
            $this->assertDatabaseCount($table, 2);
        }
    }

    public function test_exact_decimal_cost_half_size_and_signed_ledger_values_are_preserved(): void
    {
        $variant = $this->row('product_variants');
        DB::table('product_variants')->where('id', $variant)->update(['size_value' => '42.5', 'size_numeric' => '42.5', 'average_cost' => '123456.7891', 'stock_qty' => 2, 'reserved_qty' => 2]);
        $ledger = $this->row('sales_ledger_entries');
        DB::table('sales_ledger_entries')->where('id', $ledger)->update(['merchandise_revenue_delta' => -100, 'cogs_delta' => -70]);

        $actual = DB::table('product_variants')->find($variant);
        $this->assertSame('42.5', $actual->size_value);
        $this->assertSame('42.5', $actual->size_numeric);
        $this->assertSame('123456.7891', $actual->average_cost);
        $this->assertSame(-100, DB::table('sales_ledger_entries')->where('id', $ledger)->value('merchandise_revenue_delta'));
        $this->assertSame(-70, DB::table('sales_ledger_entries')->where('id', $ledger)->value('cogs_delta'));
    }

    public function test_only_cart_items_cascade_while_financial_documents_restrict_delete(): void
    {
        $item = $this->row('cart_items');
        DB::table('carts')->where('id', DB::table('cart_items')->where('id', $item)->value('cart_id'))->delete();
        $this->assertDatabaseMissing('cart_items', ['id' => $item]);
        $order = $this->row('orders');
        $this->row('payments');
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order)->delete(), 1451);
        $this->assertDatabaseHas('orders', ['id' => $order]);
    }

    public function test_valid_external_image_and_guest_cart_sources_are_accepted(): void
    {
        $image = $this->row('product_images');
        DB::table('product_images')->where('id', $image)->update(['source_type' => 'EXTERNAL_URL', 'storage_path' => null, 'external_url' => 'https://example.test/shoe.jpg']);
        $cart = $this->row('carts');
        DB::table('carts')->where('id', $cart)->update(['user_id' => null, 'guest_token_hash' => str_repeat('a', 64)]);
        $this->assertDatabaseHas('product_images', ['id' => $image, 'storage_path' => null, 'source_type' => 'EXTERNAL_URL']);
        $this->assertDatabaseHas('carts', ['id' => $cart, 'user_id' => null, 'guest_token_hash' => str_repeat('a', 64)]);
    }

    /** @return array<string, array{string}> */
    public static function domainTables(): array
    {
        $result = [];
        foreach (array_keys(self::contract()['tables']) as $table) {
            $result[$table] = [$table];
        }

        return $result;
    }

    /** @return array<string, array{string}> */
    public static function terminalRmaStatuses(): array
    {
        return ['completed' => ['COMPLETED'], 'rejected' => ['REJECTED'], 'cancelled' => ['CANCELLED']];
    }

    /** @return array<string, array{string, string, array, array, array, string}> */
    public static function conditionalUniqueSlots(): array
    {
        $result = [
            'live default address' => ['addresses', 'default_owner_id', ['is_default' => 1], ['deleted_at' => '2026-10-31'], [], 'user_id'],
            'thumbnail per color' => ['product_images', 'thumbnail_color_id', ['is_thumbnail' => 1], ['is_thumbnail' => 0], [], 'product_color_id'],
            'pending payment per order' => ['payments', 'pending_order_id', [], ['status' => 'EXPIRED'], ['attempt_ref' => 'SECOND', 'idempotency_key' => 'SECOND'], 'order_id'],
        ];
        foreach (['NEW', 'APPROVED', 'RECEIVED', 'PROCESSING', 'AWAITING_REFUND', 'READY_FOR_PICKUP'] as $status) {
            $result['RMA '.$status] = ['after_sales_requests', 'active_unit_id', ['status' => $status], ['status' => 'COMPLETED'], ['request_code' => 'SECOND', 'command_key' => 'SECOND'], 'order_item_unit_id'];
        }

        return $result;
    }

    /** @return array<string, array{string, array<string, mixed>, int}> */
    public static function invalidMutations(): array
    {
        return [
            'foreign key' => ['product_variants', ['product_color_id' => 999999999], 1452],
            'negative stock' => ['product_variants', ['stock_qty' => -1], 1264],
            'negative reserved' => ['product_variants', ['reserved_qty' => -1], 1264],
            'negative quarantine' => ['product_variants', ['quarantine_qty' => -1], 1264],
            'reserve exceeds stock' => ['product_variants', ['reserved_qty' => 1], 3819],
            'negative cost' => ['product_variants', ['average_cost' => '-0.0001'], 3819],
            'zero override' => ['product_variants', ['price_override' => 0], 3819],
            'zero size' => ['product_variants', ['size_numeric' => 0], 3819],
            'zero base price' => ['products', ['base_price' => 0], 3819],
            'sale above base' => ['products', ['promotion_price' => 101], 3819],
            'zero sale' => ['products', ['promotion_price' => 0], 3819],
            'rating aggregate' => ['products', ['rating_avg' => 5.01], 3819],
            'boolean value' => ['users', ['is_active' => 2], 3819],
            'auth version' => ['users', ['auth_version' => 0], 3819],
            'OTP attempts' => ['password_reset_challenges', ['attempts' => 6], 3819],
            'case sensitive role' => ['roles', ['slug' => 'Admin'], 3819],
            'gender enum' => ['categories', ['gender_group' => 'OTHER'], 3819],
            'hex' => ['product_colors', ['primary_hex' => '#ZZZZZZ'], 3819],
            'both image sources' => ['product_images', ['external_url' => 'https://example.test/shoe.jpg'], 3819],
            'neither image source' => ['product_images', ['storage_path' => null], 3819],
            'source enum' => ['product_images', ['source_type' => 'FTP'], 3819],
            'both owners' => ['carts', ['guest_token_hash' => str_repeat('b', 64)], 3819],
            'neither owner' => ['carts', ['user_id' => null], 3819],
            'cart version' => ['carts', ['version' => 0], 3819],
            'zero cart quantity' => ['cart_items', ['quantity' => 0], 3819],
            'cart quantity limit' => ['cart_items', ['quantity' => 11], 3819],
            'percentage limit' => ['coupons', ['value' => 101], 3819],
            'free ship value' => ['coupons', ['type' => 'FREE_SHIPPING', 'value' => 1], 3819],
            'coupon dates' => ['coupons', ['starts_at' => '2026-11-01', 'expires_at' => '2026-10-31'], 3819],
            'import quantity' => ['stock_import_items', ['quantity' => 0], 3819],
            'import subtotal' => ['stock_import_items', ['subtotal_amount' => 101], 3819],
            'movement balance' => ['inventory_movements', ['stock_delta' => 1], 3819],
            'unknown order status' => ['orders', ['order_status' => 'UNKNOWN'], 3819],
            'case sensitive order status' => ['orders', ['order_status' => 'pending'], 3819],
            'order total' => ['orders', ['total_amount' => 101], 3819],
            'shipping total' => ['orders', ['shipping_fee' => 1], 3819],
            'overcollection' => ['orders', ['paid_amount' => 101], 3819],
            'overrefund' => ['orders', ['refunded_amount' => 1], 3819],
            'line quantity' => ['order_items', ['quantity' => 11], 3819],
            'line subtotal' => ['order_items', ['subtotal_amount' => 101], 3819],
            'line net' => ['order_items', ['net_amount' => 99], 3819],
            'unit number' => ['order_item_units', ['unit_no' => 0], 3819],
            'exchange once' => ['order_item_units', ['exchange_completed_count' => 2], 3819],
            'whole unit refund' => ['order_item_units', ['refunded_merchandise_amount' => 50], 3819],
            'applied limit' => ['payments', ['applied_amount' => 1], 3819],
            'currency VND' => ['payments', ['currency' => 'USD'], 3819],
            'event status' => ['payment_events', ['processing_status' => 'UNKNOWN'], 3819],
            'zero refund' => ['refunds', ['amount' => 0], 3819],
            'refund sum' => ['refunds', ['amount' => 99], 3819],
            'shipment status' => ['shipments', ['status' => 'UNKNOWN'], 3819],
            'delivery proof mime' => ['shipment_delivery_proofs', ['mime_type' => 'image/svg+xml'], 3819],
            'delivery proof size' => ['shipment_delivery_proofs', ['size_bytes' => 5242881], 3819],
            'return cost' => ['shipment_return_items', ['cost_basis' => '-1.0000'], 3819],
            'half star' => ['reviews', ['rating' => 4.1], 3819],
            'rating minimum' => ['reviews', ['rating' => 0], 3819],
            'review text' => ['reviews', ['comment' => 'short'], 3819],
            'warranty custody' => ['after_sales_requests', ['source_in_quarantine' => 1], 3819],
            'exchange target' => ['after_sales_requests', ['type' => 'SIZE_EXCHANGE'], 3819],
            'RMA status' => ['after_sales_requests', ['status' => 'UNKNOWN'], 3819],
            'history visibility' => ['after_sales_histories', ['visibility' => 'PUBLIC'], 3819],
            'private file' => ['after_sales_attachments', ['disk' => 'public'], 3819],
            'file size' => ['after_sales_attachments', ['size_bytes' => 5242881], 3819],
            'file mime' => ['after_sales_attachments', ['mime_type' => 'image/svg+xml'], 3819],
            'ledger origin' => ['sales_ledger_entries', ['order_id' => null, 'inventory_movement_id' => null], 3819],
            'outbox status' => ['outbox_events', ['status' => 'UNKNOWN'], 3819],
        ];
    }

    /** @return array<string, mixed> */
    private static function contract(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/schema-contract.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertRejected(callable $operation, int $error): void
    {
        try {
            $operation();
            $this->fail('MySQL accepted a row violating the schema contract.');
        } catch (QueryException $exception) {
            $this->assertSame($error, (int) $exception->errorInfo[1], $exception->getMessage());
        }
    }

    /** Minimal structural fixtures, pending domain models/factories in P01.02/P01.04. */
    private function row(string $table): int
    {
        if (isset($this->fixtureIds[$table])) {
            return $this->fixtureIds[$table];
        }
        $definition = self::contract()['tables'][$table];
        $foreignKeys = array_column($definition['foreign_keys'], null, 'column');
        $data = [];
        foreach ($definition['columns'] as $column => $type) {
            if ($column === 'id' || isset($type['generated']) || $type['nullable'] || $type['default'] !== null) {
                continue;
            }
            if (isset($foreignKeys[$column])) {
                $data[$column] = $this->row($foreignKeys[$column]['table']);
            } elseif (str_starts_with($type['type'], 'datetime')) {
                $data[$column] = '2026-10-31 00:00:00.123456';
            } elseif ($type['type'] === 'date') {
                $data[$column] = '2026-10-31';
            } elseif ($type['type'] === 'json') {
                $data[$column] = '{}';
            } elseif (preg_match('/^(bigint|int|smallint|tinyint|decimal)/', $type['type'])) {
                $data[$column] = 0;
            } else {
                $length = preg_match('/\((\d+)\)/', $type['type'], $matches) ? (int) $matches[1] : 100;
                $data[$column] = substr($table.'-'.$column.'-schema-fixture', 0, $length);
            }
        }
        $overrides = [
            'users' => ['email' => 'schema@example.test'], 'roles' => ['slug' => 'customer'],
            'administrative_units' => ['level' => 'PROVINCE'], 'categories' => ['gender_group' => 'MEN'],
            'products' => ['base_price' => 100], 'product_colors' => ['primary_hex' => '#000000', 'color_key' => 'black'],
            'product_images' => ['source_type' => 'UPLOAD', 'storage_path' => 'products/schema.jpg'],
            'product_variants' => ['size_value' => '42', 'size_numeric' => '42.0'],
            'stock_import_items' => ['quantity' => 1, 'unit_cost_amount' => 100, 'subtotal_amount' => 100],
            'inventory_movements' => ['action' => 'IMPORT_POSTED', 'reference_type' => 'STOCK_IMPORT'],
            'coupons' => ['type' => 'PERCENTAGE', 'value' => 10],
            'orders' => ['subtotal_amount' => 100, 'shipping_base_fee' => 0, 'shipping_fee' => 0, 'total_amount' => 100, 'payment_method' => 'COD', 'source_cart_version' => 1],
            'order_items' => ['quantity' => 1, 'original_unit_price' => 100, 'unit_price' => 100, 'subtotal_amount' => 100, 'net_amount' => 100, 'exchange_days_snapshot' => 7, 'warranty_months_snapshot' => 6],
            'order_item_units' => ['unit_no' => 1, 'gross_amount' => 100, 'net_amount' => 100],
            'order_status_histories' => ['to_status' => 'PENDING'], 'payments' => ['method' => 'COD', 'expected_amount' => 100],
            'payment_events' => ['provider' => 'COD', 'processing_status' => 'RECEIVED'],
            'refunds' => ['reason_type' => 'ORDER_CANCELLED', 'destination_type' => 'CASH', 'merchandise_amount' => 100, 'amount' => 100],
            'reviews' => ['rating' => '4.5'], 'after_sales_requests' => ['type' => 'WARRANTY_REPAIR'],
            'after_sales_histories' => ['to_status' => 'NEW', 'event_type' => 'CREATED'],
            'after_sales_attachments' => ['mime_type' => 'image/jpeg', 'size_bytes' => 1024],
            'shipment_delivery_proofs' => ['mime_type' => 'image/jpeg', 'size_bytes' => 1024, 'sha256' => str_repeat('a', 64)],
            'sales_ledger_entries' => ['type' => 'SALE_RECOGNIZED'], 'outbox_events' => ['aggregate_type' => 'ORDER', 'aggregate_id' => 1],
        ];
        if ($table === 'carts' || $table === 'sales_ledger_entries') {
            $data[$table === 'carts' ? 'user_id' : 'order_id'] = $this->row($table === 'carts' ? 'users' : 'orders');
        }
        if ($table === 'addresses') {
            $province = $this->row('administrative_units');
            $data['ward_id'] = DB::table('administrative_units')->insertGetId(['code' => 'WARD-TEST', 'name' => 'Test Ward', 'level' => 'WARD', 'parent_id' => $province, 'dataset_version' => 'schema-fixture', 'valid_from' => '2026-10-31', 'created_at' => '2026-10-31', 'updated_at' => '2026-10-31']);
        }

        return $this->fixtureIds[$table] = DB::table($table)->insertGetId(array_replace($data, $overrides[$table] ?? []));
    }
}
