<?php

namespace Database\Factories;

use App\Enums\SalesLedgerType;
use App\Models\Order;
use App\Models\SalesLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SalesLedgerEntry> */
class SalesLedgerEntryFactory extends Factory
{
    protected $model = SalesLedgerEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => SalesLedgerType::SaleRecognized,
            'operation_key' => (string) Str::uuid(),
            'occurred_at' => now(),
            'created_at' => now(),
            'order_id' => Order::factory(),
        ];
    }
}
