<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'author_id' => User::factory()->administrator(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'summary' => fake()->sentence(15),
            'body' => fake()->paragraphs(3, true),
            'status' => ArticleStatus::Draft,
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ArticleStatus::Published, 'published_at' => now()->subHour()]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['status' => ArticleStatus::Hidden, 'published_at' => now()->subDay()]);
    }
}
