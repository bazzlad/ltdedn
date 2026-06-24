<?php

namespace Database\Factories;

use App\Enums\StorefrontPlatform;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StorefrontConnection>
 */
class StorefrontConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'platform' => fake()->randomElement(StorefrontPlatform::cases()),
            'artist_id' => Artist::factory(),
            'name' => fake()->unique()->company(),
            'store_url' => fake()->url(),
            'credentials' => ['access_token' => Str::random(32)],
            'webhook_secret' => Str::random(32),
            'status' => 'active',
            'last_sync_meta' => [],
        ];
    }
}
