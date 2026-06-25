<?php

namespace Tests\Feature\ExternalOrders;

use App\Enums\StorefrontPlatform;
use App\Jobs\PushSquarespaceFulfilment;
use App\Models\Artist;
use App\Models\Order;
use App\Models\StorefrontConnection;
use App\Services\StorefrontConnect\SquarespaceConnectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SquarespacePushbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_squarespace_pushback_refreshes_expired_access_token_before_posting_fulfillment(): void
    {
        Config::set('services.squarespace_connect.client_id', 'square-client');
        Config::set('services.squarespace_connect.client_secret', 'square-secret');

        Http::fake([
            'https://login.squarespace.com/api/1/login/oauth/provider/tokens' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'access_token_expires_at' => (string) now()->addMinutes(30)->timestamp,
            ]),
            'https://example.squarespace.com/api/commerce/orders/sq-order-1/fulfillments' => Http::response(['id' => 'fulfillment-1'], 201),
        ]);

        $connection = StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Squarespace,
                'store_url' => 'https://example.squarespace.com',
                'credentials' => ['access_token' => 'expired-access-token'],
                'refresh_token' => 'old-refresh-token',
                'token_expires_at' => now()->subMinute(),
            ]);

        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => StorefrontPlatform::Squarespace->value,
            'external_order_id' => 'sq-order-1',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-SQ-1',
        ]);

        (new PushSquarespaceFulfilment($order->id))->handle(app(SquarespaceConnectorService::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://login.squarespace.com/api/1/login/oauth/provider/tokens'
            && $request->method() === 'POST'
            && data_get($request->data(), 'grant_type') === 'refresh_token'
            && data_get($request->data(), 'refresh_token') === 'old-refresh-token');

        Http::assertSent(fn ($request) => $request->url() === 'https://example.squarespace.com/api/commerce/orders/sq-order-1/fulfillments'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer new-access-token')
            && data_get($request->data(), 'trackingNumber') === 'TRACK-SQ-1');

        $connection->refresh();

        $this->assertSame('new-access-token', data_get($connection->credentials, 'access_token'));
        $this->assertSame('new-refresh-token', $connection->refresh_token);
        $this->assertSame('succeeded', $order->fresh()->shipment_pushback_status);
    }
}
