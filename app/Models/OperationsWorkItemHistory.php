<?php

namespace App\Models;

use App\Enums\OperationsWorkItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class OperationsWorkItemHistory extends DomainModel
{
    protected $table = 'operations_work_item_histories';

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'work_item_id' => 'integer',
            'actor_id' => 'integer',
            'actor_name_snapshot' => 'string',
            'actor_roles_snapshot' => 'array',
            'from_status' => OperationsWorkItemStatus::class,
            'to_status' => OperationsWorkItemStatus::class,
            'snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(OperationsWorkItem::class, 'work_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
