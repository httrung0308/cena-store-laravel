<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProductImageThumbnailService
{
    public const SIZES = [400, 64];

    public const QUALITY = 80;

    public const MAX_ORIGINAL_DIMENSION = 2000;

    /**
     * Generate 400px and 64px square WebP thumbnails for a stored product image.
     *
     * @return array<int, string> Map of size => relative storage path
     */
    public function generateThumbnails(string $storagePath, bool $force = false): array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($storagePath)) {
            return [];
        }

        $fullPath = $disk->path($storagePath);
        $filename = pathinfo($storagePath, PATHINFO_FILENAME);

        $results = [];
        $needsGeneration = false;
        foreach (self::SIZES as $size) {
            $thumbRelative = "products/thumbs/{$size}/{$filename}.webp";
            $results[$size] = $thumbRelative;
            if ($force || ! $disk->exists($thumbRelative)) {
                $needsGeneration = true;
            }
        }

        if (! $needsGeneration) {
            return $results;
        }

        $this->ensureMemoryLimit('512M');

        try {
            $src = $this->loadImage($fullPath);
            if ($src === null) {
                return [];
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $crop = min($w, $h);
            $srcX = (int) (($w - $crop) / 2);
            $srcY = (int) (($h - $crop) / 2);

            foreach (self::SIZES as $size) {
                $thumbRelative = $results[$size];
                if (! $force && $disk->exists($thumbRelative)) {
                    continue;
                }

                $thumbDir = dirname($disk->path($thumbRelative));
                if (! is_dir($thumbDir)) {
                    mkdir($thumbDir, 0755, true);
                }

                $dst = imagecreatetruecolor($size, $size);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $crop, $crop);

                imagewebp($dst, $disk->path($thumbRelative), self::QUALITY);
                imagedestroy($dst);
            }

            imagedestroy($src);

            return $results;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Downscale a newly uploaded original image to max 2000px long edge if it exceeds it.
     */
    public function downscaleOriginal(string $storagePath, int $maxDimension = self::MAX_ORIGINAL_DIMENSION): bool
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($storagePath)) {
            return false;
        }

        $fullPath = $disk->path($storagePath);
        $info = @getimagesize($fullPath);
        if ($info === false) {
            return false;
        }

        [$w, $h] = [$info[0], $info[1]];
        if (max($w, $h) <= $maxDimension) {
            return false;
        }

        $this->ensureMemoryLimit('512M');

        try {
            $src = $this->loadImage($fullPath);
            if ($src === null) {
                return false;
            }

            $scale = $maxDimension / max($w, $h);
            $newW = (int) round($w * $scale);
            $newH = (int) round($h * $scale);

            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

            $mime = $info['mime'] ?? '';
            match ($mime) {
                'image/png' => imagepng($dst, $fullPath, 8),
                'image/webp' => imagewebp($dst, $fullPath, 85),
                default => imagejpeg($dst, $fullPath, 85),
            };

            imagedestroy($dst);
            imagedestroy($src);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Delete thumbnails for a stored image if the image is removed.
     */
    public function deleteThumbnails(string $storagePath): void
    {
        $disk = Storage::disk('public');
        $filename = pathinfo($storagePath, PATHINFO_FILENAME);
        foreach (self::SIZES as $size) {
            $thumbRelative = "products/thumbs/{$size}/{$filename}.webp";
            if ($disk->exists($thumbRelative)) {
                $disk->delete($thumbRelative);
            }
        }
    }

    /**
     * Returns the public thumbnail URL for a given storage path or external URL.
     * Falls back to the original image URL or the default fallback asset.
     */
    public function getThumbnailUrl(?string $storagePath, ?string $externalUrl = null, int $size = 400): string
    {
        if (is_string($storagePath) && $storagePath !== '') {
            $filename = pathinfo($storagePath, PATHINFO_FILENAME);
            $thumbRelative = "products/thumbs/{$size}/{$filename}.webp";
            $disk = Storage::disk('public');

            if ($disk->exists($thumbRelative)) {
                return $disk->url($thumbRelative);
            }

            return $disk->url($storagePath);
        }

        if (is_string($externalUrl) && $externalUrl !== '') {
            return $externalUrl;
        }

        return asset(CatalogQueryService::FALLBACK_IMAGE);
    }

    /**
     * Load an image file into a GD image resource with EXIF orientation correction.
     *
     * @return \GdImage|null
     */
    private function loadImage(string $path): ?\GdImage
    {
        if (! file_exists($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        $mime = $info['mime'] ?? '';
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };

        if (! $image instanceof \GdImage) {
            return null;
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => null,
            };
            if ($rotated instanceof \GdImage) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        return $image;
    }

    private function ensureMemoryLimit(string $target): void
    {
        $current = ini_get('memory_limit');
        if ($current !== '-1') {
            @ini_set('memory_limit', $target);
        }
    }
}
