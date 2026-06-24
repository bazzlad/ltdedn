<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductSku>
 */
class ProductSkuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->for(Artist::factory()),
            'sku_code' => strtoupper(fake()->bothify('LTD-####-??')),
            'price_amount' => fake()->numberBetween(1000, 25000),
            'currency' => 'gbp',
            'stock_on_hand' => fake()->numberBetween(1, 50),
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => [],
        ];
    }
}
