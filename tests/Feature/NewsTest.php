<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
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

        $this->get(route('home'))->assertSee('href="'.route('news.index').'"', false);
        $page = $this->get(route('news.index'))->assertOk()->assertSee('Bài số 10')->assertDontSee('Bài số 1<', false);
        $this->assertSame(9, substr_count($page->getContent(), 'data-news-card'));
        $this->get(route('news.index', ['page' => 2]))->assertOk()->assertSee('Bài số 1');
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
