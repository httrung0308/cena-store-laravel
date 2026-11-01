<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ChangePasswordFormPresentationTest extends TestCase
{
    public function test_old_password_values_never_render_and_action_is_post(): void
    {
        request()->setLaravelSession(session()->driver());
        session()->flashInput(['current_password' => 'OLD_SECRET', 'password' => 'NEW_SECRET']);
        $html = Blade::render('<x-account.change-password-form action="/account/password" />');
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method"', $html);
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }
        $this->assertStringNotContainsString('OLD_SECRET', $html);
        $this->assertStringNotContainsString('NEW_SECRET', $html);
    }
}
