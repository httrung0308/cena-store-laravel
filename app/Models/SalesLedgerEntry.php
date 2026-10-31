<?php

namespace App\Models;

use App\Enums\SalesLedgerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['cogs_delta'])]
class SalesLedgerEntry extends DomainModel
{
    protected $table = 'sales_ledger_entries';

    protected $attributes = [
        'merchandise_revenue_delta' => '0',
        'shipping_revenue_delta' => '0',
        'cogs_delta' => '0',
        'expense_delta' => '0',
    ];

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'order_item_unit_id' => 'integer',
            'after_sales_request_id' => 'integer',
            'refund_id' => 'integer',
            'inventory_movement_id' => 'integer',
            'type' => SalesLedgerType::class,
            'merchandise_revenue_delta' => 'integer',
            'shipping_revenue_delta' => 'integer',
            'cogs_delta' => 'integer',
            'expense_delta' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrderItemUnit::class, 'order_item_unit_id');
    }

    public function afterSalesRequest(): BelongsTo
    {
        return $this->belongsTo(AfterSalesRequest::class, 'after_sales_request_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }
}
