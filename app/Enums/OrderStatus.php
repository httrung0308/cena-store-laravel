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
            self::Pending => 'Đang chờ',
            self::Confirmed => 'Đã xác nhận',
            self::Packing => 'Đang đóng gói',
            self::OnDelivery => 'Đang giao hàng',
            self::Delivered => 'Đã giao hàng',
            self::Cancelled => 'Đã hủy',
            self::Failed => 'Thất bại',
        };
    }
}
