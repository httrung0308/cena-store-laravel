<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleFeedback;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\BusinessClock;
use Database\Seeders\ArticleFeedbackDemoSeeder;
use Database\Seeders\NewsDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->administrator()->create();
    }

    public function test_admin_publishes_an_article_with_a_server_named_cover_and_a_unique_slug(): void
    {
        $this->actingAs($this->admin);
        foreach ([1, 2] as $round) {
            $this->post(route('admin.articles.store'), $this->fields(['cover' => UploadedFile::fake()->image('my cover.png', 1200, 800)]))
                ->assertRedirect(route('admin.articles.index'));
        }

        $articles = Article::query()->orderBy('id')->get();
        $this->assertSame(['cach-chon-size-giay-chay-bo', 'cach-chon-size-giay-chay-bo-2'], $articles->pluck('slug')->all());
        $first = $articles->first();
        $this->assertSame([ArticleStatus::Published, $this->admin->id], [$first->status, $first->author_id]);
        $this->assertNotNull($first->published_at);
        $this->assertMatchesRegularExpression('~^articles/[A-Za-z0-9]{40}\.png$~', $first->cover_path);
        $this->assertStringNotContainsString('my cover', $first->cover_path);
        Storage::disk('public')->assertExists($first->cover_path);

        auth()->logout();
        $this->get(route('news.index'))->assertOk()->assertSee('Cách chọn size giày chạy bộ')->assertSee(route('news.show', $first->slug), false);
        $this->get(route('news.show', $first->slug))->assertOk()->assertSee('Đo chiều dài bàn chân')->assertSee('Mang tất khi thử giày');

        $publishedAt = $first->published_at;
        $this->actingAs($this->admin)->put(route('admin.articles.update', $first), $this->fields(['title' => 'Tiêu đề mới']))->assertRedirect();
        $first->refresh();
        $this->assertSame(['Tiêu đề mới', 'cach-chon-size-giay-chay-bo'], [$first->title, $first->slug], 'Editing keeps the link stable.');
        $this->assertEquals($publishedAt, $first->published_at);
    }

    public function test_drafts_and_hidden_articles_are_not_public_and_hiding_keeps_the_row(): void
    {
        $draft = Article::factory()->create(['title' => 'Bản nháp bí mật']);
        $published = Article::factory()->published()->create(['title' => 'Xu hướng giày 2026']);

        $this->get(route('news.index'))->assertOk()->assertSee('Xu hướng giày 2026')->assertDontSee('Bản nháp bí mật');
        $this->get(route('news.show', $draft->slug))->assertNotFound();
        $this->actingAs($this->admin)->get(route('news.show', $draft->slug))->assertNotFound();

        $this->patch(route('admin.articles.hide', $published))->assertRedirect(route('admin.articles.index'));
        $this->assertSame(ArticleStatus::Hidden, $published->fresh()->status);
        $this->assertSame(2, Article::query()->count(), 'Hiding never deletes.');
        auth()->logout();
        $this->get(route('news.show', $published->slug))->assertNotFound();
        $this->get(route('news.index'))->assertDontSee('Xu hướng giày 2026');
    }

    public function test_content_is_plain_text_and_always_escaped(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.articles.store'), $this->fields(['body' => 'Xin chào <script>alert(1)</script>', 'cover' => UploadedFile::fake()->image('c.jpg')]))
            ->assertSessionHasErrors('body');
        $this->post(route('admin.articles.store'), $this->fields(['title' => '<b>Đậm</b>', 'cover' => UploadedFile::fake()->image('c.jpg')]))
            ->assertSessionHasErrors('title');
        $this->assertSame(0, Article::query()->count());

        $legacy = Article::factory()->published()->create(['title' => 'A & B <i>', 'summary' => 'Tóm <u>tắt</u>', 'body' => "Đoạn một <script>alert(1)</script>\n\nĐoạn hai"]);
        $this->get(route('news.show', $legacy->slug))->assertOk()
            ->assertSee('A &amp; B &lt;i&gt;', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<u>tắt</u>', false);
    }

    public function test_cover_must_really_be_an_image(): void
    {
        $this->actingAs($this->admin);
        // A real upload (not a fake) so the type comes from the bytes, not from the ".jpg" name.
        $script = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($script, '<?php echo "x";');
        $this->post(route('admin.articles.store'), $this->fields(['cover' => new UploadedFile($script, 'cover.jpg', 'image/jpeg', null, true)]))
            ->assertSessionHasErrors('cover');
        @unlink($script);
        $this->post(route('admin.articles.store'), $this->fields(['cover' => UploadedFile::fake()->create('cover.gif', 10, 'image/gif')]))
            ->assertSessionHasErrors('cover');
        $this->post(route('admin.articles.store'), $this->fields())->assertSessionHasErrors('cover');
        $this->assertSame(0, Article::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_only_admin_manages_news_and_sees_its_menu(): void
    {
        $staff = User::factory()->staff()->create();
        $article = Article::factory()->create();
        $this->actingAs($staff);
        $this->get(route('admin.articles.index'))->assertForbidden();
        $this->get(route('admin.articles.create'))->assertForbidden();
        $this->get(route('admin.articles.edit', $article))->assertForbidden();
        $this->post(route('admin.articles.store'), $this->fields(['cover' => UploadedFile::fake()->image('c.jpg')]))->assertForbidden();
        $this->patch(route('admin.articles.hide', $article))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('href="'.route('admin.articles.index').'"', false);

        $this->actingAs(User::factory()->customer()->create())->get(route('admin.articles.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('href="'.route('admin.articles.index').'"', false);
        $this->get(route('admin.articles.index'))->assertOk()->assertSee($article->title);
        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
    }

    public function test_news_list_is_paginated_and_linked_from_the_navbar(): void
    {
        foreach (range(1, 10) as $index) {
            Article::factory()->published()->create(['title' => 'Bài số '.$index, 'published_at' => now()->subMinutes(100 - $index)]);
        }

        $this->get(route('home'))->assertSee('href="'.route('news.index').'" class="transition-all hover:underline">News</a>', false);
        $page = $this->get(route('news.index'))->assertOk()->assertSee('Bài số 10')->assertDontSee('Bài số 1<', false);
        $this->assertSame(9, substr_count($page->getContent(), 'data-news-card'));
        $this->assertSame(1, substr_count($page->getContent(), 'data-news-featured'));
        $this->assertMatchesRegularExpression('~data-news-featured>.*?Bài số 10.*?Đọc bài~s', $page->getContent(), 'The newest article leads the first page.');
        $this->get(route('news.index', ['page' => 2]))->assertOk()->assertSee('Bài số 1')->assertDontSee('data-news-featured', false);
    }

    public function test_editing_a_hidden_article_keeps_it_hidden_unless_admin_publishes_it(): void
    {
        $hidden = Article::factory()->hidden()->create(['title' => 'Bài đã ẩn']);
        $draft = Article::factory()->create();
        $this->actingAs($this->admin);

        $this->get(route('admin.articles.edit', $hidden))->assertOk()
            ->assertSee('value="HIDDEN" class="accent-black" checked', false)->assertSee('Giữ ẩn');
        $this->put(route('admin.articles.update', $hidden), $this->fields(['title' => 'Sửa chính tả', 'status' => 'HIDDEN']))->assertRedirect();
        $this->assertSame([ArticleStatus::Hidden, 'Sửa chính tả'], [$hidden->fresh()->status, $hidden->fresh()->title]);

        $this->get(route('admin.articles.edit', $draft))->assertOk()->assertDontSee('Giữ ẩn');
        $this->put(route('admin.articles.update', $draft), $this->fields(['status' => 'HIDDEN']))->assertSessionHasErrors('status');
        $this->assertSame(ArticleStatus::Draft, $draft->fresh()->status, 'Hiding goes through the hide action, not the form.');

        $this->put(route('admin.articles.update', $hidden), $this->fields(['status' => 'PUBLISHED']))->assertRedirect();
        $this->assertSame(ArticleStatus::Published, $hidden->fresh()->status);
        $this->get(route('news.show', $hidden->slug))->assertOk();
    }

    public function test_an_article_suggests_the_latest_other_published_articles(): void
    {
        $current = Article::factory()->published()->create(['title' => 'Bài đang đọc', 'published_at' => now()->subDays(5)]);
        foreach (['Cũ nhất' => 4, 'Thứ ba' => 3, 'Thứ hai' => 2, 'Mới nhất' => 1] as $title => $days) {
            Article::factory()->published()->create(['title' => $title, 'published_at' => now()->subDays($days)]);
        }
        Article::factory()->create(['title' => 'Nháp không gợi ý']);
        Article::factory()->hidden()->create(['title' => 'Ẩn không gợi ý']);

        $this->get(route('news.show', $current->slug))->assertOk()
            ->assertViewHas('related', fn ($related) => $related->pluck('title')->all() === ['Mới nhất', 'Thứ hai', 'Thứ ba'])
            ->assertSee('Bài viết khác')->assertDontSee('Nháp không gợi ý')->assertDontSee('Ẩn không gợi ý');

        Article::query()->whereKeyNot($current->id)->delete();
        $this->get(route('news.show', $current->slug))->assertOk()->assertDontSee('data-related-articles', false);
    }

    public function test_demo_seeder_writes_shop_articles_through_the_service_once(): void
    {
        config(['store.demo.enabled' => true]);
        app(BusinessClock::class)->freeze('2026-11-03 05:00:00');
        $this->seed(NewsDemoSeeder::class);
        $this->seed(NewsDemoSeeder::class);

        $articles = Article::query()->orderBy('published_at')->get();
        $this->assertCount(9, $articles, 'Running twice does not duplicate.');
        $published = $articles->where('status', ArticleStatus::Published);
        $this->assertCount(8, $published);
        $this->assertSame(ArticleStatus::Draft, $articles->firstWhere('published_at', null)->status);
        $this->assertGreaterThan(50, $published->first()->published_at->diffInDays($published->last()->published_at));
        foreach ($articles as $article) {
            $this->assertMatchesRegularExpression('~^articles/[A-Za-z0-9]{40}\.jpg$~', $article->cover_path);
            Storage::disk('public')->assertExists($article->cover_path);
            $this->assertGreaterThanOrEqual(2, count(preg_split('/\R{2,}/', $article->body)));
        }
        $this->assertNotNull(Article::query()->where('slug', 'air-force-1-07-vi-sao-40-nam-van-ban-chay')->first());

        $this->get(route('news.index'))->assertOk()->assertSee('Air Max 90: biểu tượng đệm khí trở lại')->assertDontSee('Lịch hàng về tháng tới');
    }

    public function test_readers_answer_was_it_helpful_once_and_can_change_their_mind(): void
    {
        $article = Article::factory()->published()->create(['title' => 'Bài hữu ích']);
        // A signed-in reader keeps one identity across these test requests (guests are covered at service level).
        $this->actingAs(User::factory()->customer()->create());
        $this->get(route('news.show', $article->slug))->assertOk()
            ->assertSee('Bài viết có hữu ích không?')->assertSee('aria-pressed="false"', false)->assertDontSee('aria-pressed="true"', false);

        $this->post(route('news.feedback', $article->slug), ['helpful' => 'yes'])
            ->assertRedirect(route('news.show', $article->slug).'#article-feedback');
        $this->followingRedirects()->post(route('news.feedback', $article->slug), ['helpful' => 'yes'])->assertOk()->assertSee('Cảm ơn bạn đã góp ý!');
        $this->get(route('news.show', $article->slug))->assertSee('1 người thấy bài viết này hữu ích.')->assertSee('Bạn đã trả lời.')
            ->assertSee('name="helpful" value="yes" aria-pressed="true"', false);

        $this->post(route('news.feedback', $article->slug), ['helpful' => 'no']);
        $this->assertSame([false], DB::table('article_feedback')->where('article_id', $article->id)->pluck('is_helpful')->map(fn ($value) => (bool) $value)->all(), 'One answer per reader, changed in place.');
        $this->get(route('news.show', $article->slug))->assertSee('name="helpful" value="no" aria-pressed="true"', false)->assertDontSee('người thấy bài viết này hữu ích');

        $stored = DB::table('article_feedback')->value('voter_hash');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $stored);
        $this->assertNotSame((string) auth()->id(), $stored, 'Only a keyed hash of the reader is stored.');

        $this->post(route('news.feedback', $article->slug), ['helpful' => 'maybe'])->assertSessionHasErrors('helpful');
    }

    public function test_each_reader_counts_once_and_unpublished_articles_take_no_answers(): void
    {
        $article = Article::factory()->published()->create();
        $service = app(ArticleService::class);
        $reader = User::factory()->customer()->create();
        $service->recordFeedback($article, null, 'guest-session-a', true);
        $service->recordFeedback($article, null, 'guest-session-b', true);
        $service->recordFeedback($article, $reader, 'phone-session', false);
        $service->recordFeedback($article, $reader, 'laptop-session', true);

        $this->assertSame(3, DB::table('article_feedback')->where('article_id', $article->id)->count(), 'A signed-in reader is one voter across devices.');
        $this->assertTrue($service->feedbackOf($article, $reader, 'any-session'));
        $this->assertNull($service->feedbackOf($article, null, 'guest-session-c'));

        foreach ([Article::factory()->create(), Article::factory()->hidden()->create()] as $hidden) {
            $this->post(route('news.feedback', $hidden->slug), ['helpful' => 'yes'])->assertNotFound();
        }
        $this->assertSame(3, DB::table('article_feedback')->count());
    }

    public function test_admin_sees_which_articles_readers_found_helpful(): void
    {
        $popular = Article::factory()->published()->create(['title' => 'Bài được thích']);
        $mixed = Article::factory()->published()->create(['title' => 'Bài chia rẽ']);
        Article::factory()->published()->create(['title' => 'Bài chưa ai đánh giá']);
        ArticleFeedback::factory()->count(3)->create(['article_id' => $popular->id]);
        ArticleFeedback::factory()->create(['article_id' => $mixed->id]);
        ArticleFeedback::factory()->notHelpful()->create(['article_id' => $mixed->id]);

        $page = $this->actingAs($this->admin)->get(route('admin.articles.index', ['sort' => 'helpful']))->assertOk()
            ->assertSee('Người đọc đánh giá')->assertSee('100% thấy hữu ích')->assertSee('50% thấy hữu ích')->assertSee('Chưa có');
        $this->assertSame(['Bài được thích', 'Bài chia rẽ'], array_slice($page->viewData('articles')->pluck('title')->all(), 0, 2));
        $this->assertSame([3, 0], [$page->viewData('articles')->first()->helpful_count, $page->viewData('articles')->first()->unhelpful_count]);

        $unhelpful = $this->get(route('admin.articles.index', ['sort' => 'unhelpful']))->assertOk()->assertSee('Không hữu ích nhất');
        $this->assertSame('Bài chia rẽ', $unhelpful->viewData('articles')->first()->title);

        $this->actingAs(User::factory()->staff()->create())->get(route('admin.articles.index'))->assertForbidden();
    }

    public function test_demo_feedback_follows_article_quality_and_age_and_replays_without_new_votes(): void
    {
        config(['store.demo.enabled' => true]);
        app(BusinessClock::class)->freeze('2026-11-04 05:00:00');
        $this->seed(NewsDemoSeeder::class);
        app(BusinessClock::class)->freeze('2026-11-04 05:00:00');
        $this->seed(ArticleFeedbackDemoSeeder::class);
        $first = DB::table('article_feedback')->orderBy('id')->get(['article_id', 'voter_hash', 'is_helpful'])->toArray();
        app(BusinessClock::class)->freeze('2026-11-04 05:00:00');
        $this->seed(ArticleFeedbackDemoSeeder::class);
        $this->assertEquals($first, DB::table('article_feedback')->orderBy('id')->get(['article_id', 'voter_hash', 'is_helpful'])->toArray(), 'Replaying keeps the same readers and answers.');

        $stats = fn (string $slug): array => (array) DB::table('article_feedback')->join('articles', 'articles.id', '=', 'article_feedback.article_id')
            ->where('articles.slug', $slug)->selectRaw('COUNT(*) AS answers, AVG(is_helpful) AS share')->first();
        $guide = $stats('cach-chon-size-giay-nike-chuan-khi-mua-online');
        $dunk = $stats('dunk-low-panda-hien-tuong-chua-ha-nhiet');
        $fresh = $stats('air-max-90-bieu-tuong-dem-khi-tro-lai');
        $this->assertGreaterThan(30, $guide['answers']);
        $this->assertGreaterThan(0.8, (float) $guide['share']);
        $this->assertLessThan((float) $guide['share'] - 0.15, (float) $dunk['share'], 'Back-ordered Dunk Low draws more thumbs down.');
        $this->assertLessThan($guide['answers'], $fresh['answers'], 'A nine-day-old article has fewer answers yet.');
        $this->assertSame(0, DB::table('article_feedback')->join('articles', 'articles.id', '=', 'article_feedback.article_id')->where('articles.status', '!=', 'PUBLISHED')->count());
        $this->assertSame(0, DB::table('article_feedback')->join('articles', 'articles.id', '=', 'article_feedback.article_id')->whereColumn('article_feedback.updated_at', '<', 'articles.published_at')->count(), 'Nobody answers before an article is published.');
    }

    /** @return array<string, mixed> */
    private function fields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cách chọn size giày chạy bộ',
            'summary' => 'Ba bước để chọn đúng size giày chạy.',
            'body' => "Đo chiều dài bàn chân vào buổi chiều.\n\nMang tất khi thử giày.",
            'status' => 'PUBLISHED',
        ], $overrides);
    }
}
