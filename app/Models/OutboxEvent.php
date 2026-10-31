<?php

namespace App\Models;

use App\Enums\OutboxAggregateType;
use App\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable([])]
#[Hidden([])]
class OutboxEvent extends DomainModel
{
    protected $table = 'outbox_events';

    protected $attributes = [
        'status' => 'PENDING',
        'attempts' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'aggregate_type' => OutboxAggregateType::class,
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'lease_until' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
