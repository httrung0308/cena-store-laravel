<?php

namespace Database\Seeders;

use App\Support\AdministrativeDataset;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the validated province/ward snapshot. Safe to run again: rows already present are
 * compared, never rewritten; any difference stops the import instead of being "fixed" silently.
 */
class AdministrativeUnitSeeder extends Seeder
{
    public function __construct(private readonly ?AdministrativeDataset $dataset = null) {}

    public function run(BusinessClock $clock): void
    {
        $dataset = $this->dataset ?? AdministrativeDataset::fromDirectory();
        $version = $dataset->version();
        $now = $clock->now()->format('Y-m-d H:i:s.u');

        DB::transaction(function () use ($dataset, $version, $now): void {
            foreach (['PROVINCE', 'WARD'] as $level) {
                $existing = DB::table('administrative_units')
                    ->where('dataset_version', $version)->where('level', $level)
                    ->lockForUpdate()->get(['code', 'name', 'parent_id'])->keyBy(fn (object $row): string => 'c'.$row->code);
                $provinceIds = $level === 'WARD'
                    ? DB::table('administrative_units')->where('dataset_version', $version)->where('level', 'PROVINCE')->pluck('id', 'code')->mapWithKeys(fn (int $id, string $code): array => ['c'.$code => $id])
                    : collect();

                $missing = [];
                foreach ($dataset->records as $record) {
                    if ($record['level'] !== $level) {
                        continue;
                    }
                    $parentId = $level === 'WARD' ? $provinceIds->get('c'.$record['parent_code']) : null;
                    $current = $existing->get('c'.$record['code']);
                    if ($current !== null) {
                        if ($current->name !== $record['name'] || ($current->parent_id === null ? null : (int) $current->parent_id) !== $parentId) {
                            throw new RuntimeException("Stored unit {$record['code']} ({$version}) differs from the dataset; publish a new dataset_version instead of editing it.");
                        }

                        continue;
                    }
                    $missing[] = [
                        'code' => $record['code'],
                        'name' => $record['name'],
                        'level' => $level,
                        'parent_id' => $parentId,
                        'dataset_version' => $version,
                        'valid_from' => $record['valid_from'],
                        'valid_to' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($missing, 500) as $chunk) {
                    DB::table('administrative_units')->insert($chunk);
                }
            }
        });
    }
}
