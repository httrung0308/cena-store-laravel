<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class SalesLedgerEntryResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_item_unit_id' => $this->order_item_unit_id,
            'after_sales_request_id' => $this->after_sales_request_id,
            'refund_id' => $this->refund_id,
            'inventory_movement_id' => $this->inventory_movement_id,
            'type' => $this->type?->value,
            'merchandise_revenue_delta' => $this->merchandise_revenue_delta,
            'shipping_revenue_delta' => $this->shipping_revenue_delta,
            'cogs_delta' => $this->cogs_delta,
            'expense_delta' => $this->expense_delta,
            'operation_key' => $this->operation_key,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
