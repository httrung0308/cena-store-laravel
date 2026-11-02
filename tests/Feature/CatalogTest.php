<?php

namespace Tests\Feature;

use App\Enums\GenderGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CatalogQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_catalog_applies_color_size_price_and_stock_to_same_variant(): void
    {
        [$product,$red] = $this->shoe('Same variant');
        $red->forceFill(['color_key' => 'red'])->save();
        $white = $this->color($product, 'white');
        $this->variant($red, '40', 100, 4, 4);
        $this->variant($white, '40.5', 900, 4, 0);
        foreach (['colors[]=red&size=40.5', 'colors[]=red&min_price=800', 'colors[]=red&in_stock=1', 'colors[]=white&max_price=500'] as $query) {
            $this->get('/shoes?'.$query)->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        }
        $this->get('/shoes?colors[]=white&size=40.5&min_price=800&in_stock=1')->assertOk()
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->items()[0]['product']['effective_price'] === 900 && ! $p->items()[0]['product']['sold_out']);
    }

    public function test_effective_price_precedence_and_sale_filter_exclude_high_override(): void
    {
        [$product,$color] = $this->shoe('Pricing', 1000, 600);
        $this->variant($color, '40', 1200, 1);
        $this->variant($color, '40.5', null, 1);
        $this->variant($color, '41', 500, 1);
        $this->get('/shoes?size=40&promotion=1')->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        $high = $this->get('/shoes?size=40')->assertViewHas('paginator', fn ($p) => $p->items()[0]['product']['effective_price'] === 1200);
        $high->assertDontSee('line-through');
        $this->get('/shoes?size=40.5&promotion=1')->assertViewHas('paginator', fn ($p) => $p->items()[0]['product']['effective_price'] === 600);
        $this->get('/shoes')->assertViewHas('paginator', fn ($p) => $p->items()[0]['product']['effective_price'] === 500)->assertSee('Từ 500');
        $product->forceFill(['promotion_price' => null])->save();
        $detail = app(CatalogQueryService::class)->detail($product->slug);
        $this->assertSame([1200, 1000, 500], array_column($detail['variants'], 'effective_price'));
    }

    #[DataProvider('hiddenCases')]
    public function test_nonpublic_products_are_not_listed_or_accessible_by_slug(string $case): void
    {
        [$product,$color] = $this->shoe('Hidden');
        $variant = $this->variant($color);
        match ($case) {
            'product-off' => $product->forceFill(['is_active' => false])->save(),
            'product-deleted' => $product->delete(),
            'color-off' => $color->forceFill(['is_active' => false])->save(),
            'color-deleted' => $color->delete(),
            'variant-off' => $variant->forceFill(['is_active' => false])->save(),
            'variant-deleted' => $variant->delete(),
            'thumbnail-missing' => $color->images()->delete(),
            'category-off' => $product->category->forceFill(['is_active' => false])->save(),
            'category-deleted' => $product->category->delete(),
            'parent-off' => $product->category->parent->forceFill(['is_active' => false])->save(),
            'parent-deleted' => $product->category->parent->delete(),
        };
        $this->get('/shoes')->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        $this->get('/shoes/'.$product->slug)->assertNotFound();
    }

    public static function hiddenCases(): array
    {
        return array_map(fn ($x) => [$x], ['product-off', 'product-deleted', 'color-off', 'color-deleted', 'variant-off', 'variant-deleted', 'thumbnail-missing', 'category-off', 'category-deleted', 'parent-off', 'parent-deleted']);
    }

    public function test_sold_out_remains_public_and_default_color_prefers_available_stock_then_first(): void
    {
        [$product,$first] = $this->shoe('Stock');
        $this->variant($first, '40', null, 3, 3);
        $second = $this->color($product, 'white');
        $v = $this->variant($second, '40.5', null, 2);
        $this->get('/shoes/'.$product->slug)->assertOk()->assertViewHas('initialColorId', $second->id);
        $v->forceFill(['reserved_qty' => 2])->save();
        $this->get('/shoes')->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->items()[0]['product']['sold_out'])->assertSee('Hết hàng');
        $this->get('/shoes/'.$product->slug)->assertOk()->assertViewHas('initialColorId', $first->id);
        $this->get('/shoes?in_stock=1')->assertViewHas('paginator', fn ($p) => $p->total() === 0);
    }

    public function test_gender_parent_category_and_canonical_half_size_filters(): void
    {
        [$men,$color] = $this->shoe('Men');
        $this->variant($color, '40.5');
        [$women,$wcolor] = $this->shoe('Women');
        $women->category->forceFill(['gender_group' => GenderGroup::Women])->save();
        $women->category->parent->forceFill(['gender_group' => GenderGroup::Women])->save();
        $this->variant($wcolor, '41');
        $this->get('/shoes?gender=men&category='.$men->category->parent->slug.'&size=40.5000')->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->items()[0]['product']['id'] === $men->id)->assertSee('value="40.5"', false);
        $this->get('/shoes?gender=women')->assertViewHas('paginator', fn ($p) => $p->items()[0]['product']['id'] === $women->id);
        $this->get('/shoes?category=missing')->assertViewHas('paginator', fn ($p) => $p->total() === 0);
    }

    public function test_search_literal_wildcards_and_style_code_are_not_sql_patterns(): void
    {
        [$p,$c] = $this->shoe('Sale 50%_OFF');
        $this->variant($c);
        [$other,$otherColor] = $this->shoe('Sale 5000OFF');
        $this->variant($otherColor);
        $this->get('/shoes?search='.urlencode('50%_'))->assertViewHas('paginator', fn ($q) => $q->total() === 1 && $q->items()[0]['product']['id'] === $p->id);
        $this->get('/shoes?search='.urlencode($c->style_color_code))->assertViewHas('paginator', fn ($q) => $q->total() === 1);
    }

    public function test_pagination_keeps_filters_and_all_sort_orders_have_stable_ids(): void
    {
        [$first,$c] = $this->shoe('Stable');
        $this->variant($c, '40.5', 100);
        $ids = [$first->id];
        for ($i = 0; $i < 10; $i++) {
            [$p,$color] = $this->shoe('Stable');
            $this->variant($color, '40.5', 100);
            $ids[] = $p->id;
        }
        $base = ['gender' => 'men', 'colors' => ['black'], 'size' => '40.5', 'min_price' => 100, 'max_price' => 100, 'promotion' => 1, 'sort' => 'price_asc'];
        $page = $this->get('/shoes?'.http_build_query($base))->assertOk();
        $page->assertViewHas('paginator', fn ($p) => $p->total() === 11 && $p->perPage() === 9 && array_column(array_column($p->items(), 'product'), 'id') === array_slice($ids, 0, 9));
        $page->assertSee('page=2', false)->assertSee('size=40.5', false)->assertSee('gender=men', false)->assertSee('max_price=100', false);
        $this->get('/shoes?'.http_build_query($base + ['page' => 2]))->assertViewHas('paginator', fn ($p) => array_column(array_column($p->items(), 'product'), 'id') === array_slice($ids, 9));
        foreach (['newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc'] as $sort) {
            Product::whereIn('id', $ids)->update(['created_at' => '2026-01-01 00:00:00']);
            $listing = app(CatalogQueryService::class)->listing(['sort' => $sort]);
            $this->assertSame(array_slice($ids, 0, 9), array_column(array_column($listing->items(), 'product'), 'id'));
        }
    }

    public function test_price_and_name_sorting_use_effective_price_before_pagination(): void
    {
        [$z,$zc] = $this->shoe('Zulu', 1000, 600);
        $this->variant($zc, '40', 200);
        [$a,$ac] = $this->shoe('Alpha', 1000, 100);
        $this->variant($ac, '40', 900);
        foreach (['price_asc' => [$z->id, $a->id], 'price_desc' => [$a->id, $z->id], 'name_asc' => [$a->id, $z->id], 'name_desc' => [$z->id, $a->id]] as $sort => $expected) {
            $this->assertSame($expected, array_column(array_column(app(CatalogQueryService::class)->listing(['sort' => $sort])->items(), 'product'), 'id'));
        }
    }

    public function test_nine_cards_use_constant_query_count_and_no_lazy_loading(): void
    {
        [$p,$c] = $this->shoe('First');
        $this->variant($c);
        $catalog = app(CatalogQueryService::class);
        DB::enableQueryLog();
        $catalog->listing([]);
        $one = count(DB::getQueryLog());
        DB::flushQueryLog();
        for ($i = 0; $i < 8; $i++) {
            [$p,$c] = $this->shoe('Card '.$i);
            $this->variant($c);
        }
        DB::flushQueryLog();
        Product::preventLazyLoading();
        try {
            $page = $catalog->listing([]);
            $nine = count(DB::getQueryLog());
        } finally {
            Product::preventLazyLoading(false);
            DB::disableQueryLog();
        }
        $this->assertCount(9, $page->items());
        $this->assertSame($one, $nine);
        $this->assertLessThanOrEqual(9, $nine);
    }

    public function test_detail_scopes_color_albums_whitelists_public_data_and_escapes_content(): void
    {
        [$product,$black] = $this->shoe('<script>alert(1)</script>');
        $v = $this->variant($black, '40.5', 2500, 4, 1);
        $product->forceFill(['description' => '<img src=x onerror=alert(1)>'])->save();
        $white = $this->color($product, 'white');
        $this->variant($white, '41', null, 0);
        [$other,$foreign] = $this->shoe('Other');
        $this->variant($foreign);
        $this->get('/shoes/'.$product->slug.'?style_color='.urlencode($foreign->style_color_code))->assertNotFound();
        $r = $this->get('/shoes/'.$product->slug.'?style_color='.urlencode($white->style_color_code))->assertOk()->assertViewHas('initialColorId', $white->id)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('data-add-to-cart', false)->assertSee('x-bind:data-variant-id', false);
        $data = app(CatalogQueryService::class)->detail($product->slug);
        $this->assertSame('40.5', $data['variants'][0]['size_value']);
        $this->assertSame(3, $data['variants'][0]['available_qty']);
        $this->assertSame(2500, $data['variants'][0]['effective_price']);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        foreach (['average_cost', 'quarantine_qty', 'reserved_qty', 'stock_qty', 'inventory_movements', 'storage_path', 'supplier', 'cost_amount'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertCount(2, $data['albums']);
        $this->assertArrayNotHasKey((string) $foreign->id, $data['albums']);
    }

    public function test_prices_beyond_javascript_safe_integers_are_formatted_on_the_server(): void
    {
        [$product, $color] = $this->shoe('Huge', 9_007_199_254_740_995, null);
        $this->variant($color, '40.5', 9_007_199_254_740_993, 1);

        $data = app(CatalogQueryService::class)->detail($product->slug);
        $this->assertSame('9.007.199.254.740.993 ₫', $data['variants'][0]['price_label']);
        $this->assertSame('9.007.199.254.740.995 ₫', $data['variants'][0]['base_price_label']);
        $this->get('/shoes/'.$product->slug)->assertOk()->assertSee('selected?.price_label', false)->assertDontSee('toLocaleString', false);
        $this->get('/shoes')->assertSee('Từ 9.007.199.254.740.993 ₫');
    }

    public function test_card_links_open_the_matching_colour_and_swatches_link_each_style_code(): void
    {
        [$product, $black] = $this->shoe('Swatches');
        $this->variant($black, '40', null, 1);
        $white = $this->color($product, 'white');
        $this->variant($white, '41', null, 1);

        $this->get('/shoes?colors[]=white')->assertOk()
            ->assertSee('href="'.e(route('shoes.show', ['slug' => $product->slug, 'style_color' => $white->style_color_code])).'"', false)
            ->assertDontSee('style_color='.urlencode($black->style_color_code), false);
        $this->get('/shoes')->assertSee('style_color='.urlencode($black->style_color_code), false)
            ->assertSee('style_color='.urlencode($white->style_color_code), false);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_validation_errors(string $query): void
    {
        $this->getJson('/shoes?'.$query)->assertUnprocessable();
    }

    public static function invalidFilters(): array
    {
        return array_map(fn ($q) => [$q], ['sort=cost_desc', 'gender=ADMIN', 'colors[]=unknown', 'colors=red', 'colors[]=red&colors[]=red', 'size=40.25', 'size[]=40', 'min_price=-1', 'min_price=1.5', 'max_price=9223372036854775808', 'min_price=100&max_price=99', 'page=0', 'page=abc', 'search[]=unsafe', 'promotion=true']);
    }

    public function test_unsafe_image_metadata_falls_back_to_real_asset_and_catalog_does_not_mutate_stock(): void
    {
        [$product,$color] = $this->shoe('Media');
        $v = $this->variant($color, '40', null, 2);
        $color->images()->update(['external_url' => 'https://secret:private@example.test/shoe.jpg']);
        $this->get('/shoes')->assertOk()->assertDontSee('secret:private', false)->assertSee('/legacy/img/men.jpg', false);
        $before = $v->fresh()->getAttributes();
        $this->get('/shoes/'.$product->slug)->assertOk();
        $this->assertSame($before, $v->fresh()->getAttributes());
        $this->assertFileExists(public_path('legacy/img/men.jpg'));
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('sales_ledger_entries', 0);
        $this->getJson('/admin/products')->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create())->getJson('/admin/products')->assertForbidden();
    }

    private function shoe(string $name, int $base = 1000, ?int $sale = 600): array
    {
        $root = Category::factory()->create(['gender_group' => GenderGroup::Men]);
        $child = Category::factory()->create(['parent_id' => $root->id, 'gender_group' => GenderGroup::Men]);
        $product = Product::factory()->create(['category_id' => $child->id, 'name' => $name, 'base_price' => $base, 'promotion_price' => $sale]);

        return [$product, $this->color($product, 'black')];
    }

    private function color(Product $product, string $key): ProductColor
    {
        $color = ProductColor::factory()->create(['product_id' => $product->id, 'color_key' => $key, 'color_name' => ucfirst($key)]);
        ProductImage::factory()->thumbnail()->create(['product_color_id' => $color->id, 'source_type' => ProductImageSourceType::ExternalUrl, 'storage_path' => null, 'external_url' => 'https://example.test/'.$key.'.jpg']);

        return $color;
    }

    private function variant(ProductColor $color, string $size = '40', ?int $price = null, int $stock = 0, int $reserved = 0): ProductVariant
    {
        return ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => $size, 'size_numeric' => $size, 'price_override' => $price, 'stock_qty' => $stock, 'reserved_qty' => $reserved, 'average_cost' => '999999.1234']);
    }
}
