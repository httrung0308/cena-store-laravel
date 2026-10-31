<?php

namespace App\Enums;

enum ReturnDisposition: string
{
    case Pending = 'PENDING';
    case Restock = 'RESTOCK';
    case WriteOff = 'WRITE_OFF';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Restock => 'Nhập lại kho bán',
            self::WriteOff => 'Ghi giảm',
        };
    }
}
