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

    public function test_missing_null_empty_and_invalid_timestamps_never_generate_a_time(): void
    {
        $entries = [
            ['id' => 'a', 'status_label' => 'Missing', 'visibility' => 'CUSTOMER'],
            ['id' => 'b', 'status_label' => 'Null', 'created_at' => null, 'visibility' => 'CUSTOMER'],
            ['id' => 'c', 'status_label' => 'Empty', 'created_at' => '', 'visibility' => 'CUSTOMER'],
            ['id' => 'd', 'status_label' => 'Invalid', 'created_at' => 'tomorrow', 'visibility' => 'CUSTOMER'],
            ['id' => 'e', 'status_label' => 'Bad date', 'created_at' => '2026-02-31T12:00:00+07:00', 'visibility' => 'CUSTOMER'],
            ['id' => 'f', 'status_label' => 'Wrong type', 'created_at' => ['secret'], 'visibility' => 'CUSTOMER'],
        ];
        $html = Blade::render('<x-orders.timeline :entries="$entries" />', compact('entries'));
        foreach (['Missing', 'Null', 'Empty', 'Invalid', 'Bad date', 'Wrong type'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringNotContainsString('<time', $html);
        $this->assertStringNotContainsString('datetime=""', $html);
        $this->assertStringNotContainsString('secret', $html);
    }

    public function test_valid_offset_and_microsecond_timestamps_are_preserved(): void
    {
        $entries = [
            ['id' => 1, 'status_label' => 'UTC', 'created_at' => '2026-10-31T12:00:00.123456+00:00', 'visibility' => 'CUSTOMER'],
            ['id' => 2, 'status_label' => 'Offset', 'created_at' => '2026-10-31T19:00:00.123456+07:00', 'visibility' => 'CUSTOMER'],
            ['id' => 3, 'status_label' => 'Z', 'created_at' => '2026-10-31T12:00:00Z', 'visibility' => 'CUSTOMER'],
        ];
        $html = Blade::render('<x-orders.timeline :entries="$entries" />', compact('entries'));
        $this->assertSame(3, substr_count($html, '<time '));
        $this->assertStringContainsString('datetime="2026-10-31T12:00:00.123456+00:00"', $html);
        $this->assertStringContainsString('datetime="2026-10-31T19:00:00.123456+07:00"', $html);
        $this->assertStringContainsString('19:00:00.123456', $html);
        $this->assertTrue(strpos($html, 'UTC') < strpos($html, 'Offset'));
    }

}
