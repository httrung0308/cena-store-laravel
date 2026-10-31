<?php

namespace App\Models;

use App\Enums\AfterSalesEventType;
use App\Enums\AfterSalesStatus;
use App\Enums\HistoryVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['note'])]
class AfterSalesHistory extends DomainModel
{
    protected $table = 'after_sales_histories';

    protected $attributes = [
        'visibility' => 'CUSTOMER',
    ];

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'after_sales_request_id' => 'integer',
            'actor_id' => 'integer',
            'from_status' => AfterSalesStatus::class,
            'to_status' => AfterSalesStatus::class,
            'event_type' => AfterSalesEventType::class,
            'visibility' => HistoryVisibility::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function afterSalesRequest(): BelongsTo
    {
        return $this->belongsTo(AfterSalesRequest::class, 'after_sales_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
