<?php

namespace App\Enums;

enum InventoryAction: string
{
    case ImportPosted = 'IMPORT_POSTED';
    case OrderReserved = 'ORDER_RESERVED';
    case OrderReleased = 'ORDER_RELEASED';
    case OrderDispatched = 'ORDER_DISPATCHED';
    case ShipmentReturnReceived = 'SHIPMENT_RETURN_RECEIVED';
    case ReturnRestocked = 'RETURN_RESTOCKED';
    case ReturnWrittenOff = 'RETURN_WRITTEN_OFF';
    case ExchangeReserved = 'EXCHANGE_RESERVED';
    case ExchangeReleased = 'EXCHANGE_RELEASED';
    case ExchangeOldReceived = 'EXCHANGE_OLD_RECEIVED';
    case ExchangeCompletedIn = 'EXCHANGE_COMPLETED_IN';
    case ExchangeCompletedOut = 'EXCHANGE_COMPLETED_OUT';
    case DefectReceived = 'DEFECT_RECEIVED';
    case ManualAdjustIn = 'MANUAL_ADJUST_IN';
    case ManualAdjustOut = 'MANUAL_ADJUST_OUT';
    case QuarantineWriteoff = 'QUARANTINE_WRITEOFF';

    public function label(): string
    {
        return match ($this) {
            self::ImportPosted => 'Ghi sổ nhập kho',
            self::OrderReserved => 'Giữ hàng cho đơn',
            self::OrderReleased => 'Giải phóng hàng của đơn',
            self::OrderDispatched => 'Xuất hàng giao đơn',
            self::ShipmentReturnReceived => 'Nhận hàng giao thất bại',
            self::ReturnRestocked => 'Nhập lại hàng trả',
            self::ReturnWrittenOff => 'Ghi giảm hàng trả',
            self::ExchangeReserved => 'Giữ hàng đổi size',
            self::ExchangeReleased => 'Giải phóng hàng đổi size',
            self::ExchangeOldReceived => 'Nhận đôi cũ đổi size',
            self::ExchangeCompletedIn => 'Hoàn tất nhập đôi cũ',
            self::ExchangeCompletedOut => 'Hoàn tất xuất đôi mới',
            self::DefectReceived => 'Nhận hàng lỗi',
            self::ManualAdjustIn => 'Điều chỉnh tăng tồn',
            self::ManualAdjustOut => 'Điều chỉnh giảm tồn',
            self::QuarantineWriteoff => 'Ghi giảm hàng cách ly',
        };
    }
}
