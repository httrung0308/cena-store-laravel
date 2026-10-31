<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class StockImportItemResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_import_id' => $this->stock_import_id,
            'variant_id' => $this->variant_id,
            'sku_snapshot' => $this->sku_snapshot,
            'quantity' => $this->quantity,
            'unit_cost_amount' => $this->unit_cost_amount,
            'subtotal_amount' => $this->subtotal_amount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
