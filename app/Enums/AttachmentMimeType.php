<?php

namespace App\Enums;

enum AttachmentMimeType: string
{
    case ImageJpeg = 'image/jpeg';
    case ImagePng = 'image/png';
    case ImageWebp = 'image/webp';

    public function label(): string
    {
        return match ($this) {
            self::ImageJpeg => 'Ảnh JPEG',
            self::ImagePng => 'Ảnh PNG',
            self::ImageWebp => 'Ảnh WebP',
        };
    }
}
