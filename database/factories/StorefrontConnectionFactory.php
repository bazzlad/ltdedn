<?php

namespace Database\Factories;

use App\Enums\StorefrontConnectionStatus;
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
            'external_shop_id' => fake()->uuid(),
            'external_shop_domain' => fake()->unique()->domainName(),
            'credentials' => ['access_token' => Str::random(32)],
            'oauth_scopes' => ['read_orders'],
            'webhook_secret' => Str::random(32),
            'status' => 'active',
            'connection_status' => StorefrontConnectionStatus::Ready,
            'last_sync_meta' => [],
        ];
    }
}
