<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LoginFormPresentationTest extends TestCase
{
    public function test_login_form_has_post_csrf_empty_password_and_escapes_email(): void
    {
        request()->setLaravelSession(session()->driver());
        session()->flashInput(['email' => '\"><img src=x onerror=alert(1)>', 'password' => 'SECRET_DO_NOT_RENDER']);
        $html = Blade::render('<x-auth.login-form action="/auth/login" />');
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;img', $html);
        $this->assertStringNotContainsString('SECRET_DO_NOT_RENDER', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
    }

    public function test_missing_action_disables_submit_without_fake_link(): void
    {
        $html = Blade::render('<x-auth.login-form />');
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('href="/login"', $html);
    }
}
