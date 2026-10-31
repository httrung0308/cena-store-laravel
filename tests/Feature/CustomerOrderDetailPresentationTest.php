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

    public function test_json_address_snapshot_is_whitelisted_and_each_part_is_escaped(): void
    {
        $order = [
            'order_code' => 'ORDER-01',
            'shipping_address_snapshot' => [
                'street_address' => '12 <img src=x onerror=alert(1)>',
                'legacy_district_text' => 'Quận cũ <b>3</b>',
                'ward_name_snapshot' => 'Phường <script>bad</script>',
                'province_name_snapshot' => 'Thành phố "HCM"',
                'province_code_snapshot' => '79',
                'dataset_version' => 'v1',
                'private_proof_path' => 'SECRET-PROOF',
                'average_cost' => 'SECRET-COST',
                'internal_note' => 'SECRET-NOTE',
            ],
        ];
        $html = Blade::render('<x-orders.customer-detail :order="$order" />', compact('order'));
        $this->assertStringContainsString('12 &lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('Quận cũ &lt;b&gt;3&lt;/b&gt;', $html);
        $this->assertStringContainsString('Phường &lt;script&gt;bad&lt;/script&gt;', $html);
        $this->assertStringContainsString('Thành phố &quot;HCM&quot;', $html);
        foreach (['SECRET-PROOF', 'SECRET-COST', 'SECRET-NOTE', 'province_code_snapshot', 'dataset_version'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
    }

    public function test_incomplete_address_snapshot_and_json_object_render_safely(): void
    {
        foreach ([
            ['street' => '12 Rue'],
            ['ward_name_snapshot' => 'Phường 1'],
            [],
            (object) ['province_name' => 'Đồng Nai', 'unknown_private_note' => 'NEVER-ECHO'],
            ['street_address' => ['unexpected-array'], 'ward_name' => null, 'private' => 'NEVER-ECHO'],
        ] as $snapshot) {
            $order = ['shipping_address_snapshot' => $snapshot];
            $html = Blade::render('<x-orders.customer-detail :order="$order" />', compact('order'));
            $this->assertStringContainsString('Địa chỉ:', $html);
            $this->assertStringNotContainsString('NEVER-ECHO', $html);
            $this->assertStringNotContainsString('unexpected-array', $html);
        }
    }
}
