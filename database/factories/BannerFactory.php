<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Banner> */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'subtitle' => fake()->sentence(8),
            'image_path' => 'banners/fake-'.fake()->uuid().'.jpg',
            'mobile_image_path' => null,
            'link_url' => '/shoes',
            'cta_text' => 'Khám phá ngay',
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
