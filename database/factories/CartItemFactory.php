<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'variant_id' => ProductVariant::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
