<?php

namespace Database\Factories;

use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InventoryMovement> */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'action' => InventoryAction::ImportPosted,
            'stock_before' => 0,
            'stock_after' => 1,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'quarantine_before' => 0,
            'quarantine_after' => 0,
            'average_cost_before' => '0.0000',
            'average_cost_after' => '100.0000',
            'reference_type' => InventoryReferenceType::StockImport,
            'operation_key' => (string) Str::uuid(),
            'created_at' => now(),
            'reference_id' => StockImport::factory(),
            'stock_delta' => 1,
        ];
    }
}
