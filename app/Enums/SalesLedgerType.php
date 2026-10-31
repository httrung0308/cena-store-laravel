<?php

namespace App\Enums;

enum SalesLedgerType: string
{
    case SaleRecognized = 'SALE_RECOGNIZED';
    case ShippingRecognized = 'SHIPPING_RECOGNIZED';
    case SaleReversed = 'SALE_REVERSED';
    case ShippingReversed = 'SHIPPING_REVERSED';
    case ExchangeCogsAdjustment = 'EXCHANGE_COGS_ADJUSTMENT';
    case RmaExpense = 'RMA_EXPENSE';
    case InventoryLoss = 'INVENTORY_LOSS';

    public function label(): string
    {
        return match ($this) {
            self::SaleRecognized => 'Ghi nhận bán hàng',
            self::ShippingRecognized => 'Ghi nhận phí vận chuyển',
            self::SaleReversed => 'Đảo bán hàng',
            self::ShippingReversed => 'Đảo phí vận chuyển',
            self::ExchangeCogsAdjustment => 'Điều chỉnh giá vốn đổi size',
            self::RmaExpense => 'Chi phí hậu mãi',
            self::InventoryLoss => 'Tổn thất hàng tồn',
        };
    }
}
