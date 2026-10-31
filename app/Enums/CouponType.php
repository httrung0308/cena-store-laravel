<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'PERCENTAGE';
    case Fixed = 'FIXED';
    case FreeShipping = 'FREE_SHIPPING';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Giảm phần trăm',
            self::Fixed => 'Giảm số tiền',
            self::FreeShipping => 'Miễn phí vận chuyển',
        };
    }
}
