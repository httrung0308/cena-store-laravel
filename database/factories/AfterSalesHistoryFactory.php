<?php

namespace Database\Factories;

use App\Enums\AfterSalesEventType;
use App\Enums\AfterSalesStatus;
use App\Models\AfterSalesHistory;
use App\Models\AfterSalesRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AfterSalesHistory> */
class AfterSalesHistoryFactory extends Factory
{
    protected $model = AfterSalesHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'after_sales_request_id' => AfterSalesRequest::factory(),
            'actor_name_snapshot' => 'System',
            'to_status' => AfterSalesStatus::New,
            'event_type' => AfterSalesEventType::Created,
            'command_key' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}
