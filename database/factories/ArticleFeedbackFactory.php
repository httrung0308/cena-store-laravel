<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArticleFeedback> */
class ArticleFeedbackFactory extends Factory
{
    protected $model = ArticleFeedback::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory()->published(),
            'voter_hash' => hash('sha256', fake()->unique()->uuid()),
            'is_helpful' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function notHelpful(): static
    {
        return $this->state(fn (): array => ['is_helpful' => false]);
    }
}
