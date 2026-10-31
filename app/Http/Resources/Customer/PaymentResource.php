<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;

class PaymentResource extends OwnedResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'attempt_ref' => $this->attempt_ref,
            'method' => $this->method?->value,
            'status' => $this->status?->value,
            'disposition' => $this->disposition?->value,
            'expected_amount' => $this->expected_amount,
            'received_amount' => $this->received_amount,
            'applied_amount' => $this->applied_amount,
            'currency' => $this->currency?->value,
            'customer_reported_reference' => $this->customer_reported_reference,
            'customer_reported_at' => $this->customer_reported_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'succeeded_at' => $this->succeeded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function ownerId(): ?int
    {
        $this->resource->loadMissing('order');

        return $this->resource->order?->user_id;
    }
}
