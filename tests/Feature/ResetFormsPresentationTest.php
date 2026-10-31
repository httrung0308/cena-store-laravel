<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ResetFormsPresentationTest extends TestCase
{
    public function test_otp_field_is_text_preserves_leading_zeros_without_prefilling(): void
    {
        request()->setLaravelSession(session()->driver());
        session()->flashInput(['otp' => '000001', 'password' => 'NEVER_PRINT']);
        $html = Blade::render('<x-auth.verify-reset-form action="/verify" />');
        $this->assertStringContainsString('name="otp" type="text" value=""', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('pattern="[0-9]{6}"', $html);
        $this->assertStringNotContainsString('value="000001"', $html);
    }

    public function test_password_reset_has_no_secrets_or_implicit_success(): void
    {
        $html = Blade::render('<x-auth.reset-password-form />');
        $this->assertStringContainsString('name="password_confirmation"', $html);
        $this->assertStringNotContainsString('name="token"', $html);
        $this->assertStringNotContainsString('reset thành công', $html);
        $this->assertStringContainsString('disabled', $html);
    }
}
