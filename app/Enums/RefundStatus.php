<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Processing = 'PROCESSING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Đã yêu cầu',
            self::Approved => 'Đã duyệt',
            self::Processing => 'Đang xử lý',
            self::Succeeded => 'Thành công',
            self::Failed => 'Thất bại',
            self::Cancelled => 'Đã hủy',
        };
    }
}
