<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class PaymentResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'origin_payment_id' => $this->origin_payment_id,
            'attempt_ref' => $this->attempt_ref,
            'method' => $this->method?->value,
            'status' => $this->status?->value,
            'disposition' => $this->disposition?->value,
            'expected_amount' => $this->expected_amount,
            'received_amount' => $this->received_amount,
            'applied_amount' => $this->applied_amount,
            'currency' => $this->currency?->value,
            'provider_transaction_id' => $this->provider_transaction_id,
            'bank_transfer_content' => $this->bank_transfer_content,
            'customer_reported_reference' => $this->customer_reported_reference,
            'customer_reported_at' => $this->customer_reported_at?->toISOString(),
            'confirmed_by' => $this->confirmed_by,
            'expires_at' => $this->expires_at?->toISOString(),
            'succeeded_at' => $this->succeeded_at?->toISOString(),
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
