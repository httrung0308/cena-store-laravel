<?php

namespace Database\Factories;

use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockImport> */
class StockImportFactory extends Factory
{
    protected $model = StockImport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'import_code' => 'NK-'.Str::ulid(),
            'supplier_id' => Supplier::factory(),
            'supplier_code_snapshot' => fn (array $attributes) => Supplier::findOrFail($attributes['supplier_id'])->code,
            'supplier_name_snapshot' => fn (array $attributes) => Supplier::findOrFail($attributes['supplier_id'])->name,
            'created_by' => User::factory(),
            'command_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
