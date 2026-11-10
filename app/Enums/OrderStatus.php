<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Packing = 'PACKING';
    case OnDelivery = 'ON_DELIVERY';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đang đóng gói',
            self::Packing => 'Đang đóng gói',
            self::OnDelivery => 'Đang vận chuyển',
            self::Delivered => 'Giao thành công',
            self::Cancelled => 'Đã hủy',
            self::Failed => 'Thất bại',
        };
    }
}
