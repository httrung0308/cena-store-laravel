<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\AfterSalesRequest;
use App\Models\OrderItemUnit;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('enumCasts')]
    public function test_canonical_enum_values_hydrate_and_write_the_exact_database_string(string $class, string $field, string $enum, string $value): void
    {
        $model = new $class;
        $model->setRawAttributes([$field => $value]);

        $this->assertInstanceOf($enum, $model->getAttribute($field));
        $this->assertSame($value, $model->getAttribute($field)->value);
        $model->setAttribute($field, $enum::from($value));
        $this->assertSame($value, $model->getAttributes()[$field]);
    }

    #[DataProvider('models')]
    public function test_each_domain_model_persists_and_casts_the_canonical_columns(string $table, string $class): void
    {
        $this->freezeTime();
        $model = $class::factory()->create()->refresh();
        $columns = self::schema()[$table]['columns'];
        $enums = self::contract()[$table]['enums'];
        $this->assertSame($table, $model->getTable());
        $this->assertIsInt($model->id);
        foreach ($columns as $name => $column) {
            $value = $model->getAttribute($name);
            if ($value === null) {
                $this->assertTrue($column['nullable'], $table.'.'.$name.' unexpectedly null');

                continue;
            }
            if (isset($enums[$name])) {
                $this->assertInstanceOf($enums[$name], $value, $table.'.'.$name);
                $this->assertSame((string) $model->getRawOriginal($name), $value->value);
            } elseif ($column['type'] === 'tinyint(1)') {
                $this->assertIsBool($value);
            } elseif (preg_match('/^(datetime|date)/', $column['type'])) {
                $this->assertInstanceOf(CarbonImmutable::class, $value);
                $this->assertSame('UTC', $value->timezoneName);
            } elseif ($column['type'] === 'json') {
                $this->assertIsArray($value);
            } elseif (str_starts_with($column['type'], 'decimal')) {
                $this->assertIsString($value);
            } elseif (preg_match('/^(bigint|int|smallint|tinyint)/', $column['type'])) {
                $this->assertIsInt($value);
            }
        }
        if (isset($columns['created_at']) && ! isset($columns['updated_at'])) {
            $this->assertNull($model->getUpdatedAtColumn());
            $this->assertNotNull($model->created_at);
        }
        if (! isset($columns['created_at'])) {
            $this->assertFalse($model->usesTimestamps());
        }
        $this->assertDatabaseHas($table, ['id' => $model->id]);
    }

    #[DataProvider('foreignRelationships')]
    public function test_belongs_to_uses_the_correct_real_parent_and_retains_history_when_required(string $class, string $column, string $relationship, string $parentClass, bool $history): void
    {
        $parent = $parentClass::factory()->create();
        $child = new $class;
        $child->forceFill([$column => $parent->id]);

        $this->assertTrue($child->{$relationship}()->first()->is($parent));
        if (in_array(SoftDeletes::class, class_uses_recursive($parentClass), true)) {
            $parent->delete();
            $found = $child->{$relationship}()->first();
            if ($history) {
                $this->assertNotNull($found);
                $this->assertTrue($found->is($parent));
            } else {
                $this->assertNull($found);
            }
        }
    }

    public function test_unit_to_item_order_owner_and_rma_chain_stays_consistent(): void
    {
        $unit = OrderItemUnit::factory()->create();
        $request = AfterSalesRequest::factory()->for($unit, 'unit')->create();
        $unit->load('item.order.user', 'currentVariant', 'afterSalesRequests');

        $this->assertTrue($unit->currentVariant->is($unit->item->variant));
        $this->assertSame($unit->item->order->user_id, $unit->item->order->user->id);
        $this->assertTrue($unit->item->order->user->orders->contains($unit->item->order));
        $this->assertTrue($unit->item->units->contains($unit));
        $this->assertTrue($unit->afterSalesRequests->contains($request));
        $this->assertTrue($request->unit->item->order->user->is($unit->item->order->user));
        $this->assertSame($unit->current_variant_id, $request->source_variant_id);
    }

    public function test_custom_role_pivot_uses_assigned_at_and_roles_are_queried_by_slug(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['slug' => RoleSlug::Staff, 'name' => 'Staff']);
        $assigned = CarbonImmutable::parse('2026-10-31 12:00:00.123456', 'UTC');
        UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'assigned_at' => $assigned]);
        $loaded = $user->roles()->first();

        $this->assertTrue($user->hasRole(RoleSlug::Staff));
        $this->assertFalse($user->hasRole(RoleSlug::Admin));
        $this->assertInstanceOf(UserRole::class, $loaded->assignment);
        $this->assertIsInt($loaded->assignment->id);
        $this->assertSame('123456', $loaded->assignment->assigned_at->format('u'));
        $this->assertTrue($role->users->contains($user));
        $this->assertCount(1, $user->roleAssignments);
        $user->load('roles');
        $user->roles()->detach($role);
        $this->assertFalse($user->hasRole(RoleSlug::Staff));
    }

    public function test_catalog_eager_loading_uses_a_constant_five_queries_and_correct_thumbnail(): void
    {
        foreach (range(1, 4) as $index) {
            $product = Product::factory()->create();
            $color = ProductColor::factory()->for($product)->create();
            ProductImage::factory()->for($color, 'color')->thumbnail()->create();
            ProductImage::factory()->for($color, 'color')->create();
            ProductVariant::factory()->for($color, 'color')->stocked()->create();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        $products = Product::with(['category', 'colors.thumbnail', 'colors.variants'])->get();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(5, $queries);
        $this->assertCount(4, $products);
        foreach ($products as $product) {
            $this->assertTrue($product->colors->first()->thumbnail->is_thumbnail);
            $this->assertCount(1, $product->colors->first()->variants);
            $this->assertTrue($product->category->products->contains($product));
            $this->assertCount(1, $product->variants);
        }
    }

    public function test_money_cost_boolean_json_and_microseconds_round_trip_without_float_conversion(): void
    {
        $stamp = CarbonImmutable::parse('2026-10-31 12:00:00.123456', 'UTC');
        $variant = ProductVariant::factory()->stocked(2, '123456.7891')->create(['size_value' => '42.5', 'size_numeric' => '42.5', 'price_override' => 999999999, 'is_active' => false, 'created_at' => $stamp]);
        $product = $variant->color->product;
        $product->specifications = ['material' => 'mesh'];
        $product->save();
        $variant->refresh();

        $this->assertSame('123456.7891', $variant->average_cost);
        $this->assertSame(999999999, $variant->price_override);
        $this->assertSame('42.5', $variant->size_value);
        $this->assertSame('42.5', $variant->size_numeric);
        $this->assertFalse($variant->is_active);
        $this->assertSame('123456', $variant->created_at->format('u'));
        $this->assertSame(['material' => 'mesh'], $product->fresh()->specifications);
    }

    public function test_password_hash_and_encrypted_refund_destination_are_not_plaintext_or_serialized(): void
    {
        $user = User::factory()->create(['password' => 'controlled-test-password']);
        $refund = Refund::factory()->create(['destination_ciphertext' => ['bank' => 'TEST', 'account' => 'SENSITIVE-ACCOUNT']])->refresh();

        $this->assertTrue(Hash::check('controlled-test-password', $user->password));
        $this->assertStringNotContainsString('SENSITIVE-ACCOUNT', DB::table('refunds')->where('id', $refund->id)->value('destination_ciphertext'));
        $this->assertSame(['bank' => 'TEST', 'account' => 'SENSITIVE-ACCOUNT'], $refund->destination_ciphertext);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('auth_version', $user->toArray());
        $this->assertArrayNotHasKey('destination_ciphertext', $refund->toArray());
    }

    #[DataProvider('protectedFields')]
    public function test_public_mass_assignment_cannot_change_server_owned_attributes(string $class, string $field): void
    {
        $model = new $class;
        $before = $model->getAttributes();
        try {
            $model->fill([$field => 'UNTRUSTED-REQUEST-VALUE']);
        } catch (MassAssignmentException) {
        }

        $this->assertSame($before, $model->getAttributes(), $class.'.'.$field);
    }

    /** @return array<string, array{string, class-string}> */
    public static function models(): array
    {
        $result = [];
        foreach (self::contract() as $table => $definition) {
            $result[$table] = [$table, $definition['class']];
        }

        return $result;
    }

    /** @return array<string, array{class-string, string, class-string, string}> */
    public static function enumCasts(): array
    {
        $result = [];
        foreach (self::contract() as $table => $definition) {
            foreach ($definition['enum_cases'] ?? [] as $field => $values) {
                foreach ($values as $value) {
                    $result[$table.'.'.$field.'.'.$value] = [$definition['class'], $field, $definition['enums'][$field], $value];
                }
            }
        }

        return $result;
    }

    /** @return array<string, array{class-string, string, string, class-string, bool}> */
    public static function foreignRelationships(): array
    {
        $result = [];
        foreach (self::contract() as $table => $definition) {
            foreach ($definition['belongs_to'] as $relation) {
                $result[$table.'.'.$relation['column']] = [$definition['class'], $relation['column'], $relation['relation'], $relation['class'], $relation['history']];
            }
        }

        return $result;
    }

    /** @return array<string, array{class-string, string}> */
    public static function protectedFields(): array
    {
        $result = [];
        foreach (self::schema() as $table => $definition) {
            foreach ($definition['columns'] as $column => $metadata) {
                if (isset($metadata['generated']) || preg_match('/cost|cogs|stock_qty|reserved_qty|quarantine_qty/', $column) || in_array($column, ['user_id', 'role_id', 'assigned_by', 'assigned_at', 'is_active', 'auth_version', 'payment_status', 'order_status', 'status', 'applied_amount', 'received_amount', 'paid_amount', 'refunded_amount', 'destination_ciphertext', 'staff_note'], true)) {
                    $result[$table.'.'.$column] = [self::contract()[$table]['class'], $column];
                }
            }
        }
        foreach (['roles', 'role', 'role_ids'] as $field) {
            $result['user.'.$field] = [User::class, $field];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private static function contract(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/model-contract.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/schema-contract.json'), true, flags: JSON_THROW_ON_ERROR)['tables'];
    }
}
