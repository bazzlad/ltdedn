<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'product_sku_id' => null,
            'quantity' => 1,
            'unit_amount_snapshot' => fake()->numberBetween(1000, 10000),
        ];
    }
}
