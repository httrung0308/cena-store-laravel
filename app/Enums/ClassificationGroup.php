<?php

namespace App\Enums;

enum ClassificationGroup: string
{
    case Silhouette = 'SILHOUETTE';
    case Activity = 'ACTIVITY';

    public function label(): string
    {
        return match ($this) {
            self::Silhouette => 'Kiểu dáng',
            self::Activity => 'Mục đích sử dụng',
        };
    }
}
