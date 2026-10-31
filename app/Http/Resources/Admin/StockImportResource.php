<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class StockImportResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'import_code' => $this->import_code,
            'supplier_id' => $this->supplier_id,
            'supplier_code_snapshot' => $this->supplier_code_snapshot,
            'supplier_name_snapshot' => $this->supplier_name_snapshot,
            'created_by' => $this->created_by,
            'posted_by' => $this->posted_by,
            'status' => $this->status?->value,
            'total_quantity' => $this->total_quantity,
            'total_cost_amount' => $this->total_cost_amount,
            'note' => $this->note,
            'posted_at' => $this->posted_at?->toISOString(),
            'command_key' => $this->command_key,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
