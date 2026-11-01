<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CartLinePresentationTest extends TestCase
{
    public function test_line_only_displays_safe_snapshot_with_zero_and_decimal_size(): void
    {
        $line = ['id' => 1, 'name' => '<script>x</script>', 'color_name' => 'Đỏ', 'size_value' => '40.5', 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0, 'average_cost' => 100000, 'proof_path' => 'PRIVATE'];
        $html = Blade::render('<x-cart.line :line="$line" />', compact('line'));
        $this->assertStringContainsString('Size 40.5', $html);
        $this->assertStringContainsString('0 ₫', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('100000', $html);
        $this->assertStringNotContainsString('PRIVATE', $html);
        $this->assertStringNotContainsString('method="POST"', $html);
    }
}
