<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SHIP-###??'),
            'label' => fake()->randomElement(['Standard', 'Express', 'International']),
            'currency' => 'gbp',
            'amount_minor' => fake()->numberBetween(0, 2500),
            'country_codes' => null,
            'is_active' => true,
            'sort_order' => 0,
            'stripe_rate_id' => null,
        ];
    }
}
