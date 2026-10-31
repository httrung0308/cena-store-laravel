<?php

namespace App\Enums;

enum OutboxAggregateType: string
{
    case Order = 'ORDER';
    case Rma = 'RMA';
    case Refund = 'REFUND';
    case User = 'USER';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Đơn hàng',
            self::Rma => 'Hậu mãi',
            self::Refund => 'Hoàn tiền',
            self::User => 'Người dùng',
        };
    }
}
