<?php

namespace App\Enums;

enum AdministrativeUnitLevel: string
{
    case Province = 'PROVINCE';
    case Ward = 'WARD';

    public function label(): string
    {
        return match ($this) {
            self::Province => 'Tỉnh / thành phố',
            self::Ward => 'Phường / xã',
        };
    }
}
