<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.Str::uuid(),
            'name' => fake()->company(),
            'phone' => '0900000000',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
