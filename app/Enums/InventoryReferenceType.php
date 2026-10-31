<?php

namespace App\Enums;

enum InventoryReferenceType: string
{
    case StockImport = 'STOCK_IMPORT';
    case Order = 'ORDER';
    case ShipmentReturnItem = 'SHIPMENT_RETURN_ITEM';
    case AfterSalesRequest = 'AFTER_SALES_REQUEST';
    case ManualAdjustment = 'MANUAL_ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::StockImport => 'Phiếu nhập kho',
            self::Order => 'Đơn hàng',
            self::ShipmentReturnItem => 'Dòng hàng giao trả',
            self::AfterSalesRequest => 'Yêu cầu hậu mãi',
            self::ManualAdjustment => 'Điều chỉnh thủ công',
        };
    }
}
