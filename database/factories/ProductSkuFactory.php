<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductSkuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku_code' => strtoupper(fake()->bothify('SKU-###??')),
            'price_amount' => fake()->numberBetween(1000, 10000),
            'compare_at_amount' => null,
            'currency' => 'gbp',
            'stock_on_hand' => fake()->numberBetween(0, 20),
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => [
                'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            ],
        ];
    }
}
