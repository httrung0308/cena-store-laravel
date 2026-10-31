<?php

namespace App\Enums;

enum OrderUnitState: string
{
    case Allocated = 'ALLOCATED';
    case Outbound = 'OUTBOUND';
    case OwnedByCustomer = 'OWNED_BY_CUSTOMER';
    case ReturnedToStore = 'RETURNED_TO_STORE';
    case Lost = 'LOST';
    case ReturnedRefunded = 'RETURNED_REFUNDED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Allocated => 'Đã phân bổ',
            self::Outbound => 'Đang xuất giao',
            self::OwnedByCustomer => 'Khách đang sở hữu',
            self::ReturnedToStore => 'Đã trả về cửa hàng',
            self::Lost => 'Thất lạc',
            self::ReturnedRefunded => 'Đã trả và hoàn tiền',
            self::Cancelled => 'Đã hủy',
        };
    }
}
