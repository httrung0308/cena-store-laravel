<?php

namespace App\Enums;

enum OutboxStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Sent = 'SENT';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Processing => 'Đang xử lý',
            self::Sent => 'Đã gửi',
            self::Failed => 'Thất bại',
        };
    }
}
