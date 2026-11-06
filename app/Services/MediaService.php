<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MediaService
{
    public function __construct(
        private readonly ProductImageThumbnailService $thumbnails = new ProductImageThumbnailService,
    ) {}

    public function stage(UploadedFile $file): string
    {
        $path = $file->storePublicly('products', 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Cannot store product image.');
        }

        $this->thumbnails->downscaleOriginal($path, ProductImageThumbnailService::MAX_ORIGINAL_DIMENSION);
        $this->thumbnails->generateThumbnails($path);

        return $path;
    }

    /** @param list<string> $paths Only server-created paths from this attempted operation. */
    public function discard(array $paths): void
    {
        foreach ($paths as $path) {
            if (preg_match('~^products/[A-Za-z0-9]{40}\.(?:jpe?g|png|webp)$~D', $path)) {
                Storage::disk('public')->delete($path);
                $this->thumbnails->deleteThumbnails($path);
            }
        }
    }
}
