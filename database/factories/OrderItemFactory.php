<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitAmount = fake()->numberBetween(1000, 10000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->for(Artist::factory()),
            'product_name' => fake()->sentence(2, false),
            'product_slug' => fake()->slug(),
            'sku_code_snapshot' => strtoupper(fake()->bothify('SKU-####')),
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'line_total_amount' => $unitAmount * $quantity,
        ];
    }
}
