<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AfterSalesDetailPresentationTest extends TestCase
{
    public function test_customer_rma_detail_does_not_emit_private_paths_or_notes(): void
    {
        $request = ['request_code' => 'RMA1', 'type' => 'SIZE_EXCHANGE', 'status' => 'NEW', 'customer_reason' => '<script>hello</script>', 'returned_cost' => 123456, 'internal_note' => 'NOPE'];
        $histories = [['status' => 'NEW', 'status_label' => 'Received', 'created_at' => '2026-10-31T00:00:00+00:00', 'visibility' => 'CUSTOMER'], ['status' => 'PRIVATE', 'status_label' => 'PRIVATE', 'visibility' => 'INTERNAL']];
        $attachments = [['id' => 1, 'private_path' => 'storage/private/secret']];
        $html = Blade::render('<x-after-sales.customer-detail :request="$request" :histories="$histories" :attachments="$attachments" />', compact('request', 'histories', 'attachments'));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('storage/private', $html);
        $this->assertStringNotContainsString('123456', $html);
        $this->assertStringNotContainsString('NOPE', $html);
        $this->assertStringNotContainsString('PRIVATE', $html);
    }
}
