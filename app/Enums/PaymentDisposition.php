<?php

namespace App\Enums;

enum PaymentDisposition: string
{
    case None = 'NONE';
    case Applied = 'APPLIED';
    case UnappliedLate = 'UNAPPLIED_LATE';
    case UnappliedDuplicate = 'UNAPPLIED_DUPLICATE';
    case UnappliedMismatch = 'UNAPPLIED_MISMATCH';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Chưa xử lý',
            self::Applied => 'Đã áp dụng',
            self::UnappliedLate => 'Chưa áp dụng: thanh toán muộn',
            self::UnappliedDuplicate => 'Chưa áp dụng: thanh toán trùng',
            self::UnappliedMismatch => 'Chưa áp dụng: sai nội dung',
        };
    }
}
