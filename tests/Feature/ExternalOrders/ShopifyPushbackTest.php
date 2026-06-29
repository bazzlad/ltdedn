<?php

namespace Tests\Feature\ExternalOrders;

use App\Enums\StorefrontPlatform;
use App\Jobs\PushShopifyFulfilment;
use App\Models\Artist;
use App\Models\Order;
use App\Models\StorefrontConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyPushbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_pushback_fetches_fulfillment_order_id_before_posting_fulfillment(): void
    {
        Config::set('services.shopify_connect.api_version', '2025-10');

        Http::fake([
            'https://joe.myshopify.com/admin/api/2025-10/orders/6020179886232/fulfillment_orders.json' => Http::response([
                'fulfillment_orders' => [
                    ['id' => 7001, 'status' => 'open', 'request_status' => 'unsubmitted'],
                ],
            ]),
            'https://joe.myshopify.com/admin/api/2025-10/fulfillments.json' => Http::response(['fulfillment' => ['id' => 8001]], 201),
        ]);

        $connection = StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Shopify,
                'store_url' => 'https://joe.myshopify.com',
                'credentials' => ['access_token' => 'shpat_test_token'],
            ]);

        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => StorefrontPlatform::Shopify->value,
            'external_order_id' => '6020179886232',
            'shipping_carrier' => 'Post Office',
            'shipping_tracking_number' => 'TRACK-SHOPIFY-1',
        ]);

        (new PushShopifyFulfilment($order->id))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://joe.myshopify.com/admin/api/2025-10/orders/6020179886232/fulfillment_orders.json'
            && $request->method() === 'GET'
            && $request->hasHeader('X-Shopify-Access-Token', 'shpat_test_token'));

        Http::assertSent(fn ($request) => $request->url() === 'https://joe.myshopify.com/admin/api/2025-10/fulfillments.json'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Shopify-Access-Token', 'shpat_test_token')
            && data_get($request->data(), 'fulfillment.line_items_by_fulfillment_order.0.fulfillment_order_id') === '7001'
            && data_get($request->data(), 'fulfillment.tracking_info.number') === 'TRACK-SHOPIFY-1');

        $order->refresh();

        $this->assertSame('succeeded', $order->shipment_pushback_status);
        $this->assertSame('7001', data_get($order->meta, 'shopify_fulfillment_order_id'));
        $this->assertSame(['7001'], data_get($order->meta, 'shopify_fulfillment_order_ids'));
    }

    public function test_shopify_pushback_records_scope_failure_when_fulfillment_order_lookup_is_forbidden(): void
    {
        Config::set('services.shopify_connect.api_version', '2025-10');

        Http::fake([
            'https://joe.myshopify.com/admin/api/2025-10/orders/6020179886232/fulfillment_orders.json' => Http::response([
                'errors' => 'The api_client does not have the required permission(s).',
            ], 403),
        ]);

        $connection = StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Shopify,
                'store_url' => 'https://joe.myshopify.com',
                'credentials' => ['access_token' => 'shpat_test_token'],
            ]);

        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => StorefrontPlatform::Shopify->value,
            'external_order_id' => '6020179886232',
            'shipping_carrier' => 'Post Office',
            'shipping_tracking_number' => 'TRACK-SHOPIFY-1',
        ]);

        (new PushShopifyFulfilment($order->id))->handle();

        Http::assertSentCount(1);

        $order->refresh();

        $this->assertSame('failed', $order->shipment_pushback_status);
        $this->assertStringContainsString('Reinstall the Shopify app', (string) $order->shipment_pushback_error);
    }
}
