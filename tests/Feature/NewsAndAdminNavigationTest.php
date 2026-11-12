<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NewsAndAdminNavigationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_news_search_filters_only_published_titles_and_preserves_pagination(): void
    {
        Article::factory()->published()->create([
            'title' => 'Giày chạy bộ Pegasus 41',
            'summary' => 'Tóm tắt thường',
            'body' => 'Nội dung chi tiết về đệm khí',
            'published_at' => now()->subMinutes(10),
        ]);
        Article::factory()->published()->create([
            'title' => 'Mẹo bảo quản sneaker trắng',
            'summary' => 'Nhắc đến Pegasus trong tóm tắt',
            'body' => 'Trong nội dung có từ Pegasus nhưng tiêu đề không có',
            'published_at' => now()->subMinutes(5),
        ]);
        Article::factory()->create([
            'title' => 'Pegasus bản nháp',
        ]);
        Article::factory()->hidden()->create([
            'title' => 'Pegasus đã ẩn',
        ]);

        $response = $this->get(route('news.index', ['search' => 'Pegasus']));
        $response->assertOk()
            ->assertSee('Giày chạy bộ Pegasus 41')
            ->assertDontSee('Mẹo bảo quản sneaker trắng')
            ->assertDontSee('Pegasus bản nháp')
            ->assertDontSee('Pegasus đã ẩn')
            ->assertSee('Tìm thấy');

        // Empty search result state
        $this->get(route('news.index', ['search' => 'KhongTonTai123']))
            ->assertOk()
            ->assertSee('Không tìm thấy bài viết phù hợp')
            ->assertSee('Xem tất cả bài viết');

        // LIKE wildcard characters are treated literally
        $this->get(route('news.index', ['search' => '100%']))
            ->assertOk()
            ->assertSee('Không tìm thấy bài viết phù hợp');
    }

    public function test_news_search_pagination_keeps_query_string(): void
    {
        foreach (range(1, 11) as $i) {
            Article::factory()->published()->create([
                'title' => 'Hướng dẫn chọn giày số '.$i,
                'published_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $page1 = $this->get(route('news.index', ['search' => 'Hướng dẫn chọn giày']));
        $page1->assertOk()->assertDontSee('data-news-featured', false);

        $page2 = $this->get(route('news.index', ['search' => 'Hướng dẫn chọn giày', 'page' => 2]));
        $page2->assertOk()->assertSee('Hướng dẫn chọn giày số 1');
    }

    public function test_news_feedback_supports_json_ajax_and_preserves_html_fallback(): void
    {
        $article = Article::factory()->published()->create(['title' => 'Bài viết AJAX']);
        $reader = User::factory()->customer()->create();
        $this->actingAs($reader);

        $this->postJson(route('news.feedback', $article->slug), ['helpful' => 'yes'])
            ->assertOk()
            ->assertJson([
                'message' => 'Cảm ơn bạn đã góp ý!',
                'my_feedback' => true,
                'helpful_count' => 1,
            ]);

        $this->postJson(route('news.feedback', $article->slug), ['helpful' => 'no'])
            ->assertOk()
            ->assertJson([
                'my_feedback' => false,
                'helpful_count' => 0,
            ]);

        $this->postJson(route('news.feedback', $article->slug), ['helpful' => 'invalid'])
            ->assertUnprocessable();

        $draft = Article::factory()->create(['title' => 'Bản nháp']);
        $this->postJson(route('news.feedback', $draft->slug), ['helpful' => 'yes'])
            ->assertNotFound();

        // Standard form POST still redirects
        $this->post(route('news.feedback', $article->slug), ['helpful' => 'yes'])
            ->assertRedirect(route('news.show', $article->slug).'#article-feedback');
    }

    public function test_admin_and_staff_see_role_appropriate_sidebar_links_and_active_states(): void
    {
        $admin = User::factory()->administrator()->create(['full_name' => 'Admin Test']);
        $staff = User::factory()->staff()->create(['full_name' => 'Staff Test']);
        $order = Order::factory()->create();

        // Admin sees all modules including Mã giảm giá and correct role label
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Test (Quản trị viên)')
            ->assertSee('href="'.route('admin.coupons.index').'"', false)
            ->assertSee('href="'.route('admin.banners.index').'"', false)
            ->assertSee('href="'.route('admin.classifications.index').'"', false)
            ->assertSee('href="'.route('admin.articles.index').'"', false)
            ->assertSee('href="'.route('admin.users.index').'"', false);

        // Active state highlights on create/show pages
        $this->get(route('admin.coupons.create'))
            ->assertOk()
            ->assertSee('href="'.route('admin.coupons.index').'"', false)
            ->assertSee('aria-current="page"', false);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('aria-current="page"', false);

        // Staff does NOT see Admin-only links or group
        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Staff Test (Nhân viên)')
            ->assertSee('href="'.route('admin.orders.index').'"', false)
            ->assertSee('href="'.route('admin.products.index').'"', false)
            ->assertDontSee('href="'.route('admin.coupons.index').'"', false)
            ->assertDontSee('href="'.route('admin.banners.index').'"', false)
            ->assertDontSee('href="'.route('admin.classifications.index').'"', false)
            ->assertDontSee('href="'.route('admin.articles.index').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('Nội dung &amp; Hệ thống', false);
    }
}
