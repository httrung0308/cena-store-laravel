<?php

namespace Database\Factories;

use App\Enums\AdministrativeUnitLevel;
use App\Models\AdministrativeUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

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
}
