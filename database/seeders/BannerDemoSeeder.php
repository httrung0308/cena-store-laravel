<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BannerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $bannersDir = storage_path('app/public/banners');
        if (! File::isDirectory($bannersDir)) {
            File::makeDirectory($bannersDir, 0755, true);
        }

        $source1 = public_path('legacy/img/men.jpg');
        $dest1 = $bannersDir.'/demo-hero-1.jpg';
        if (File::exists($source1) && ! File::exists($dest1)) {
            File::copy($source1, $dest1);
        }

        $source2 = public_path('legacy/img/women.jpg');
        $dest2 = $bannersDir.'/demo-hero-2.jpg';
        if (File::exists($source2) && ! File::exists($dest2)) {
            File::copy($source2, $dest2);
        }

        $source3 = public_path('legacy/img/unisex.jpg');
        $dest3 = $bannersDir.'/demo-hero-3.jpg';
        if (File::exists($source3) && ! File::exists($dest3)) {
            File::copy($source3, $dest3);
        }

        $demos = [
            [
                'title' => 'Bứt Phá Mọi Giới Hạn — Bộ Sưu Tập Running 2026',
                'subtitle' => 'Công nghệ đệm khí trợ lực tối đa, sẵn sàng cùng bạn chinh phục mọi cự ly.',
                'image_path' => 'banners/demo-hero-1.jpg',
                'mobile_image_path' => null,
                'link_url' => '/shoes?activity%5B%5D=chay-bo',
                'cta_text' => 'Khám phá ngay',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Cena Streetwear — Tự Do Thể Hiện Cá Tính',
                'subtitle' => 'Phong cách đường phố phóng khoáng, độ bền vượt trội và phối màu kinh điển.',
                'image_path' => 'banners/demo-hero-2.jpg',
                'mobile_image_path' => null,
                'link_url' => '/shoes?silhouette%5B%5D=sneaker',
                'cta_text' => 'Xem bộ sưu tập',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Đổi Size An Tâm 7 Ngày — Bảo Hành 6 Tháng',
                'subtitle' => 'Trải nghiệm mua sắm không rủi ro, kiểm tra hàng trước khi thanh toán.',
                'image_path' => 'banners/demo-hero-3.jpg',
                'mobile_image_path' => null,
                'link_url' => '/shoes',
                'cta_text' => 'Mua sắm ngay',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($demos as $demo) {
            Banner::updateOrCreate(
                ['title' => $demo['title']],
                $demo
            );
        }
    }
}
