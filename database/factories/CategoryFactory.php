<?php

namespace Database\Factories;

use App\Enums\GenderGroup;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => 'category-'.Str::uuid(),
            'gender_group' => GenderGroup::Men,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
