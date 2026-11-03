<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Admin-only news publishing. Covers are stored under server-generated names; articles are hidden, never deleted. */
final class ArticleService
{
    private const COVER_PATTERN = '~^articles/[A-Za-z0-9]{40}\.(?:jpe?g|png|webp)$~D';

    public function __construct(private readonly BusinessClock $clock) {}

    /**
     * @param  array{title: string, summary: string, body: string, status: string}  $input
     */
    public function save(User $actor, ?Article $article, array $input, ?UploadedFile $cover): Article
    {
        $newCover = $cover === null ? null : $this->storeCover($cover);
        $oldCover = null;
        try {
            $article = DB::transaction(function () use ($actor, $article, $input, $newCover, &$oldCover): Article {
                $record = $article === null ? new Article : Article::query()->whereKey($article->id)->lockForUpdate()->firstOrFail();
                $record->fill(['title' => $input['title'], 'summary' => $input['summary'], 'body' => $input['body']]);
                if (! $record->exists) {
                    $record->author_id = $actor->id;
                    $record->slug = $this->uniqueSlug($input['title']);
                }
                $status = ArticleStatus::from($input['status']);
                $record->status = $status;
                if ($status === ArticleStatus::Published && $record->published_at === null) {
                    $record->published_at = $this->clock->now();
                }
                if ($newCover !== null) {
                    $oldCover = $record->cover_path;
                    $record->cover_path = $newCover;
                }
                $record->save();

                return $record;
            });
        } catch (Throwable $exception) {
            $this->discard($newCover);
            throw $exception;
        }
        $this->discard($oldCover);

        return $article;
    }

    public function hide(Article $article): void
    {
        Article::query()->whereKey($article->id)->update(['status' => ArticleStatus::Hidden->value, 'updated_at' => $this->clock->now()->format('Y-m-d H:i:s.u')]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 200, '') ?: 'bai-viet';
        $slug = $base;
        for ($suffix = 2; Article::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function storeCover(UploadedFile $file): string
    {
        $path = $file->storePublicly('articles', 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Cannot store article cover.');
        }

        return $path;
    }

    private function discard(?string $path): void
    {
        if ($path !== null && preg_match(self::COVER_PATTERN, $path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
