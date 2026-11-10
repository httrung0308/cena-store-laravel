<?php

namespace Database\Factories;

use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use App\Models\OperationsWorkItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OperationsWorkItem> */
class OperationsWorkItemFactory extends Factory
{
    protected $model = OperationsWorkItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $key = (string) Str::uuid();

        return [
            'source_type' => OperationsSourceType::Order,
            'source_event_key' => $key,
            'source_reference' => 'ORD-'.Str::upper(Str::random(8)),
            'source_label' => 'Đơn hàng',
            'title' => 'Kiểm tra yêu cầu vận hành',
            'content' => 'Nội dung công việc cần được nhân viên kiểm tra.',
            'occurred_at' => now(),
            'status' => OperationsWorkItemStatus::Pending,
            'version' => 1,
            'created_by' => User::factory()->staff(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
