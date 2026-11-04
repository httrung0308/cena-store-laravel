<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reader's "helpful / not helpful" answer to an article; the reader is only known by a keyed hash. */
#[Fillable([])]
class ArticleFeedback extends DomainModel
{
    protected $table = 'article_feedback';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'article_id' => 'integer',
            'is_helpful' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
