<?php

namespace Database\Factories;

use App\Enums\PaymentEventProcessingStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentEvent> */
class PaymentEventFactory extends Factory
{
    protected $model = PaymentEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'provider' => PaymentMethod::Cod,
            'event_key' => hash('sha256', (string) Str::uuid()),
            'signature_valid' => true,
            'processing_status' => PaymentEventProcessingStatus::Received,
            'payload_redacted' => ['source' => 'factory'],
            'received_at' => now(),
            'payment_id' => Payment::factory(),
        ];
    }
}
