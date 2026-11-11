<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBannerCrudTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->administrator()->create();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_only_admin_can_access_banner_management(): void
    {
        // Admin -> 200 OK
        $this->actingAs($this->admin)->get(route('admin.banners.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.banners.create'))->assertOk();

        // Staff -> 403 Forbidden
        $this->actingAs($this->staff)->get(route('admin.banners.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.banners.create'))->assertForbidden();

        // Guest -> 403 Forbidden
        $this->get(route('admin.banners.index'))->assertForbidden();
    }

    public function test_admin_can_create_banner_with_desktop_and_mobile_images(): void
    {
        $desktopFile = UploadedFile::fake()->image('desktop-banner.jpg', 1920, 640);
        $mobileFile = UploadedFile::fake()->image('mobile-banner.webp', 800, 800);

        $response = $this->actingAs($this->admin)->post(route('admin.banners.store'), [
            'title' => 'Khuyến Mãi Mùa Hè 2026',
            'subtitle' => 'Giảm giá tới 40% cho tất cả mẫu chạy bộ',
            'image' => $desktopFile,
            'mobile_image' => $mobileFile,
            'link_url' => '/shoes?gender=men',
            'cta_text' => 'Mua Ngay',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.banners.index'));
        $response->assertSessionHas('success');

        $banner = Banner::where('title', 'Khuyến Mãi Mùa Hè 2026')->first();
        $this->assertNotNull($banner);
        $this->assertSame(1, $banner->sort_order);
        $this->assertTrue($banner->is_active);
        $this->assertSame('/shoes?gender=men', $banner->link_url);
        $this->assertSame('Mua Ngay', $banner->cta_text);

        // Verify files stored on disk
        $this->assertNotNull($banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);

        $this->assertNotNull($banner->mobile_image_path);
        Storage::disk('public')->assertExists($banner->mobile_image_path);
    }

    public function test_validation_rejects_dangerous_link_url_schemes(): void
    {
        $file = UploadedFile::fake()->image('banner.jpg', 1200, 600);

        $response = $this->actingAs($this->admin)->post(route('admin.banners.store'), [
            'title' => 'Test Malicious Link',
            'image' => $file,
            'link_url' => 'javascript:alert(1)',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['link_url']);
    }

    public function test_admin_can_edit_banner_and_replacing_image_cleans_up_old_image(): void
    {
        $initialFile = UploadedFile::fake()->image('initial.jpg', 1600, 600);
        $initialPath = $initialFile->storePublicly('banners', 'public');

        $banner = Banner::forceCreate([
            'title' => 'Banner Gốc',
            'image_path' => $initialPath,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Storage::disk('public')->assertExists($initialPath);

        $replacementFile = UploadedFile::fake()->image('replacement.jpg', 1600, 600);

        $response = $this->actingAs($this->admin)->put(route('admin.banners.update', $banner), [
            'title' => 'Banner Đã Cập Nhật',
            'image' => $replacementFile,
            'sort_order' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.banners.index'));

        $banner->refresh();
        $this->assertSame('Banner Đã Cập Nhật', $banner->title);
        $this->assertSame(5, $banner->sort_order);
        $this->assertNotSame($initialPath, $banner->image_path);

        // Old file must be deleted, new file must exist
        Storage::disk('public')->assertMissing($initialPath);
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_admin_can_toggle_active_status(): void
    {
        $banner = Banner::forceCreate([
            'title' => 'Banner Test Toggle',
            'image_path' => 'banners/test.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->patch(route('admin.banners.toggle-active', $banner))->assertRedirect();

        $this->assertFalse($banner->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.banners.toggle-active', $banner))->assertRedirect();

        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_admin_can_delete_banner_and_it_cleans_up_files(): void
    {
        $file = UploadedFile::fake()->image('delete-me.jpg', 1200, 600);
        $path = $file->storePublicly('banners', 'public');

        $banner = Banner::forceCreate([
            'title' => 'Banner Sắp Bị Xóa',
            'image_path' => $path,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($this->admin)->delete(route('admin.banners.destroy', $banner));
        $response->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
