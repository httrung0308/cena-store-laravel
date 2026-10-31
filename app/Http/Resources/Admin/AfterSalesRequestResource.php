<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class AfterSalesRequestResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'order_item_unit_id' => $this->order_item_unit_id,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'source_variant_id' => $this->source_variant_id,
            'target_variant_id' => $this->target_variant_id,
            'target_reservation_active' => $this->target_reservation_active,
            'source_in_quarantine' => $this->source_in_quarantine,
            'return_disposition' => $this->return_disposition?->value,
            'disposition_by' => $this->disposition_by,
            'disposition_at' => $this->disposition_at?->toISOString(),
            'customer_reason' => $this->customer_reason,
            'staff_note' => $this->staff_note,
            'handled_by' => $this->handled_by,
            'receive_by_at' => $this->receive_by_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'ready_at' => $this->ready_at?->toISOString(),
            'customer_handover_at' => $this->customer_handover_at?->toISOString(),
            'returned_cogs_amount' => $this->returned_cogs_amount,
            'replacement_cogs_amount' => $this->replacement_cogs_amount,
            'repair_expense_amount' => $this->repair_expense_amount,
            'repair_expense_approved_by' => $this->repair_expense_approved_by,
            'repair_expense_approved_at' => $this->repair_expense_approved_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'command_key' => $this->command_key,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
