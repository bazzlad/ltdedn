<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(2, false),
            'description' => fake()->optional()->paragraph(),
            'cover_image' => fake()->optional()->imageUrl(400, 400, 'music'),
            'sell_through_ltdedn' => fake()->boolean(30),
            'is_limited' => fake()->boolean(80),
            'edition_size' => fake()->optional(70)->numberBetween(10, 500),
            'base_price' => fake()->optional()->randomFloat(2, 5, 100),
            'is_public' => fake()->boolean(20),
        ];
    }
}
