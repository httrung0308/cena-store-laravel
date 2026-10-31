<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Unpaid = 'UNPAID';
    case Paid = 'PAID';
    case RefundPending = 'REFUND_PENDING';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
    case Refunded = 'REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::Paid => 'Đã thanh toán',
            self::RefundPending => 'Chờ hoàn tiền',
            self::PartiallyRefunded => 'Đã hoàn một phần',
            self::Refunded => 'Đã hoàn tiền',
        };
    }
}
