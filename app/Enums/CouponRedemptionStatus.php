<?php

namespace App\Enums;

enum CouponRedemptionStatus: string
{
    case Reserved = 'RESERVED';
    case Consumed = 'CONSUMED';
    case Released = 'RELEASED';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Đã giữ chỗ',
            self::Consumed => 'Đã sử dụng',
            self::Released => 'Đã giải phóng',
        };
    }
}
