<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductEdition>
 */
class ProductEditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['available', 'sold', 'redeemed', 'pending_transfer', 'invalidated'];

        return [
            'number' => $this->faker->unique()->numberBetween(1, 100000),
            'status' => fake()->randomElement($statuses),
            'owner_id' => null, // Don't randomly assign owners, let tests control this
        ];
    }
}
