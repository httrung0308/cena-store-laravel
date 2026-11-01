<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CustomerOrderListPresentationTest extends TestCase
{
    public function test_safe_customer_list_does_not_render_extra_private_fields(): void
    {
        $orders = [['order' => ['order_code' => '<script>x</script>', 'total_amount' => 0, 'payment_status' => 'PENDING', 'cost' => 'PRIVATE_COST', 'internal_note' => 'PRIVATE_NOTE']]];
        $html = Blade::render('<x-orders.customer-list :orders="$orders" />', compact('orders'));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('0 ₫', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('PRIVATE_COST', $html);
        $this->assertStringNotContainsString('PRIVATE_NOTE', $html);
        $this->assertStringNotContainsString('href=', $html);
    }
}
