<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AddressCardsPresentationTest extends TestCase
{
    public function test_safe_card_does_not_create_actions_when_urls_absent(): void
    {
        $address = ['recipient_name' => '<img src=x>', 'phone' => '0900000000', 'street_address' => '<script>alert(1)</script>', 'ward_name_snapshot' => 'Phường A', 'province_name_snapshot' => 'Tỉnh B', 'is_default' => true];
        $html = Blade::render('<x-account.address-card :address="$address" />', compact('address'));
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('method="POST"', $html);
        $this->assertStringContainsString('Mặc định', $html);
    }

    public function test_mutation_buttons_only_render_when_authorized_url_is_supplied(): void
    {
        $html = Blade::render('<x-account.address-card delete-url="/account/address/1" set-default-url="/account/address/1/default" />');
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }
}
