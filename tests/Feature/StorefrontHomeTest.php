<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Classification;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontHomeTest extends TestCase
{
    public function test_home_renders_successfully_with_fallback_when_no_banners(): void
    {
        Banner::query()->delete();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('storefront.home')
            ->assertSee('Thể thao là')
            ->assertSee('không ngừng bỏ cuộc.')
            ->assertSee('Khám phá ngay');
    }

    public function test_home_renders_single_banner_statically_when_one_active_banner_exists(): void
    {
        Banner::query()->delete();

        $banner = Banner::factory()->create([
            'title' => 'Single Hero Promotion '.Str::random(5),
            'subtitle' => 'Exclusive running shoes collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($banner->title)
            ->assertSee('Exclusive running shoes collection')
            ->assertDontSee('Banner trước');
    }

    public function test_home_renders_carousel_when_multiple_active_banners_exist(): void
    {
        Banner::query()->delete();

        $banner1 = Banner::factory()->create([
            'title' => 'Banner First '.Str::random(5),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $banner2 = Banner::factory()->create([
            'title' => 'Banner Second '.Str::random(5),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $inactiveBanner = Banner::factory()->inactive()->create([
            'title' => 'Hidden Inactive Banner '.Str::random(5),
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($banner1->title)
            ->assertSee($banner2->title)
            ->assertDontSee($inactiveBanner->title)
            ->assertSee('Banner trước')
            ->assertSee('Banner kế tiếp');
    }

    public function test_home_renders_gender_categories_and_active_classifications(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Khám phá theo đối tượng')
            ->assertSee('Giày Nam')
            ->assertSee('Giày Nữ')
            ->assertSee('Trẻ Em')
            ->assertSee('Unisex');

        $activeClass = Classification::active()->first();
        if ($activeClass) {
            $response->assertSee($activeClass->name);
        }
    }

    public function test_home_renders_three_separate_product_sections(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Sản phẩm nổi bật')
            ->assertSee('Mới đăng bán');
    }

    public function test_home_article_sidebar_displays_only_published_articles(): void
    {
        $author = User::factory()->create();

        $published = Article::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'Published Running Tips '.Str::random(6),
        ]);

        $draft = Article::factory()->create([
            'author_id' => $author->id,
            'title' => 'Unpublished Secret Article '.Str::random(6),
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title);
    }
}
