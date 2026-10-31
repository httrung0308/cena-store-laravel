<?php

namespace Database\Factories;

use App\Enums\AfterSalesType;
use App\Models\AfterSalesRequest;
use App\Models\OrderItemUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AfterSalesRequest> */
class AfterSalesRequestFactory extends Factory
{
    protected $model = AfterSalesRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_code' => 'RMA-'.Str::ulid(),
            'order_item_unit_id' => OrderItemUnit::factory(),
            'type' => AfterSalesType::WarrantyRepair,
            'source_variant_id' => fn (array $attributes) => OrderItemUnit::findOrFail($attributes['order_item_unit_id'])->current_variant_id,
            'customer_reason' => 'A structurally valid repair request.',
            'command_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
