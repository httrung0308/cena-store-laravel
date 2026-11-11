<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BannerDatabaseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_banners_table_has_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('banners'));
        $this->assertTrue(Schema::hasColumns('banners', [
            'id', 'title', 'subtitle', 'image_path', 'mobile_image_path',
            'link_url', 'cta_text', 'sort_order', 'is_active', 'created_at', 'updated_at',
        ]));

        $indexes = collect(DB::select('SHOW INDEX FROM banners'))->pluck('Key_name')->unique();
        $this->assertTrue($indexes->contains('PRIMARY'));
        $this->assertTrue($indexes->contains('banners_active_sort_idx'));
    }

    public function test_banner_model_casts_and_scopes_work_correctly(): void
    {
        $active1 = Banner::forceCreate([
            'title' => 'Banner 1',
            'image_path' => 'banners/1.jpg',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $active2 = Banner::forceCreate([
            'title' => 'Banner 2',
            'image_path' => 'banners/2.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $inactive = Banner::forceCreate([
            'title' => 'Banner Inactive',
            'image_path' => 'banners/inactive.jpg',
            'sort_order' => 3,
            'is_active' => false,
        ]);

        $activeBanners = Banner::query()->active()->ordered()->get();

        $this->assertCount(2, $activeBanners);
        $this->assertSame($active2->id, $activeBanners->first()->id);
        $this->assertSame($active1->id, $activeBanners->last()->id);
        $this->assertFalse($activeBanners->contains('id', $inactive->id));

        $this->assertStringContainsString('storage/banners/1.jpg', $active1->imageUrl());
        $this->assertNull($active1->mobileImageUrl());
    }

    public function test_mysql_rejects_non_boolean_is_active_due_to_check_constraint(): void
    {
        $this->expectException(QueryException::class);

        DB::statement("INSERT INTO `banners` (`title`, `image_path`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES ('Invalid', 'banners/test.jpg', 1, 2, NOW(), NOW())");
    }
}
