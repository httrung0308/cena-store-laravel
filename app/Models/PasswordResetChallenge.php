<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['challenge_token', 'otp_hash', 'session_binding_hash'])]
class PasswordResetChallenge extends DomainModel
{
    protected $table = 'password_reset_challenges';

    protected $attributes = [
        'attempts' => '0',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'last_sent_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
