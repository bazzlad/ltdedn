<?php

namespace Database\Factories;

use App\Enums\ExternalImportStatus;
use App\Enums\StorefrontPlatform;
use App\Models\StorefrontConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalOrderImport>
 */
class ExternalOrderImportFactory extends Factory
{
    public function definition(): array
    {
        $payload = ['id' => (string) fake()->randomNumber(6)];

        return [
            'storefront_connection_id' => StorefrontConnection::factory(),
            'platform' => StorefrontPlatform::Shopify,
            'external_order_id' => (string) $payload['id'],
            'delivery_id' => fake()->uuid(),
            'payload_hash' => hash('sha256', json_encode($payload)),
            'raw_payload' => $payload,
            'status' => ExternalImportStatus::Pending,
        ];
    }
}
