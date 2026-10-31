<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rating', 'comment'])]
#[Hidden([])]
class Review extends DomainModel
{
    protected $table = 'reviews';

    protected $attributes = [
        'is_visible' => '1',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_item_unit_id' => 'integer',
            'user_id' => 'integer',
            'rating' => 'decimal:1',
            'is_visible' => 'boolean',
            'replied_by' => 'integer',
            'replied_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrderItemUnit::class, 'order_item_unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by')->withTrashed();
    }
}
