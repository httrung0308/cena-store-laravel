<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class RegisterFormPresentationTest extends TestCase
{
    public function test_registration_whitelists_fields_and_escapes_old_input(): void
    {
        request()->setLaravelSession(session()->driver());
        session()->flashInput(['full_name' => '<svg onload=alert(1)>', 'email' => 'x@example.invalid', 'password' => 'PRIVATE_PASSWORD', 'role' => 'admin']);
        $html = Blade::render('<x-auth.register-form action="/auth/register" />');
        foreach (['full_name', 'email', 'password', 'password_confirmation'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $html);
        }
        foreach (['name="role"', 'name="is_active"', 'name="auth_version"', 'name="stock"', 'PRIVATE_PASSWORD', '<svg onload'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertStringContainsString('&lt;svg', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }
}
