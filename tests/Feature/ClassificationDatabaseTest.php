<?php

namespace Tests\Feature;

use App\Enums\ClassificationGroup;
use App\Models\Classification;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClassificationDatabaseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_create_classification_and_link_to_product(): void
    {
        $product = Product::factory()->create();

        $classification = Classification::query()->create([
            'name' => 'Sneaker',
            'slug' => 'sneaker-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product->classifications()->attach($classification->id);

        $this->assertTrue($product->classifications->contains('id', $classification->id));
        $this->assertTrue($classification->products->contains('id', $product->id));
    }

    public function test_slug_is_unique_within_same_classification_group(): void
    {
        Classification::query()->create([
            'name' => 'Running A',
            'slug' => 'running-test-slug',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 1,
        ]);

        // Same group, same slug -> should fail unique
        $this->expectException(QueryException::class);
        Classification::query()->create([
            'name' => 'Running B',
            'slug' => 'running-test-slug',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 2,
        ]);
    }

    public function test_same_slug_allowed_in_different_classification_groups(): void
    {
        $uniqueSlug = 'cross-group-'.uniqid();

        $c1 = Classification::query()->create([
            'name' => 'Cross Silhouette',
            'slug' => $uniqueSlug,
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 1,
        ]);

        $c2 = Classification::query()->create([
            'name' => 'Cross Activity',
            'slug' => $uniqueSlug,
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 2,
        ]);

        $this->assertSame($uniqueSlug, $c1->slug);
        $this->assertSame($uniqueSlug, $c2->slug);
    }

    public function test_cannot_delete_classification_when_used_by_product(): void
    {
        $product = Product::factory()->create();

        $classification = Classification::query()->create([
            'name' => 'Restrict Test',
            'slug' => 'restrict-'.uniqid(),
            'classification_group' => ClassificationGroup::Silhouette,
        ]);

        $product->classifications()->attach($classification->id);

        $this->expectException(QueryException::class);
        $classification->delete();
    }

    public function test_active_classifications_relation_only_returns_active(): void
    {
        $product = Product::factory()->create();

        $active = Classification::query()->create([
            'name' => 'Active Tag',
            'slug' => 'active-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => true,
        ]);

        $inactive = Classification::query()->create([
            'name' => 'Inactive Tag',
            'slug' => 'inactive-'.uniqid(),
            'classification_group' => ClassificationGroup::Activity,
            'is_active' => false,
        ]);

        $product->classifications()->attach([$active->id, $inactive->id]);

        $this->assertCount(2, $product->classifications);
        $this->assertCount(1, $product->activeClassifications);
        $this->assertSame($active->id, $product->activeClassifications->first()->id);
    }
}
