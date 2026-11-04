<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    public function test_home_renders_original_cena_layout_and_compiled_assets(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('storefront.home')
            ->assertSee('<html lang="en">', false)
            ->assertSee('Thể thao là')->assertSee('không ngừng bỏ cuộc.')
            ->assertSee('legacy/img/fakestore-logo.png')->assertDontSee('the smart choice.')
            ->assertSee('legacy/img/main.jpg')
            ->assertSee('Fresh summer collection avaible now')
            ->assertSee('Mua sắm')
            ->assertSee('/build/assets/', false)
            ->assertSee('aria-controls="cart-panel"', false);
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_unimplemented_catalog_does_not_claim_to_exist(): void
    {
        $this->get('/products')->assertNotFound();
    }

    public function test_private_storage_has_no_public_link_or_download_route(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.private.root'));
        $this->assertFalse(config('filesystems.disks.private.serve'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertSame([public_path('storage') => storage_path('app/public')], config('filesystems.links'));
        $this->get('/storage/private/proof.pdf')->assertNotFound();
    }

    public function test_store_contract_uses_vnd_utc_and_one_shipment(): void
    {
        $this->assertSame('VND', config('store.currency'));
        $this->assertSame(0, config('store.money_scale'));
        $this->assertSame(4, config('store.cost_scale'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Ho_Chi_Minh', config('store.business_timezone'));
        $this->assertSame(1, config('store.warehouse_count'));
        $this->assertSame(1, config('store.shipments_per_order'));
    }

    public function test_pdf_dependency_can_render_without_external_resources(): void
    {
        $pdf = Pdf::loadHTML('<h1>Cena Store</h1><p>P00 foundation</p>')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
