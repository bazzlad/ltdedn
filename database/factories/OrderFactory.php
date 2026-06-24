<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 50000);
        $shipping = fake()->numberBetween(0, 1500);

        return [
            'source_platform' => 'manual',
            'status' => OrderStatus::Paid,
            'currency' => 'gbp',
            'subtotal_amount' => $subtotal,
            'shipping_amount' => $shipping,
            'tax_amount' => 0,
            'total_amount' => $subtotal + $shipping,
            'customer_email' => fake()->safeEmail(),
            'shipping_name' => fake()->name(),
            'shipping_line1' => fake()->streetAddress(),
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => 'GB',
            'paid_at' => now(),
        ];
    }
}
