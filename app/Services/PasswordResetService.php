<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetChallenge;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * OTP password reset (WF-02). One challenge per user, bound to the requesting browser by a random token whose
 * hash is stored; the OTP itself is only hashed. Unknown emails get the same answer and a dummy binding, so the
 * flow never reveals whether an account exists.
 */
final class PasswordResetService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly BusinessClock $clock) {}

    /**
     * @return array{challenge: string, binding: string, cooldown: int} what the session keeps
     */
    public function request(string $email, ?string $currentChallenge, ?string $currentBinding): array
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->where('is_active', true)->first();
        if ($user === null) {
            return ['challenge' => bin2hex(random_bytes(32)), 'binding' => bin2hex(random_bytes(32)), 'cooldown' => 0];
        }

        $otp = null;
        $result = DB::transaction(function () use ($user, $currentChallenge, $currentBinding, &$otp): array {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $challenge = PasswordResetChallenge::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $now = $this->clock->now();
            $waited = $challenge === null ? PHP_INT_MAX : (int) $challenge->last_sent_at->diffInSeconds($now, true);
            $cooldown = (int) config('store.deadlines.otp_resend_seconds', 60);
            if ($challenge !== null && $waited < $cooldown) {
                // Too soon: keep the live challenge; a browser already bound to it stays bound.
                $bound = $currentChallenge !== null && $currentBinding !== null && hash_equals($challenge->challenge_token, $currentChallenge)
                    && hash_equals($challenge->session_binding_hash, hash('sha256', $currentBinding));

                return ['challenge' => $bound ? $currentChallenge : bin2hex(random_bytes(32)), 'binding' => $bound ? $currentBinding : bin2hex(random_bytes(32)), 'cooldown' => $cooldown - $waited];
            }
            $challenge ??= new PasswordResetChallenge;
            $binding = bin2hex(random_bytes(32));
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $challenge->user_id = $user->id;
            $challenge->challenge_token = bin2hex(random_bytes(32));
            $challenge->otp_hash = Hash::make($otp);
            $challenge->attempts = 0;
            $challenge->expires_at = $this->clock->deadline('otp');
            $challenge->last_sent_at = $now;
            $challenge->verified_at = null;
            $challenge->used_at = null;
            $challenge->invalidated_at = null;
            $challenge->session_binding_hash = hash('sha256', $binding);
            $challenge->created_at ??= $now;
            $challenge->updated_at = $now;
            $challenge->save();

            return ['challenge' => $challenge->challenge_token, 'binding' => $binding, 'cooldown' => 0];
        });

        if ($otp !== null) {
            try {
                Mail::to($user->email)->send(new PasswordResetOtpMail($otp, (int) config('store.deadlines.otp_minutes', 15)));
            } catch (Throwable $exception) {
                // Never log the code; the page only says the request was received.
                Log::warning('Password reset mail could not be sent.', ['user_id' => $user->id, 'exception' => $exception::class]);
            }
        }

        return $result;
    }

    /** Wrong codes are counted and committed; the fifth wrong code locks the challenge. */
    public function verify(string $challengeToken, string $binding, string $otp): bool
    {
        return DB::transaction(function () use ($challengeToken, $binding, $otp): bool {
            $challenge = $this->usable($challengeToken, $binding);
            if ($challenge === null || $challenge->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }
            if (! Hash::check($otp, $challenge->otp_hash)) {
                $challenge->attempts += 1;
                $challenge->updated_at = $this->clock->now();
                $challenge->save();

                return false;
            }
            $challenge->verified_at = $this->clock->now();
            $challenge->updated_at = $this->clock->now();
            $challenge->save();

            return true;
        });
    }

    public function attemptsLeft(string $challengeToken): int
    {
        $attempts = PasswordResetChallenge::query()->where('challenge_token', $challengeToken)->value('attempts');

        return $attempts === null ? self::MAX_ATTEMPTS : max(0, self::MAX_ATTEMPTS - (int) $attempts);
    }

    /** Consume the verified challenge exactly once and sign the user out everywhere. */
    public function reset(string $challengeToken, string $binding, string $password): void
    {
        DB::transaction(function () use ($challengeToken, $binding, $password): void {
            $userId = PasswordResetChallenge::query()->where('challenge_token', $challengeToken)->value('user_id');
            $user = $userId === null ? null : User::query()->whereKey($userId)->lockForUpdate()->first();
            $challenge = $this->usable($challengeToken, $binding);
            if ($user === null || $challenge === null || $challenge->verified_at === null) {
                throw new DomainException(ErrorCode::ValidationFailed, ['password' => ['Yêu cầu đặt lại mật khẩu không còn hiệu lực. Vui lòng yêu cầu mã mới.']]);
            }
            $now = $this->clock->now();
            $user->password = $password;
            $user->auth_version += 1;
            $user->remember_token = Str::random(60);
            $user->updated_at = $now;
            $user->save();
            $challenge->used_at = $now;
            $challenge->updated_at = $now;
            $challenge->save();
        });
    }

    private function usable(string $challengeToken, string $binding): ?PasswordResetChallenge
    {
        $challenge = PasswordResetChallenge::query()->where('challenge_token', $challengeToken)->lockForUpdate()->first();
        if ($challenge === null || ! hash_equals($challenge->session_binding_hash, hash('sha256', $binding))
            || $challenge->used_at !== null || $challenge->invalidated_at !== null || ! $this->clock->beforeDeadline($challenge->expires_at)) {
            return null;
        }

        return $challenge;
    }
}
