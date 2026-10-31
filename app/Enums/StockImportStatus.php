<?php

namespace App\Enums;

enum StockImportStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Posted => 'Đã ghi sổ',
            self::Cancelled => 'Đã hủy',
        };
    }
}
