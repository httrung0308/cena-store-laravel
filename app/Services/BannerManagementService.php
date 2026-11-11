<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BannerManagementService
{
    private const FILE_PATTERN = '~^banners/[A-Za-z0-9_\-\.]{1,80}$~D';

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(
        User $actor,
        ?Banner $banner,
        array $input,
        ?UploadedFile $imageFile = null,
        ?UploadedFile $mobileImageFile = null,
    ): Banner {
        $stagedFiles = [];

        try {
            $imagePath = $banner?->image_path;
            if ($imageFile instanceof UploadedFile) {
                $imagePath = $imageFile->storePublicly('banners', 'public');
                $stagedFiles[] = $imagePath;
            }

            $mobileImagePath = $banner?->mobile_image_path;
            if ($mobileImageFile instanceof UploadedFile) {
                $mobileImagePath = $mobileImageFile->storePublicly('banners', 'public');
                $stagedFiles[] = $mobileImagePath;
            }

            return DB::transaction(function () use ($banner, $input, $imagePath, $mobileImagePath): Banner {
                $oldDesktopPath = $banner?->image_path;
                $oldMobilePath = $banner?->mobile_image_path;

                $current = $banner
                    ? Banner::whereKey($banner->id)->lockForUpdate()->firstOrFail()
                    : new Banner;

                $data = [
                    'title' => (string) $input['title'],
                    'subtitle' => ! empty($input['subtitle']) ? (string) $input['subtitle'] : null,
                    'image_path' => (string) $imagePath,
                    'mobile_image_path' => ! empty($mobileImagePath) ? (string) $mobileImagePath : null,
                    'link_url' => ! empty($input['link_url']) ? (string) $input['link_url'] : null,
                    'cta_text' => ! empty($input['cta_text']) ? (string) $input['cta_text'] : null,
                    'sort_order' => (int) $input['sort_order'],
                    'is_active' => (bool) $input['is_active'],
                ];

                $current->forceFill($data)->save();

                // Dọn dẹp ảnh cũ nếu đã thay thế ảnh mới thành công
                if ($oldDesktopPath && $oldDesktopPath !== $imagePath) {
                    $this->discardIfNotShared($oldDesktopPath, $current->id);
                }
                if ($oldMobilePath && $oldMobilePath !== $mobileImagePath) {
                    $this->discardIfNotShared($oldMobilePath, $current->id);
                }

                return $current->refresh();
            });
        } catch (Throwable $e) {
            // Dọn dẹp file mới tải lên nếu thao tác database thất bại
            foreach ($stagedFiles as $file) {
                $this->discardFile($file);
            }
            throw $e;
        }
    }

    public function toggleActive(User $actor, Banner $banner): Banner
    {
        return DB::transaction(function () use ($banner): Banner {
            $current = Banner::whereKey($banner->id)->lockForUpdate()->firstOrFail();
            $current->forceFill(['is_active' => ! $current->is_active])->save();

            return $current->refresh();
        });
    }

    public function delete(User $actor, Banner $banner): void
    {
        $desktopPath = $banner->image_path;
        $mobilePath = $banner->mobile_image_path;
        $bannerId = $banner->id;

        DB::transaction(function () use ($banner): void {
            $current = Banner::whereKey($banner->id)->lockForUpdate()->firstOrFail();
            $current->delete();
        });

        // Chỉ xóa file nếu không còn banner nào khác đang sử dụng
        $this->discardIfNotShared($desktopPath, $bannerId);
        if ($mobilePath) {
            $this->discardIfNotShared($mobilePath, $bannerId);
        }
    }

    private function discardIfNotShared(?string $path, ?int $excludingBannerId = null): void
    {
        if (! $path) {
            return;
        }

        $isShared = Banner::query()
            ->when($excludingBannerId, fn ($q) => $q->where('id', '!=', $excludingBannerId))
            ->where(fn ($q) => $q->where('image_path', $path)->orWhere('mobile_image_path', $path))
            ->exists();

        if (! $isShared) {
            $this->discardFile($path);
        }
    }

    private function discardFile(?string $path): void
    {
        if ($path && preg_match(self::FILE_PATTERN, $path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
