<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Shoe news written by Admin; only published articles are public. Hidden instead of deleted. */
#[Fillable(['title', 'summary', 'body'])]
#[Hidden([])]
class Article extends DomainModel
{
    protected $table = 'articles';

    protected $attributes = [
        'status' => 'DRAFT',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'author_id' => 'integer',
            'status' => ArticleStatus::class,
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(ArticleFeedback::class, 'article_id');
    }

    /** @param Builder<Article> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ArticleStatus::Published)->whereNotNull('published_at')
            ->where('published_at', '<=', app(BusinessClock::class)->now()->format('Y-m-d H:i:s.u'));
    }

    public function coverUrl(): string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : asset('legacy/img/men.jpg');
    }
}
