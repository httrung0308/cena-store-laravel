<?php

namespace App\Enums;

enum RefundReasonType: string
{
    case OrderCancelled = 'ORDER_CANCELLED';
    case ShipmentFailed = 'SHIPMENT_FAILED';
    case DefectReturn = 'DEFECT_RETURN';
    case LatePayment = 'LATE_PAYMENT';
    case DuplicatePayment = 'DUPLICATE_PAYMENT';
    case MismatchPayment = 'MISMATCH_PAYMENT';
    case ShippingReturn = 'SHIPPING_RETURN';

    public function label(): string
    {
        return match ($this) {
            self::OrderCancelled => 'Đơn bị hủy',
            self::ShipmentFailed => 'Giao hàng thất bại',
            self::DefectReturn => 'Trả hàng lỗi',
            self::LatePayment => 'Thanh toán muộn',
            self::DuplicatePayment => 'Thanh toán trùng',
            self::MismatchPayment => 'Thanh toán sai nội dung',
            self::ShippingReturn => 'Hoàn phí vận chuyển',
        };
    }
}
