<?php

namespace App\Models;

use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class OperationsWorkItem extends DomainModel
{
    protected $table = 'operations_work_items';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'created_by' => 'integer',
            'version' => 'integer',
            'source_type' => OperationsSourceType::class,
            'status' => OperationsWorkItemStatus::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OperationsWorkItemHistory::class, 'work_item_id');
    }
}
