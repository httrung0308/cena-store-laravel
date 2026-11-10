<?php

namespace App\Enums;

enum OperationsWorkItemStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::InProgress => 'Đang xử lý',
            self::Completed => 'Đã hoàn thành',
        };
    }
}
