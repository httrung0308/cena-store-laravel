<?php

namespace App\Enums;

enum ProductImageSourceType: string
{
    case Upload = 'UPLOAD';
    case ExternalUrl = 'EXTERNAL_URL';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Tải lên',
            self::ExternalUrl => 'Đường dẫn bên ngoài',
        };
    }
}
