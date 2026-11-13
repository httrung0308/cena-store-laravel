<?php

namespace App\Enums;

enum GenderGroup: string
{
    case Men = 'MEN';
    case Women = 'WOMEN';
    case Kids = 'KIDS';
    case Unisex = 'UNISEX';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Nam',
            self::Women => 'Nữ',
            self::Kids => 'Trẻ em',
            self::Unisex => 'Unisex',
        };
    }
}
