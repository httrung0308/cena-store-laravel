<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductCardPresentationTest extends TestCase
{
    public function test_card_escapes_label_preserves_zero_and_does_not_expose_cost(): void
    {
        $product = ['name' => '<script>unsafe</script>', 'brand_line' => 'Cena', 'effective_price' => 0, 'base_price' => 0, 'sold_out' => true, 'average_cost' => 999999];
        $html = Blade::render('<x-catalog.product-card :product="$product" />', compact('product'));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('0 ₫', $html);
        $this->assertStringContainsString('Hết hàng', $html);
        $this->assertStringNotContainsString('999999', $html);
    }

    public function test_missing_presenter_prices_are_unavailable_not_recomputed(): void
    {
        $product = ['name' => 'A', 'base_price' => 1000000, 'promotion_price' => 500000];
        $html = Blade::render('<x-catalog.product-card :product="$product" />', compact('product'));
        $this->assertStringContainsString('Giá chưa khả dụng', $html);
        $this->assertStringNotContainsString('500.000', $html);
    }
}
