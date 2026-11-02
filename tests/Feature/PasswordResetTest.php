<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetChallenge;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->customer()->create(['email' => 'quen@nike.test', 'password' => 'Mat-khau-cu-8']);
        RateLimiter::clear('password-reset:quen@nike.test|127.0.0.1');
        RateLimiter::clear('login:quen@nike.test|127.0.0.1');
    }

    public function test_full_reset_with_the_emailed_code_ends_old_sessions(): void
    {
        $this->get(route('login'))->assertSee('href="'.route('password.request').'"', false);
        $this->post(route('password.email'), ['email' => ' Quen@Nike.test '])->assertRedirect(route('password.reset'));
        $otp = $this->sentOtp();
        $challenge = PasswordResetChallenge::query()->where('user_id', $this->user->id)->sole();
        $this->assertNotSame($otp, $challenge->otp_hash);
        $this->assertTrue(Hash::check($otp, $challenge->otp_hash));

        $this->get(route('password.reset'))->assertOk()->assertSee('Kiểm tra hộp thư')->assertDontSee($otp);
        $this->post(route('password.verify'), ['otp' => $otp])->assertRedirect(route('password.reset'));
        $this->get(route('password.reset'))->assertSee('Đặt mật khẩu mới');
        $this->post(route('password.update'), ['password' => 'Mat-khau-moi-9', 'password_confirmation' => 'Mat-khau-moi-9'])->assertRedirect(route('login'));

        $this->user->refresh();
        $this->assertTrue(Hash::check('Mat-khau-moi-9', $this->user->password));
        $this->assertSame(2, $this->user->auth_version);
        $this->assertNotNull($challenge->fresh()->used_at);
        $this->post(route('password.update'), ['password' => 'Mat-khau-khac-9', 'password_confirmation' => 'Mat-khau-khac-9'])->assertRedirect(route('password.request'));
        $this->assertTrue(Hash::check('Mat-khau-moi-9', $this->user->fresh()->password), 'A used challenge cannot reset twice.');
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        $known = $this->post(route('password.email'), ['email' => 'quen@nike.test']);
        RateLimiter::clear('password-reset:khong-co@nike.test|127.0.0.1');
        $unknown = $this->post(route('password.email'), ['email' => 'khong-co@nike.test']);

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        $this->assertSame(session('status'), 'Nếu email có tài khoản, mã gồm 6 chữ số đã được gửi tới hộp thư. Mã có hiệu lực 15 phút.');
        Mail::assertSentCount(1);
        $this->post(route('password.verify'), ['otp' => '123456'])->assertSessionHasErrors('otp');
    }

    public function test_five_wrong_codes_lock_the_challenge_even_for_the_right_code(): void
    {
        $this->post(route('password.email'), ['email' => 'quen@nike.test']);
        $otp = $this->sentOtp();
        $wrong = $otp === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.verify'), ['otp' => $wrong])->assertSessionHasErrors('otp');
        }
        $this->assertSame(5, PasswordResetChallenge::query()->where('user_id', $this->user->id)->value('attempts'));
        $this->post(route('password.verify'), ['otp' => $otp])->assertSessionHasErrors('otp');
        $this->assertNull(PasswordResetChallenge::query()->where('user_id', $this->user->id)->value('verified_at'));
    }

    public function test_code_is_bound_to_the_requesting_browser_expires_and_resend_waits(): void
    {
        $this->post(route('password.email'), ['email' => 'quen@nike.test']);
        $otp = $this->sentOtp();
        $this->post(route('password.resend'))->assertRedirect();
        $this->assertStringContainsString('Vui lòng chờ', (string) session('status'));
        Mail::assertSentCount(1);

        // Another browser with the same code but without this session's binding gets nothing.
        $this->flushSession();
        $this->post(route('password.verify'), ['otp' => $otp])->assertSessionHasErrors('otp');

        $this->post(route('password.email'), ['email' => 'quen@nike.test']);
        $clock = app(BusinessClock::class);
        $clock->freeze(now()->addMinutes(16));
        try {
            $this->post(route('password.verify'), ['otp' => $otp])->assertSessionHasErrors('otp');
        } finally {
            $clock->unfreeze();
        }
    }

    public function test_changing_the_password_invalidates_a_pending_code(): void
    {
        $this->post(route('password.email'), ['email' => 'quen@nike.test']);
        $otp = $this->sentOtp();
        $session = session()->all();

        $this->actingAs($this->user)->patch(route('account.password.update'), [
            'current_password' => 'Mat-khau-cu-8', 'password' => 'Mat-khau-moi-9', 'password_confirmation' => 'Mat-khau-moi-9',
        ]);
        $this->withSession($session)->post(route('password.verify'), ['otp' => $otp])->assertSessionHasErrors('otp');
    }

    private function sentOtp(): string
    {
        $otp = null;
        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp): bool {
            $otp = $mail->otp;

            return $mail->hasTo('quen@nike.test');
        });
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', (string) $otp);

        return (string) $otp;
    }
}
