<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Enums\GenderGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StorefrontClassificationFilterTest extends TestCase
{
    use DatabaseTransactions;

    private Category $menCat;

    private Category $womenCat;

    private Classification $sneaker;

    private Classification $boots;

    private Classification $running;

    private Classification $lifestyle;

    private Classification $inactiveTag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menCat = Category::factory()->create(['gender_group' => GenderGroup::Men, 'is_active' => true]);
        $this->womenCat = Category::factory()->create(['gender_group' => GenderGroup::Women, 'is_active' => true]);

        $this->sneaker = Classification::query()->create([
            'name' => 'Sneaker',
            'slug' => 'sneaker-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->boots = Classification::query()->create([
            'name' => 'Boots',
            'slug' => 'boots-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->running = Classification::query()->create([
            'name' => 'Chạy bộ',
            'slug' => 'running-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->lifestyle = Classification::query()->create([
            'name' => 'Thời trang',
            'slug' => 'lifestyle-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->inactiveTag = Classification::query()->create([
            'name' => 'Tag Ẩn',
            'slug' => 'tag-an-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'is_active' => false,
            'sort_order' => 99,
        ]);
    }

    /** Helper to create a public, sellable shoe with thumbnail and stock */
    private function createPublicShoe(Category $category, string $name, array $classificationIds = []): Product
    {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name.'-'.uniqid())->slug(),
            'base_price' => 1_000_000,
            'is_active' => true,
        ]);

        $color = $product->colors()->create([
            'style_color_code' => 'STYLE-'.uniqid(),
            'color_name' => 'Trắng',
            'color_key' => 'white',
            'primary_hex' => '#FFFFFF',
            'is_active' => true,
            'display_order' => 1,
        ]);

        ProductImage::factory()->thumbnail()->create([
            'product_color_id' => $color->id,
            'source_type' => ProductImageSourceType::ExternalUrl,
            'external_url' => 'https://example.com/shoe.jpg',
            'storage_path' => null,
            'alt_text' => $name,
        ]);

        ProductVariant::factory()->stocked(10)->create([
            'product_color_id' => $color->id,
            'size_value' => '40',
            'size_numeric' => '40.0',
        ]);

        if (! empty($classificationIds)) {
            $product->classifications()->attach($classificationIds);
        }

        return $product;
    }

    public function test_can_filter_by_single_silhouette(): void
    {
        $shoeSneaker = $this->createPublicShoe($this->menCat, 'Men Sneaker', [$this->sneaker->id]);
        $shoeBoots = $this->createPublicShoe($this->menCat, 'Men Boots', [$this->boots->id]);

        $response = $this->get('/shoes?silhouette[]='.$this->sneaker->slug);
        $response->assertOk();
        $response->assertSee($shoeSneaker->name);
        $response->assertDontSee($shoeBoots->name);
    }

    public function test_filter_by_multiple_silhouettes_uses_or_logic_within_group(): void
    {
        $shoeSneaker = $this->createPublicShoe($this->menCat, 'Men Sneaker Item', [$this->sneaker->id]);
        $shoeBoots = $this->createPublicShoe($this->menCat, 'Men Boots Item', [$this->boots->id]);
        $shoeOther = $this->createPublicShoe($this->menCat, 'Men Other Item', []);

        $url = '/shoes?silhouette[]='.$this->sneaker->slug.'&silhouette[]='.$this->boots->slug;
        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee($shoeSneaker->name);
        $response->assertSee($shoeBoots->name);
        $response->assertDontSee($shoeOther->name);
    }

    public function test_combined_and_logic_across_groups(): void
    {
        // Shoe 1: Men + Sneaker + Running
        $shoe1 = $this->createPublicShoe($this->menCat, 'Shoe Men Sneaker Running', [$this->sneaker->id, $this->running->id]);
        // Shoe 2: Men + Sneaker + Lifestyle
        $shoe2 = $this->createPublicShoe($this->menCat, 'Shoe Men Sneaker Lifestyle', [$this->sneaker->id, $this->lifestyle->id]);
        // Shoe 3: Women + Sneaker + Running
        $shoe3 = $this->createPublicShoe($this->womenCat, 'Shoe Women Sneaker Running', [$this->sneaker->id, $this->running->id]);

        // Filter: gender=men AND silhouette=sneaker AND activity=running
        $url = '/shoes?gender=men&silhouette[]='.$this->sneaker->slug.'&activity[]='.$this->running->slug;
        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee($shoe1->name);
        $response->assertDontSee($shoe2->name); // missing running
        $response->assertDontSee($shoe3->name); // wrong gender
    }

    public function test_inactive_classification_is_rejected_in_storefront_filter(): void
    {
        // Submitting an inactive slug must fail validation (unprocessable for JSON)
        $this->getJson('/shoes?silhouette[]='.$this->inactiveTag->slug)->assertUnprocessable();
    }

    public function test_filter_options_excludes_inactive_classifications(): void
    {
        $response = $this->get('/shoes');
        $response->assertOk();
        $response->assertSee($this->sneaker->name);
        $response->assertDontSee($this->inactiveTag->name);
    }

    public function test_unclassified_products_show_in_general_list_but_hidden_when_tag_filtered(): void
    {
        $unclassified = $this->createPublicShoe($this->menCat, 'Unclassified Shoe', []);
        $classified = $this->createPublicShoe($this->menCat, 'Classified Shoe', [$this->sneaker->id]);

        // General list -> both visible
        $general = $this->get('/shoes?gender=men');
        $general->assertOk();
        $general->assertSee($unclassified->name);
        $general->assertSee($classified->name);

        // Filter by silhouette -> unclassified is hidden
        $filtered = $this->get('/shoes?gender=men&silhouette[]='.$this->sneaker->slug);
        $filtered->assertOk();
        $filtered->assertSee($classified->name);
        $filtered->assertDontSee($unclassified->name);
    }
}
