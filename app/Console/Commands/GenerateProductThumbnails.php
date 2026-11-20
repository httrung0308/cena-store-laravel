<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ProductImageThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateProductThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:thumbnails {--force : Bắt buộc tạo lại thumbnail dù đã tồn tại}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quét và tạo ảnh thu nhỏ (400px và 64px WebP) cho mọi ảnh sản phẩm đang lưu trữ';

    public function handle(ProductImageThumbnailService $thumbnails): int
    {
        $force = (bool) $this->option('force');
        $disk = Storage::disk('public');

        // 1. Gather all storage paths from database
        $dbPaths = ProductImage::query()
            ->whereNotNull('storage_path')
            ->where('source_type', 'UPLOAD')
            ->pluck('storage_path')
            ->filter()
            ->unique()
            ->all();

        // 2. Also discover any existing physical product images on disk
        $diskFiles = $disk->files('products');
        $allPaths = array_unique(array_merge($dbPaths, $diskFiles));

        // Filter only valid image extensions and exclude thumbs folder
        $allPaths = array_values(array_filter($allPaths, function (string $path): bool {
            if (str_starts_with($path, 'products/thumbs/')) {
                return false;
            }

            return (bool) preg_match('/\.(?:jpe?g|png|webp)$/i', $path);
        }));

        if ($allPaths === []) {
            $this->info('Không tìm thấy ảnh sản phẩm nào để xử lý.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Tìm thấy %d ảnh sản phẩm. Bắt đầu xử lý...', count($allPaths)));

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar(count($allPaths));
        $bar->start();

        foreach ($allPaths as $path) {
            if (! $disk->exists($path)) {
                $failed++;
                $bar->advance();

                continue;
            }

            $filename = pathinfo($path, PATHINFO_FILENAME);
            $thumb400 = "products/thumbs/400/{$filename}.webp";
            $thumb64 = "products/thumbs/64/{$filename}.webp";

            $needsWork = $force || ! $disk->exists($thumb400) || ! $disk->exists($thumb64);
            if (! $needsWork) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $res = $thumbnails->generateThumbnails($path, $force);
            if ($res !== []) {
                $generated++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Trạng thái', 'Số lượng'],
            [
                ['Đã tạo mới/cập nhật', $generated],
                ['Đã tồn tại (bỏ qua)', $skipped],
                ['Lỗi/Không tồn tại', $failed],
                ['Tổng cộng', count($allPaths)],
            ]
        );

        return self::SUCCESS;
    }
}
