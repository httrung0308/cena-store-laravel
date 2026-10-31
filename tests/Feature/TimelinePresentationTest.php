<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TimelinePresentationTest extends TestCase
{
    public function test_history_filters_internal_entries_and_preserves_input_order(): void
    {
        $entries = [
            ['id' => '2', 'status' => 'A', 'status_label' => 'Second', 'created_at' => '2026-10-31T12:00:00.123456+00:00', 'visibility' => 'CUSTOMER', 'actor_name_snapshot' => '<b>Alex</b>', 'note' => '<script>unsafe</script>'],
            ['id' => '3', 'status' => 'B', 'status_label' => 'PRIVATE', 'created_at' => '2026-10-31T12:00:00.123456+00:00', 'visibility' => 'INTERNAL', 'note' => 'PRIVATE_NOTE'],
            ['id' => '1', 'status' => 'C', 'status_label' => 'First', 'created_at' => '2026-10-31T12:00:00.123456+00:00', 'visibility' => 'CUSTOMER'],
        ];
        $html = Blade::render('<x-orders.timeline :entries="$entries" />', compact('entries'));
        $this->assertTrue(strpos($html, 'Second') < strpos($html, 'First'));
        $this->assertStringNotContainsString('PRIVATE_NOTE', $html);
        $this->assertStringContainsString('19:00:00.123456', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
