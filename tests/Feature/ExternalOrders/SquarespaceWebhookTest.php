<?php

namespace Tests\Feature\ExternalOrders;

use App\Enums\StorefrontPlatform;
use App\Jobs\ProcessExternalOrderWebhook;
use App\Models\Artist;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\StorefrontConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SquarespaceWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_squarespace_webhook_imports_paid_order(): void
    {
        $connection = StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Squarespace,
                'webhook_secret' => bin2hex('square-secret'),
            ]);

        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'POSTER-001', 'stock_on_hand' => 3]);

        $payload = [
            'order' => [
                'id' => 'sq-1001',
                'orderNumber' => 'SQ1001',
                'customerEmail' => 'buyer@example.com',
                'currency' => 'GBP',
                'paymentStatus' => 'paid',
                'subtotal' => '30.00',
                'grandTotal' => '30.00',
                'shippingAddress' => [
                    'fullName' => 'Buyer Name',
                    'address1' => '1 Test Street',
                    'city' => 'London',
                    'postalCode' => 'N1 1AA',
                    'countryCode' => 'GB',
                ],
                'lineItems' => [
                    [
                        'id' => 'line-1',
                        'sku' => 'POSTER-001',
                        'productName' => 'Poster',
                        'quantity' => 1,
                        'unitPricePaid' => '30.00',
                    ],
                ],
            ],
        ];

        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $secretKey = hex2bin($connection->webhook_secret);
        $signature = hash_hmac('sha256', $content, $secretKey);

        $this->call('POST', "/api/webhooks/squarespace/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SQUARESPACE_SIGNATURE' => $signature,
        ], $content)
            ->assertOk()
            ->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('orders', [
            'source_platform' => 'squarespace',
            'external_order_id' => 'sq-1001',
        ]);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_valid_squarespace_webhook_dispatches_processing_job(): void
    {
        Queue::fake();

        $connection = StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Squarespace,
                'webhook_secret' => 'square-secret',
            ]);

        $payload = $this->payload();
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $content, $connection->webhook_secret);

        $this->call('POST', "/api/webhooks/squarespace/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SQUARESPACE_SIGNATURE' => $signature,
            'HTTP_X_SQUARESPACE_WEBHOOK_ID' => 'sq-delivery-1',
        ], $content)
            ->assertOk()
            ->assertJson(['status' => 'queued']);

        Queue::assertPushed(ProcessExternalOrderWebhook::class, function (ProcessExternalOrderWebhook $job) use ($connection, $payload) {
            return $job->connectionId === $connection->id
                && $job->platform === StorefrontPlatform::Squarespace->value
                && $job->payload === $payload
                && $job->deliveryId === 'sq-delivery-1';
        });

        $this->assertSame(0, Order::query()->count());
    }

    public function test_invalid_squarespace_signature_is_rejected(): void
    {
        Queue::fake();

        $connection = StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Squarespace,
                'webhook_secret' => 'square-secret',
            ]);

        $this->call('POST', "/api/webhooks/squarespace/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SQUARESPACE_SIGNATURE' => 'bad-signature',
        ], json_encode($this->payload(), JSON_THROW_ON_ERROR))->assertUnauthorized();

        Queue::assertNotPushed(ProcessExternalOrderWebhook::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'order' => [
                'id' => 'sq-1001',
                'orderNumber' => 'SQ1001',
                'customerEmail' => 'buyer@example.com',
                'currency' => 'GBP',
                'paymentStatus' => 'paid',
                'subtotal' => '30.00',
                'grandTotal' => '30.00',
                'shippingAddress' => [
                    'fullName' => 'Buyer Name',
                    'address1' => '1 Test Street',
                    'city' => 'London',
                    'postalCode' => 'N1 1AA',
                    'countryCode' => 'GB',
                ],
                'lineItems' => [
                    [
                        'id' => 'line-1',
                        'sku' => 'POSTER-001',
                        'productName' => 'Poster',
                        'quantity' => 1,
                        'unitPricePaid' => '30.00',
                    ],
                ],
            ],
        ];
    }
}
