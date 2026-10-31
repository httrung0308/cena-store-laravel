<?php

namespace App\Models;

use App\Enums\PaymentEventProcessingStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden([])]
class PaymentEvent extends DomainModel
{
    protected $table = 'payment_events';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'payment_id' => 'integer',
            'provider' => PaymentMethod::class,
            'signature_valid' => 'boolean',
            'processing_status' => PaymentEventProcessingStatus::class,
            'payload_redacted' => 'array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
