<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class RefundResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'after_sales_request_id' => $this->after_sales_request_id,
            'order_item_unit_id' => $this->order_item_unit_id,
            'reason_type' => $this->reason_type?->value,
            'status' => $this->status?->value,
            'merchandise_amount' => $this->merchandise_amount,
            'shipping_amount' => $this->shipping_amount,
            'amount' => $this->amount,
            'operation_key' => $this->operation_key,
            'destination_type' => $this->destination_type?->value,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'paid_by' => $this->paid_by,
            'external_reference' => $this->external_reference,
            'failure_reason' => $this->failure_reason,
            'succeeded_at' => $this->succeeded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
