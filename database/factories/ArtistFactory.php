<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Create an artist with a specific owner.
     */
    public function ownedBy(User $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $owner->id,
        ]);
    }

    /**
     * Create an artist with a specific name and owner.
     */
    public function withNameAndOwner(string $name, User $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'owner_id' => $owner->id,
        ]);
    }
}
