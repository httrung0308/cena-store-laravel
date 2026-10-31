<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'PENDING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Succeeded => 'Thành công',
            self::Failed => 'Thất bại',
            self::Expired => 'Đã hết hạn',
        };
    }
}
