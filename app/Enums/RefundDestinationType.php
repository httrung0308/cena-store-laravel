<?php

namespace App\Enums;

enum RefundDestinationType: string
{
    case BankTransfer = 'BANK_TRANSFER';
    case Cash = 'CASH';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Chuyển khoản ngân hàng',
            self::Cash => 'Tiền mặt',
        };
    }
}
