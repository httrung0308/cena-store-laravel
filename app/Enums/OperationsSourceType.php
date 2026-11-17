<?php

namespace App\Enums;

enum OperationsSourceType: string
{
    case Order = 'ORDER';
    case Shipment = 'SHIPMENT';
    case Inventory = 'INVENTORY';
    case AfterSales = 'AFTER_SALES';
    case Payment = 'PAYMENT';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Đơn hàng',
            self::Shipment => 'Vận chuyển',
            self::Inventory => 'Kho hàng',
            self::AfterSales => 'Hậu mãi',
            self::Payment => 'Thanh toán',
            self::Other => 'Nghiệp vụ khác',
        };
    }
}
