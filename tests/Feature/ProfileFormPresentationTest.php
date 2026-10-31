<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProfileFormPresentationTest extends TestCase
{
    public function test_profile_form_only_exposes_safe_fields_and_escapes_values(): void
    {
        $profile = ['full_name' => '<img onerror=alert(1)>', 'phone' => '00001', 'email' => 'test@example.invalid', 'role' => 'admin', 'cost' => 'PRIVATE_COST'];
        $html = Blade::render('<x-account.profile-form action="/account" :profile="$profile" />', compact('profile'));
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('value="&lt;img', $html);
        $this->assertStringNotContainsString('<img onerror', $html);
        $this->assertStringNotContainsString('PRIVATE_COST', $html);
        $this->assertStringNotContainsString('name="role"', $html);
        $this->assertStringNotContainsString('name="email"', $html);
        $this->assertStringContainsString('type="file"', $html);
    }
}
