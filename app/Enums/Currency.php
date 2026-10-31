<?php

namespace App\Enums;

enum Currency: string
{
    case Vnd = 'VND';

    public function label(): string
    {
        return match ($this) {
            self::Vnd => 'Việt Nam đồng',
        };
    }
}
