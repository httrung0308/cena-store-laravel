<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class PaymentEventResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'provider' => $this->provider?->value,
            'event_key' => $this->event_key,
            'provider_event_ref' => $this->provider_event_ref,
            'signature_valid' => $this->signature_valid,
            'processing_status' => $this->processing_status?->value,
            'response_code' => $this->response_code,
            'received_at' => $this->received_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
        ];
    }
}
