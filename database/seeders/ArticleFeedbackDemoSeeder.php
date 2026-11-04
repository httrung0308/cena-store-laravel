<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Services\ArticleService;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Local/testing only: readers' "was this helpful?" answers for every published article, through ArticleService.
 * Practical guides (sizing, care, choosing running shoes) get many answers and are mostly helpful; the Dunk Low
 * article draws complaints because sizes are back-ordered; very short articles score lower. Answers cluster in
 * the first two weeks after publishing and thin out afterwards. Deterministic per article; re-running gives the
 * same readers and answers instead of adding votes.
 */
class ArticleFeedbackDemoSeeder extends Seeder
{
    /**
     * Answers per 30 days while the article is fresh, and the share of readers who find it helpful.
     *
     * @var array<string, array{0: int, 1: float}>
     */
    private const PROFILES = [
        'cach-chon-size-giay-nike-chuan-khi-mua-online' => [34, 0.91],
        'cach-ve-sinh-va-bao-quan-giay-sneaker-luon-nhu-moi' => [26, 0.88],
        'pegasus-41-hay-invincible-3-chon-giay-chay-bo-nao' => [22, 0.84],
        'chon-giay-cho-be-di-hoc-em-ben-va-de-mang' => [12, 0.86],
        'air-force-1-07-vi-sao-40-nam-van-ban-chay' => [18, 0.79],
        'air-max-90-bieu-tuong-dem-khi-tro-lai' => [16, 0.74],
        'xu-huong-giay-nu-mua-cuoi-nam' => [12, 0.66],
        'dunk-low-panda-hien-tuong-chua-ha-nhiet' => [20, 0.58],
    ];

    /** Short articles without a profile: few answers, lukewarm. */
    private const DEFAULT_PROFILE = [9, 0.55];

    public function run(ArticleService $articles, BusinessClock $clock): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }
        $now = $clock->now();
        $recorded = 0;
        try {
            foreach (Article::query()->published()->orderBy('published_at')->get() as $article) {
                [$perMonth, $helpfulShare] = self::PROFILES[$article->slug] ?? self::DEFAULT_PROFILE;
                $ageDays = max(0.0, $article->published_at->diffInSeconds($now) / 86_400);
                // Interest fades with a two-week half-life, so an article collects about 1.4 months' worth in total.
                $answers = (int) round($perMonth * 1.4 * (1 - 2 ** (-$ageDays / 14)));
                mt_srand(crc32($article->slug));
                for ($reader = 1; $reader <= $answers; $reader++) {
                    $at = $article->published_at->addSeconds((int) ($ageDays * 86_400 * (mt_rand(0, 1000) / 1000) ** 2.2));
                    $clock->freeze($at->lessThan($now) ? $at : $now);
                    $articles->recordFeedback($article, null, 'demo-reader:'.$article->slug.':'.$reader, mt_rand(1, 1000) <= $helpfulShare * 1000);
                    $recorded++;
                }
            }
        } finally {
            $clock->unfreeze();
        }
        $this->command?->info("Đã ghi {$recorded} lượt đánh giá bài viết demo.");
    }
}
