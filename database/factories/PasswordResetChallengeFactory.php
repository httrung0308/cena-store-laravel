<?php

namespace Database\Factories;

use App\Models\PasswordResetChallenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<PasswordResetChallenge> */
class PasswordResetChallengeFactory extends Factory
{
    protected $model = PasswordResetChallenge::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_token' => hash('sha256', (string) Str::uuid()),
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now(),
            'last_sent_at' => now(),
            'session_binding_hash' => hash('sha256', (string) Str::uuid()),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
