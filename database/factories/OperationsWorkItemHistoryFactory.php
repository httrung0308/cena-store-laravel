<?php

namespace Database\Factories;

use App\Enums\OperationsWorkItemStatus;
use App\Models\OperationsWorkItem;
use App\Models\OperationsWorkItemHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OperationsWorkItemHistory> */
class OperationsWorkItemHistoryFactory extends Factory
{
    protected $model = OperationsWorkItemHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'work_item_id' => OperationsWorkItem::factory(),
            'actor_id' => User::factory()->staff(),
            'actor_roles_snapshot' => ['staff'],
            'actor_name_snapshot' => 'Nhân viên kiểm thử',
            'from_status' => null,
            'to_status' => OperationsWorkItemStatus::Pending,
            'outcome_code' => null,
            'note' => null,
            'snapshot' => ['title' => 'Kiểm tra công việc'],
            'created_at' => now(),
        ];
    }
}
