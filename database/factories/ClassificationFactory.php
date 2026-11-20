<?php

namespace Database\Factories;

use App\Enums\ClassificationGroup;
use App\Models\Classification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Classification> */
class ClassificationFactory extends Factory
{
    protected $model = Classification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => 'tag-'.Str::uuid(),
            'classification_group' => ClassificationGroup::Silhouette,
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function silhouette(): static
    {
        return $this->state(fn () => ['classification_group' => ClassificationGroup::Silhouette]);
    }

    public function activity(): static
    {
        return $this->state(fn () => ['classification_group' => ClassificationGroup::Activity]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
