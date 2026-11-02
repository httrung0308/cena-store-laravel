<?php

namespace Tests\Feature;

use App\Models\AdministrativeUnit;
use App\Support\AdministrativeDataset;
use App\Support\BusinessClock;
use Database\Seeders\AdministrativeUnitSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdministrativeDatasetTest extends TestCase
{
    use DatabaseTransactions;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/admin-units-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    /** SYNTHETIC TEST ONLY: two provinces, three wards, leading-zero codes. */
    private function syntheticRecords(): array
    {
        $row = fn (string $code, string $name, string $level, ?string $parent): array => [
            'code' => $code, 'name' => $name, 'level' => $level, 'parent_code' => $parent,
            'dataset_version' => 'test-v1', 'valid_from' => '2026-09-20', 'valid_to' => null, 'is_active' => true,
        ];

        return [
            $row('01', 'Tỉnh Thử Một', 'PROVINCE', null),
            $row('79', 'Thành phố Thử', 'PROVINCE', null),
            $row('00004', 'Phường Số Không', 'WARD', '01'),
            $row('00007', 'Xã Bảy', 'WARD', '01'),
            $row('26734', 'Phường Bến Thử', 'WARD', '79'),
        ];
    }

    private function syntheticManifest(string $bytes, array $overrides = []): array
    {
        return array_merge([
            'schema_version' => 1,
            'dataset_version' => 'test-v1',
            'hcm_province_code' => '79',
            'effective_as_of' => '2026-09-20',
            'retrieved_at' => '2026-11-02',
            'source_urls' => ['https://example.test/tinh.json'],
            'legal_basis_urls' => ['https://example.test/qd'],
            'license' => 'CC BY 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
            'attribution' => 'Synthetic test data',
            'province_count' => 2,
            'ward_count' => 3,
            'record_count' => 5,
            'file' => 'administrative_units.json',
            'sha256' => hash('sha256', $bytes),
        ], $overrides);
    }

    private function write(array $records, array $manifestOverrides = [], ?string $bytes = null): string
    {
        $bytes ??= json_encode($records, JSON_UNESCAPED_UNICODE);
        File::put($this->directory.'/administrative_units.json', $bytes);
        File::put($this->directory.'/manifest.json', json_encode($this->syntheticManifest($bytes, $manifestOverrides), JSON_UNESCAPED_UNICODE));

        return $this->directory;
    }

    public function test_committed_snapshot_is_complete_and_keeps_string_codes(): void
    {
        $dataset = AdministrativeDataset::fromDirectory();
        $provinces = array_values(array_filter($dataset->records, fn (array $r): bool => $r['level'] === 'PROVINCE'));
        $byCode = array_column($dataset->records, 'name', 'code');

        $this->assertSame('dvc-20260920', $dataset->version());
        $this->assertCount(34, $provinces);
        $this->assertCount(3355, $dataset->records);
        $this->assertSame('01', $provinces[0]['code']);
        $this->assertSame('Thành phố Hồ Chí Minh', $byCode['79']);
        $this->assertSame('79', config('store.shipping.hcm_province_code'));
        $this->assertSame('dvc-20260920', config('store.shipping.dataset_version'));
        $this->assertSame('CC BY 4.0', $dataset->manifest['license']);
        $this->assertStringContainsString('diachiviet.com', $dataset->manifest['attribution']);
    }

    public function test_tampered_file_is_rejected_by_checksum(): void
    {
        $bytes = json_encode($this->syntheticRecords(), JSON_UNESCAPED_UNICODE);
        $this->write($this->syntheticRecords(), [], $bytes);
        File::put($this->directory.'/administrative_units.json', str_replace('Xã Bảy', 'Xã Tám', $bytes));

        $this->expectExceptionMessage('checksum');
        AdministrativeDataset::fromDirectory($this->directory);
    }

    /** @return array<string, array{\Closure(array): array, array<string, mixed>}> */
    public static function invalidSnapshots(): array
    {
        return [
            'unknown manifest field' => [fn (array $r): array => $r, ['extra' => true]],
            'schema version as string' => [fn (array $r): array => $r, ['schema_version' => '1']],
            'http source' => [fn (array $r): array => $r, ['source_urls' => ['http://example.test/x']]],
            'retrieved before effective' => [fn (array $r): array => $r, ['retrieved_at' => '2026-09-19']],
            'wrong counts' => [fn (array $r): array => $r, ['ward_count' => 4, 'record_count' => 6]],
            'hcm not a province' => [fn (array $r): array => $r, ['hcm_province_code' => '02']],
            'integer code' => [fn (array $r): array => [array_replace($r[0], ['code' => 1]), ...array_slice($r, 1)], []],
            'code lost its zero' => [fn (array $r): array => [...array_slice($r, 0, 2), array_replace($r[2], ['code' => '4']), ...array_slice($r, 3)], []],
            'missing field' => [fn (array $r): array => [array_diff_key($r[0], ['valid_to' => 1]), ...array_slice($r, 1)], []],
            'district level' => [fn (array $r): array => [...array_slice($r, 0, 2), array_replace($r[2], ['level' => 'DISTRICT']), ...array_slice($r, 3)], []],
            'orphan ward' => [fn (array $r): array => [...array_slice($r, 0, 4), array_replace($r[4], ['parent_code' => '02'])], []],
            'duplicate code' => [fn (array $r): array => [...array_slice($r, 0, 4), array_replace($r[4], ['code' => '00004'])], []],
            'other version' => [fn (array $r): array => [...array_slice($r, 0, 4), array_replace($r[4], ['dataset_version' => 'test-v2'])], []],
            'is_active as 1' => [fn (array $r): array => [...array_slice($r, 0, 4), array_replace($r[4], ['is_active' => 1])], []],
            'padded name' => [fn (array $r): array => [...array_slice($r, 0, 4), array_replace($r[4], ['name' => ' Phường '])], []],
        ];
    }

    #[DataProvider('invalidSnapshots')]
    public function test_invalid_snapshots_are_rejected(\Closure $mutate, array $manifestOverrides): void
    {
        $this->write($mutate($this->syntheticRecords()), $manifestOverrides);

        $this->expectException(RuntimeException::class);
        AdministrativeDataset::fromDirectory($this->directory);
    }

    public function test_seeder_imports_with_parents_and_is_idempotent(): void
    {
        $dataset = AdministrativeDataset::fromDirectory($this->write($this->syntheticRecords()));
        $seeder = new AdministrativeUnitSeeder($dataset);

        $seeder->run(app(BusinessClock::class));
        $seeder->run(app(BusinessClock::class));

        $units = AdministrativeUnit::query()->where('dataset_version', 'test-v1')->get()->keyBy('code');
        $this->assertCount(5, $units);
        $this->assertSame('01', $units['01']->code);
        $this->assertSame($units['01']->id, $units['00004']->parent_id);
        $this->assertSame($units['79']->id, $units['26734']->parent_id);
        $this->assertNull($units['79']->parent_id);
    }

    public function test_seeder_refuses_to_silently_change_an_imported_unit(): void
    {
        $dataset = AdministrativeDataset::fromDirectory($this->write($this->syntheticRecords()));
        (new AdministrativeUnitSeeder($dataset))->run(app(BusinessClock::class));
        DB::table('administrative_units')->where('dataset_version', 'test-v1')->where('code', '00007')->update(['name' => 'Tên đã sửa tay']);

        try {
            (new AdministrativeUnitSeeder($dataset))->run(app(BusinessClock::class));
            $this->fail('Expected the seeder to stop.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('00007', $exception->getMessage());
        }
        $this->assertSame('Tên đã sửa tay', DB::table('administrative_units')->where('dataset_version', 'test-v1')->where('code', '00007')->value('name'));
    }

    public function test_full_snapshot_seeds_twice_without_duplicates(): void
    {
        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(AdministrativeUnitSeeder::class);

        $query = DB::table('administrative_units')->where('dataset_version', 'dvc-20260920');
        $this->assertSame(34, (clone $query)->where('level', 'PROVINCE')->count());
        $this->assertSame(3321, (clone $query)->where('level', 'WARD')->count());
        $this->assertSame(0, DB::table('administrative_units as w')
            ->join('administrative_units as p', 'p.id', '=', 'w.parent_id')
            ->where('w.dataset_version', 'dvc-20260920')
            ->where(fn ($q) => $q->where('p.level', '!=', 'PROVINCE')->orWhereColumn('p.dataset_version', '!=', 'w.dataset_version'))
            ->count());
    }

    public function test_lookup_api_returns_current_snapshot_with_string_codes_only(): void
    {
        (new AdministrativeUnitSeeder(AdministrativeDataset::fromDirectory($this->write($this->syntheticRecords()))))->run(app(BusinessClock::class));
        $old = AdministrativeUnit::factory()->create(['code' => '01', 'name' => 'Tỉnh Cũ', 'dataset_version' => 'test-v0']);
        AdministrativeUnit::factory()->wardOf($old)->create(['code' => '00099', 'name' => 'Xã Cũ']);
        config(['store.shipping.dataset_version' => 'test-v1']);

        $this->getJson(route('locations.provinces'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['code' => '79', 'name' => 'Thành phố Thử', 'dataset_version' => 'test-v1'])
            ->assertJsonPath('data.1.code', '01')
            ->assertDontSee('Tỉnh Cũ');

        $this->getJson(route('locations.wards', ['provinceCode' => '01']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['code' => '00004', 'name' => 'Phường Số Không', 'parent_code' => '01', 'dataset_version' => 'test-v1'])
            ->assertDontSee('Xã Cũ')
            ->assertDontSee('"id"', false);

        $this->getJson(route('locations.wards', ['provinceCode' => '02']))->assertNotFound();
        $this->getJson('/api/locations/provinces/1/wards')->assertNotFound();
        $this->getJson('/api/locations/provinces/01%27/wards')->assertNotFound();
    }
}
