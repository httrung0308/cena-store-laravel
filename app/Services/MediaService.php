<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MediaService
{
    public function stage(UploadedFile $file): string
    {
        $path = $file->storePublicly('products', 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Cannot store product image.');
        }

        return $path;
    }

    /** @param list<string> $paths Only server-created paths from this attempted operation. */
    public function discard(array $paths): void
    {
        foreach ($paths as $path) {
            if (preg_match('~^products/[A-Za-z0-9]{40}\.(?:jpe?g|png|webp)$~D', $path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
