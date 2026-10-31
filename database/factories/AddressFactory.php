<?php

namespace Database\Factories;

use App\Enums\AdministrativeUnitLevel;
use App\Models\Address;
use App\Models\AdministrativeUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Address> */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'phone' => '0900000000',
            'province_id' => AdministrativeUnit::factory(),
            'ward_id' => fn (array $attributes) => AdministrativeUnit::factory()->state(['level' => AdministrativeUnitLevel::Ward, 'parent_id' => $attributes['province_id']])->create()->id,
            'province_code_snapshot' => fn (array $attributes) => AdministrativeUnit::findOrFail($attributes['province_id'])->code,
            'province_name_snapshot' => fn (array $attributes) => AdministrativeUnit::findOrFail($attributes['province_id'])->name,
            'ward_code_snapshot' => fn (array $attributes) => AdministrativeUnit::findOrFail($attributes['ward_id'])->code,
            'ward_name_snapshot' => fn (array $attributes) => AdministrativeUnit::findOrFail($attributes['ward_id'])->name,
            'dataset_version' => 'factory-v1',
            'street_address' => '1 Test Street',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
