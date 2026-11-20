<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductImageSourceType;
use App\Models\ProductImage;
use App\Services\ProductImageThumbnailService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageThumbnailTest extends TestCase
{
    private ProductImageThumbnailService $service;

    private string $fixtureFile = 'products/phpunit-thumb-test.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductImageThumbnailService::class);
    }

    protected function tearDown(): void
    {
        $disk = Storage::disk('public');
        if ($disk->exists($this->fixtureFile)) {
            $disk->delete($this->fixtureFile);
        }
        $this->service->deleteThumbnails($this->fixtureFile);
        parent::tearDown();
    }

    public function test_service_generates_400_and_64_square_webp_thumbnails(): void
    {
        $disk = Storage::disk('public');

        // Create a fake test JPEG image with GD
        $img = imagecreatetruecolor(800, 600);
        $blue = imagecolorallocate($img, 32, 20, 100);
        imagefilledrectangle($img, 0, 0, 800, 600, $blue);

        $fullPath = $disk->path($this->fixtureFile);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagejpeg($img, $fullPath, 90);
        imagedestroy($img);

        $this->assertTrue($disk->exists($this->fixtureFile));

        // Generate thumbnails
        $results = $this->service->generateThumbnails($this->fixtureFile);

        $this->assertArrayHasKey(400, $results);
        $this->assertArrayHasKey(64, $results);

        $thumb400 = 'products/thumbs/400/phpunit-thumb-test.webp';
        $thumb64 = 'products/thumbs/64/phpunit-thumb-test.webp';

        $this->assertTrue($disk->exists($thumb400));
        $this->assertTrue($disk->exists($thumb64));

        $info400 = getimagesize($disk->path($thumb400));
        $this->assertSame(400, $info400[0]);
        $this->assertSame(400, $info400[1]);
        $this->assertSame('image/webp', $info400['mime']);

        $info64 = getimagesize($disk->path($thumb64));
        $this->assertSame(64, $info64[0]);
        $this->assertSame(64, $info64[1]);
        $this->assertSame('image/webp', $info64['mime']);
    }

    public function test_thumbnail_accessor_and_fallback_behavior(): void
    {
        $disk = Storage::disk('public');

        // When thumbnail does NOT exist, falls back to original storage URL
        $fallbackUrl = $this->service->getThumbnailUrl($this->fixtureFile, null, 400);
        $this->assertSame($disk->url($this->fixtureFile), $fallbackUrl);

        // Model accessor also falls back
        $model = new ProductImage([
            'source_type' => ProductImageSourceType::Upload,
        ]);
        $model->storage_path = $this->fixtureFile;
        $this->assertSame($disk->url($this->fixtureFile), $model->thumbnailUrl(400));

        // When external URL is used
        $externalUrl = 'https://example.test/shoe.webp';
        $this->assertSame($externalUrl, $this->service->getThumbnailUrl(null, $externalUrl, 400));

        // When no path or external url exists, falls back to default store asset
        $this->assertStringContainsString('legacy/img/men.jpg', $this->service->getThumbnailUrl(null, null, 400));
    }
}
