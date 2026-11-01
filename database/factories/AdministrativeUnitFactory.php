<?php

namespace Database\Factories;

use App\Enums\AdministrativeUnitLevel;
use App\Models\AdministrativeUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/** @extends Factory<AdministrativeUnit> */
class AdministrativeUnitFactory extends Factory
{
    protected $model = AdministrativeUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'P-'.fake()->unique()->numerify('############'),
            'name' => fake()->city(),
            'level' => AdministrativeUnitLevel::Province,
            'dataset_version' => 'factory-v1',
            'valid_from' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** Synthetic fixture state, never a verified administrative dataset. */
    public function wardOf(AdministrativeUnit $province): static
    {
        if (! $province->exists || $province->level !== AdministrativeUnitLevel::Province || $province->parent_id !== null) {
            throw new InvalidArgumentException('A ward fixture needs a persisted province parent.');
        }

        return $this->state([
            'code' => 'W-'.fake()->unique()->numerify('############'),
            'level' => AdministrativeUnitLevel::Ward,
            'parent_id' => $province->id,
            'dataset_version' => $province->dataset_version,
            'valid_from' => $province->valid_from,
            'valid_to' => $province->valid_to,
            'is_active' => $province->is_active,
        ]);
    }
}
