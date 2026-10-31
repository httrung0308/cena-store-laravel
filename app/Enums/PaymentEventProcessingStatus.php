<?php

namespace App\Enums;

enum PaymentEventProcessingStatus: string
{
    case Received = 'RECEIVED';
    case Applied = 'APPLIED';
    case Duplicate = 'DUPLICATE';
    case Review = 'REVIEW';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Đã tiếp nhận',
            self::Applied => 'Đã áp dụng',
            self::Duplicate => 'Trùng lặp',
            self::Review => 'Cần đối soát',
            self::Rejected => 'Đã từ chối',
        };
    }
}
