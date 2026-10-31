<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['note'])]
class OrderStatusHistory extends DomainModel
{
    protected $table = 'order_status_histories';

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'actor_id' => 'integer',
            'actor_roles_snapshot' => 'array',
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
