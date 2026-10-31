<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Ready = 'READY';
    case InTransit = 'IN_TRANSIT';
    case Delivered = 'DELIVERED';
    case Returning = 'RETURNING';
    case ReturnReceived = 'RETURN_RECEIVED';
    case Lost = 'LOST';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Sẵn sàng',
            self::InTransit => 'Đang vận chuyển',
            self::Delivered => 'Đã giao hàng',
            self::Returning => 'Đang hoàn hàng',
            self::ReturnReceived => 'Đã nhận hàng hoàn',
            self::Lost => 'Thất lạc',
        };
    }
}
