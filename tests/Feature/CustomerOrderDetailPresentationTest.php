<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CustomerOrderDetailPresentationTest extends TestCase
{
    public function test_detail_uses_snapshots_even_without_catalog_and_hides_private_keys(): void
    {
        $order = ['order_code' => 'ORD01', 'total_amount' => 0, 'shipping_address_snapshot' => '<script>bad</script>', 'internal_note' => 'SECRET'];
        $items = [['product_name_snapshot' => 'Archived model', 'size_value_snapshot' => '40.5', 'quantity' => 1, 'units' => [['unit_no' => '01', 'state' => 'DELIVERED', 'current_cogs' => 'VERY_PRIVATE']]]];
        $html = Blade::render('<x-orders.customer-detail :order="$order" :items="$items" />', compact('order', 'items'));
        $this->assertStringContainsString('Archived model', $html);
        $this->assertStringContainsString('Size 40.5', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('VERY_PRIVATE', $html);
        $this->assertStringNotContainsString('SECRET', $html);
        $this->assertStringNotContainsString('href=', $html);
    }
}
