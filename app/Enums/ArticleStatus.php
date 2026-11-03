<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Hidden = 'HIDDEN';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Published => 'Đã đăng',
            self::Hidden => 'Đã ẩn',
        };
    }
}
