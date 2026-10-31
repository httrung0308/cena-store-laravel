<?php

namespace App\Enums;

enum AttachmentDisk: string
{
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Riêng tư',
        };
    }
}
