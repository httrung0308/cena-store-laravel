<?php

namespace App\Enums;

enum HistoryVisibility: string
{
    case Customer = 'CUSTOMER';
    case Internal = 'INTERNAL';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Khách hàng',
            self::Internal => 'Nội bộ',
        };
    }
}
