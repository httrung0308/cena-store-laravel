<?php

namespace Tests\Feature;

use App\Enums\AdministrativeUnitLevel;
use App\Enums\RoleSlug;
use App\Models\AdministrativeUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Support\BusinessClock;
use Database\Seeders\CoreFoundationSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationCoreTest extends TestCase
{
    use DatabaseTransactions;

    private const DEMO_PASSWORD = 'test-only-demo-passphrase';

    public function test_role_seed_is_idempotent_by_slug_and_keeps_existing_foreign_keys_and_timestamps(): void
    {
        $admin = Role::factory()->administrator()->create();
        $user = User::factory()->create();
        $assignment = UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $admin->id]);
        $this->seed(RolesSeeder::class);
        $first = DB::table('roles')->orderBy('slug')->get()->toArray();
        app(BusinessClock::class)->freeze('2030-01-01T00:00:00Z');
        $this->seed(RolesSeeder::class);
        $this->assertEquals($first, DB::table('roles')->orderBy('slug')->get()->toArray());
        $this->assertSame(3, Role::count());
        $this->assertSame($admin->id, $assignment->fresh()->role_id);
        foreach (RoleSlug::cases() as $slug) {
            $this->assertSame($slug->label(), Role::where('slug', $slug->value)->sole()->name);
        }
    }

    public function test_demo_users_are_opt_in_and_core_seed_never_writes_catalog_stock_or_financial_documents(): void
    {
        config(['store.demo.enabled' => false, 'store.demo.password' => null]);
        $this->seedCore();
        $this->seedCore();
        $this->assertSame(3, Role::count());
        foreach (['users', 'user_roles', 'carts', 'administrative_units', 'products', 'product_variants', 'stock_imports', 'inventory_movements', 'orders', 'payments', 'refunds', 'sales_ledger_entries', 'outbox_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_demo_seed_creates_exactly_three_hashed_accounts_roles_and_empty_carts_and_replay_is_noop(): void
    {
        $this->enableDemo();
        app(BusinessClock::class)->freeze('2026-10-31T07:00:00.123456Z');
        $this->seedCore();
        $before = [];
        foreach (RoleSlug::cases() as $slug) {
            $user = User::where('email', config('store.demo.accounts.'.$slug->value.'.email'))->sole();
            $this->assertTrue(Hash::check(self::DEMO_PASSWORD, $user->password));
            $this->assertNotSame(self::DEMO_PASSWORD, $user->password);
            $this->assertTrue($user->hasRole($slug));
            $this->assertSame(1, $user->roles()->count());
            $this->assertTrue($user->is_active);
            $this->assertSame(1, $user->auth_version);
            $this->assertNull($user->email_verified_at);
            $assignment = $user->roleAssignments()->sole();
            $this->assertNull($assignment->assigned_by);
            $this->assertSame('123456', $assignment->assigned_at->format('u'));
            $cart = $user->carts()->sole();
            $this->assertSame(1, $cart->version);
            $this->assertSame(0, $cart->items()->count());
            $before[$slug->value] = [$user->getAttributes(), $assignment->getAttributes(), $cart->getAttributes()];
        }
        app(BusinessClock::class)->freeze('2027-01-01T00:00:00Z');
        $this->seedCore();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('user_roles', 3);
        $this->assertDatabaseCount('carts', 3);
        foreach (RoleSlug::cases() as $slug) {
            $user = User::where('email', config('store.demo.accounts.'.$slug->value.'.email'))->sole();
            $this->assertSame($before[$slug->value], [$user->getAttributes(), $user->roleAssignments()->sole()->getAttributes(), $user->carts()->sole()->getAttributes()]);
        }
    }

    #[DataProvider('disallowedEnvironments')]
    public function test_enabled_demo_seed_is_refused_outside_local_testing_and_core_transaction_rolls_back(string $environment): void
    {
        $this->enableDemo();
        $this->app['env'] = $environment;
        $this->assertCoreRefused();
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public static function disallowedEnvironments(): array
    {
        return [['production'], ['staging']];
    }

    public function test_disabled_demo_allows_production_role_bootstrap_without_accounts(): void
    {
        $this->app['env'] = 'production';
        config(['store.demo.enabled' => false]);
        $this->seedCore();
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('badPasswords')]
    public function test_invalid_demo_configuration_fails_without_persisting_any_bootstrap_rows(mixed $password): void
    {
        $this->enableDemo();
        config(['store.demo.password' => $password]);
        $this->assertCoreRefused();
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public static function badPasswords(): array
    {
        return [[null], [''], ['short'], [true], [123456789012], [str_repeat('x', 73)]];
    }

    public function test_demo_seeder_requires_roles_and_does_not_bootstrap_privileges_implicitly(): void
    {
        $this->enableDemo();
        try {
            $this->seed(DemoUsersSeeder::class);
            $this->fail('Missing role bootstrap was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('canonical roles', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_existing_account_is_never_promoted_or_password_reset_and_all_new_demo_rows_roll_back(): void
    {
        $this->enableDemo();
        $existing = User::factory()->create(['email' => config('store.demo.accounts.customer.email'), 'password' => 'unrelated-account-password'])->refresh();
        $before = $existing->getAttributes();
        $this->assertCoreRefused();
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertDatabaseCount('carts', 0);
    }

    #[DataProvider('changedDemoAccounts')]
    public function test_replay_never_unlocks_restores_or_regrants_a_changed_demo_admin(string $change): void
    {
        $this->enableDemo();
        $this->seedCore();
        $user = User::where('email', config('store.demo.accounts.admin.email'))->sole();
        match ($change) {
            'inactive' => $user->forceFill(['is_active' => false])->save(),
            'deleted' => $user->delete(),
            'revoked' => $user->roleAssignments()->delete(),
            'password' => $user->forceFill(['password' => 'changed-after-seeding'])->save(),
        };
        $before = $user->fresh()->getAttributes();
        $assignmentCount = $user->roleAssignments()->count();
        $this->assertCoreRefused();
        $this->assertSame($before, User::withTrashed()->findOrFail($user->id)->getAttributes());
        $this->assertSame($assignmentCount, $user->roleAssignments()->count());
        $this->assertDatabaseCount('users', 3);
    }

    public static function changedDemoAccounts(): array
    {
        return [['inactive'], ['deleted'], ['revoked'], ['password']];
    }

    public function test_replay_preserves_profile_edits_and_restores_only_a_missing_empty_cart(): void
    {
        $this->enableDemo();
        $this->seedCore();
        $user = User::where('email', config('store.demo.accounts.customer.email'))->sole();
        $user->full_name = 'Profile edited after seed';
        $user->phone = '0900000000';
        $user->save();
        $user->carts()->delete();
        $before = $user->getAttributes();
        $this->seedCore();
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame(1, $user->carts()->count());
        $this->assertSame(0, $user->carts()->sole()->items()->count());
    }

    #[DataProvider('badAccountConfigs')]
    public function test_demo_account_config_rejects_real_addresses_duplicate_emails_and_unknown_roles(string $change): void
    {
        $this->enableDemo();
        $accounts = config('store.demo.accounts');
        match ($change) {
            'real_address' => $accounts['admin']['email'] = 'admin@example.com',
            'duplicate' => $accounts['staff']['email'] = $accounts['admin']['email'],
            'uppercase' => $accounts['admin']['email'] = 'ADMIN@demo.cena.test',
            'extra_role' => $accounts['root'] = $accounts['admin'],
            'missing_role' => $accounts = ['admin' => $accounts['admin']],
        };
        config(['store.demo.accounts' => $accounts]);
        $this->assertCoreRefused();
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public static function badAccountConfigs(): array
    {
        return [['real_address'], ['duplicate'], ['uppercase'], ['extra_role'], ['missing_role']];
    }

    public function test_palette_preserves_the_twelve_legacy_keys_and_shipping_remains_unresolved_without_reviewed_province_codes(): void
    {
        $palette = config('store.palette');
        $this->assertSame(['red', 'blue', 'green', 'yellow', 'black', 'white', 'gray', 'pink', 'purple', 'orange', 'brown', 'lime'], array_keys($palette));
        foreach ($palette as $key => $color) {
            $this->assertSame(ucfirst($key), $color['label']);
            $this->assertMatchesRegularExpression('/^#[a-f0-9]{6}$/D', $color['hex']);
        }
        $this->assertSame('#78350f', $palette['brown']['hex']);
        $this->assertSame('#a3e635', $palette['lime']['hex']);
        $this->assertSame(2000000, config('store.shipping.free_threshold'));
        $this->assertSame(30000, config('store.shipping.hcm_fee'));
        $this->assertSame(40000, config('store.shipping.other_fee'));
        $this->assertArrayHasKey('hcm_province_code', config('store.shipping'));
        $this->assertArrayHasKey('dataset_version', config('store.shipping'));
        $this->assertFalse(config('store.demo.enabled'));
    }

    public function test_role_factory_states_and_synthetic_ward_factory_keep_parent_version_and_leading_zero_codes(): void
    {
        foreach (['administrator' => RoleSlug::Admin, 'staff' => RoleSlug::Staff, 'customer' => RoleSlug::Customer] as $state => $slug) {
            $this->assertSame($slug, Role::factory()->$state()->create()->slug);
        }
        $province = AdministrativeUnit::factory()->create(['code' => '00001', 'dataset_version' => 'synthetic-test-only-v2']);
        $ward = AdministrativeUnit::factory()->wardOf($province)->create(['code' => '00002']);
        $this->assertSame(AdministrativeUnitLevel::Ward, $ward->level);
        $this->assertSame($province->id, $ward->parent_id);
        $this->assertSame($province->dataset_version, $ward->dataset_version);
        $this->assertSame($province->valid_from->format('Y-m-d'), $ward->valid_from->format('Y-m-d'));
        $this->assertSame('00001', $ward->parent->code);
        $this->assertSame('00002', $ward->code);
    }

    public function test_ward_fixture_refuses_unpersisted_or_non_province_parent(): void
    {
        $province = AdministrativeUnit::factory()->create();
        $ward = AdministrativeUnit::factory()->wardOf($province)->create();
        foreach ([AdministrativeUnit::factory()->make(), $ward] as $parent) {
            try {
                AdministrativeUnit::factory()->wardOf($parent);
                $this->fail('Invalid fixture parent accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('persisted province', $exception->getMessage());
            }
        }
    }

    private function seedCore(): void
    {
        $this->artisan('db:seed', ['--class' => CoreFoundationSeeder::class, '--force' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    private function enableDemo(): void
    {
        config(['store.demo.enabled' => true, 'store.demo.password' => self::DEMO_PASSWORD]);
    }

    private function assertCoreRefused(): void
    {
        try {
            $this->seedCore();
            $this->fail('Unsafe demo seed was accepted.');
        } catch (LogicException $exception) {
            $this->assertSame(LogicException::class, $exception::class);
            $this->assertNotSame('', $exception->getMessage());
            $this->assertStringNotContainsString(self::DEMO_PASSWORD, $exception->getMessage());
        }
    }
}
