<?php

namespace Tests\Feature\ExternalOrders;

use App\Enums\ExternalImportStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Enums\StorefrontPlatform;
use App\Jobs\PushPipe17Fulfilment;
use App\Models\Artist;
use App\Models\ExternalOrderImport;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\StorefrontConnection;
use App\Models\User;
use App\Notifications\ExternalOrderExceptionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Pipe17IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pipe17.allowed_hosts', ['api.pipe17.test']);
    }

    public function test_pipe17_pull_imports_ready_shipping_request_and_allocates_stock(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1001', 'PRINT-P17-001')],
            ]),
            'https://api.pipe17.test/api/v3/shipping-requests/sr-1001' => Http::response(['ok' => true]),
        ]);

        $connection = $this->pipe17Connection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => true]);
        $sku = ProductSku::factory()->for($product)->create(['sku_code' => 'PRINT-P17-001', 'stock_on_hand' => 1]);
        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => null,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertSuccessful();

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('pipe17', $order->source_platform);
        $this->assertSame('sr-1001', $order->external_order_id);
        $this->assertSame('SHOP-1001', $order->external_order_number);
        $this->assertSame('shopify-order-1001', data_get($order->meta, 'pipe17_ext_order_id'));
        $this->assertDatabaseHas('product_skus', ['id' => $sku->id, 'stock_on_hand' => 0]);
        $this->assertDatabaseHas('product_editions', ['product_sku_id' => $sku->id, 'status' => ProductEditionStatus::Sold->value]);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.pipe17.test/api/v3/shipping-requests')
            && $request->hasHeader('X-Pipe17-Key', 'pipe17-api-key'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.pipe17.test/api/v3/shipping-requests/sr-1001'
            && data_get($request->data(), 'status') === 'sentToFulfillment'
            && data_get($request->data(), 'extReferenceId') === 'ltdedn-order-'.$order->id);
    }

    public function test_duplicate_pipe17_pull_does_not_create_duplicate_order(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1004', 'TEE-P17-001')],
            ]),
            'https://api.pipe17.test/api/v3/shipping-requests/sr-1004' => Http::response(['ok' => true]),
        ]);

        $connection = $this->pipe17Connection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-P17-001', 'stock_on_hand' => 5]);

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertSuccessful();
        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertSuccessful();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, ExternalOrderImport::query()->where('status', ExternalImportStatus::Processed)->count());
    }

    public function test_unknown_pipe17_sku_creates_exception_order_and_notifies_admins(): void
    {
        Notification::fake();
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests/sr-1005' => Http::response(['ok' => true]),
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1005', 'MISSING-P17-SKU')],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $connection = $this->pipe17Connection();

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertSuccessful();

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::Exception, $order->status);
        $this->assertStringContainsString('Unknown SKU', (string) $order->exception_reason);
        $this->assertDatabaseHas('external_order_imports', ['status' => ExternalImportStatus::Exception->value]);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.pipe17.test/api/v3/shipping-requests/sr-1005'
            && data_get($request->data(), 'status') === 'sentToFulfillment'
            && data_get($request->data(), 'extReferenceId') === 'ltdedn-order-'.$order->id);
        $this->assertArrayHasKey('pipe17_last_updated_since', $connection->fresh()->last_sync_meta ?? []);
        Notification::assertSentTo($admin, ExternalOrderExceptionNotification::class);
    }

    public function test_cancelled_pipe17_shipping_request_is_recorded_as_ignored(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1006', 'TEE-P17-001', 'canceled')],
            ]),
        ]);

        $connection = $this->pipe17Connection();

        $this->artisan('pipe17:pull-shipping-requests', [
            'connection' => $connection->id,
            '--status' => ['canceled'],
        ])->assertSuccessful();

        $this->assertSame(0, Order::query()->count());
        $this->assertDatabaseHas('external_order_imports', ['status' => ExternalImportStatus::Ignored->value]);
    }

    public function test_malformed_pipe17_shipping_request_is_skipped(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [['status' => 'readyForFulfillment', 'lineItems' => []]],
            ]),
        ]);

        $connection = $this->pipe17Connection();

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertFailed();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, ExternalOrderImport::query()->count());
        $this->assertArrayNotHasKey('pipe17_last_updated_since', $connection->fresh()->last_sync_meta ?? []);
    }

    public function test_pipe17_cursor_does_not_advance_when_acknowledgement_fails(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests/sr-1011' => Http::response(['error' => 'temporary'], 500),
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1011', 'TEE-P17-001')],
            ]),
        ]);

        $connection = $this->pipe17Connection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-P17-001', 'stock_on_hand' => 5]);

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertFailed();

        $this->assertSame(1, Order::query()->count());
        $this->assertArrayNotHasKey('pipe17_last_updated_since', $connection->fresh()->last_sync_meta ?? []);
    }

    public function test_pipe17_pull_handles_paginated_responses(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests*cursor=page-2*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1013', 'TEE-P17-001')],
            ]),
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1012', 'TEE-P17-001')],
                'nextCursor' => 'page-2',
            ]),
            'https://api.pipe17.test/api/v3/shipping-requests/sr-1012' => Http::response(['ok' => true]),
            'https://api.pipe17.test/api/v3/shipping-requests/sr-1013' => Http::response(['ok' => true]),
        ]);

        $connection = $this->pipe17Connection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-P17-001', 'stock_on_hand' => 5]);

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertSuccessful();

        $this->assertSame(2, Order::query()->count());
    }

    public function test_pipe17_pull_fails_when_pagination_exceeds_page_cap(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Config::set('services.pipe17.max_pages', 1);
        Http::fake([
            'https://api.pipe17.test/api/v3/shipping-requests*' => Http::response([
                'shippingRequests' => [$this->shippingRequestPayload('sr-1014', 'TEE-P17-001')],
                'nextCursor' => 'page-2',
            ]),
        ]);

        $connection = $this->pipe17Connection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-P17-001', 'stock_on_hand' => 5]);

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertFailed();

        $this->assertSame(0, Order::query()->count());
        $this->assertArrayNotHasKey('pipe17_last_updated_since', $connection->fresh()->last_sync_meta ?? []);
    }

    public function test_marking_pipe17_order_shipped_dispatches_pushback_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $connection = $this->pipe17Connection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'pipe17',
            'external_order_id' => 'sr-1007',
            'customer_email' => null,
            'status' => OrderStatus::Paid,
            'shipped_at' => null,
        ]);

        $this->actingAs($admin)
            ->post("/admin/sales/{$order->id}/ship", [
                'carrier' => 'royal_mail',
                'tracking' => 'TRACK-P17-123',
            ])
            ->assertRedirect();

        Queue::assertPushed(PushPipe17Fulfilment::class, fn (PushPipe17Fulfilment $job) => $job->orderId === $order->id);
    }

    public function test_pipe17_pushback_posts_fulfillment_to_pipe17_api(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/fulfillments' => Http::response(['id' => 'fulfillment-1'], 201),
        ]);

        $connection = $this->pipe17Connection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        $sku = ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-P17-001', 'stock_on_hand' => 5]);
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'pipe17',
            'external_order_id' => 'sr-1008',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-P17-456',
            'shipped_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'product_name' => 'Tee',
            'sku_code_snapshot' => 'TEE-P17-001',
            'quantity' => 2,
            'unit_amount' => 4000,
            'line_total_amount' => 8000,
        ]);

        (new PushPipe17Fulfilment($order->id))->handle(app(\App\Services\Pipe17\Pipe17Client::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.pipe17.test/api/v3/fulfillments'
            && $request->hasHeader('X-Pipe17-Key', 'pipe17-api-key')
            && data_get($request->data(), 'shippingRequestId') === 'sr-1008'
            && data_get($request->data(), 'tracking.number') === 'TRACK-P17-456'
            && data_get($request->data(), 'lineItems.0.sku') === 'TEE-P17-001'
            && data_get($request->data(), 'lineItems.0.quantity') === 2);

        $this->assertSame('succeeded', $order->fresh()->shipment_pushback_status);
    }

    public function test_pipe17_pushback_records_failure_response(): void
    {
        Config::set('services.pipe17.api_url', 'https://api.pipe17.test/api/v3');
        Http::fake([
            'https://api.pipe17.test/api/v3/fulfillments' => Http::response(['error' => 'nope'], 422),
        ]);

        $connection = $this->pipe17Connection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'pipe17',
            'external_order_id' => 'sr-1009',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-P17-789',
        ]);

        (new PushPipe17Fulfilment($order->id))->handle(app(\App\Services\Pipe17\Pipe17Client::class));

        $order->refresh();

        $this->assertSame('failed', $order->shipment_pushback_status);
        $this->assertStringContainsString('Pipe17 pushback failed:', (string) $order->shipment_pushback_error);
    }

    public function test_pipe17_api_url_must_be_https_and_allowed(): void
    {
        Config::set('services.pipe17.api_url', 'http://api.pipe17.test/api/v3');
        Http::fake();

        $connection = $this->pipe17Connection();

        $this->artisan('pipe17:pull-shipping-requests', ['connection' => $connection->id])->assertFailed();

        Http::assertNothingSent();
    }

    private function pipe17Connection(): StorefrontConnection
    {
        return StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Pipe17,
                'external_shop_id' => 'pipe17-location-1',
                'credentials' => ['api_key' => 'pipe17-api-key'],
                'webhook_secret' => null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingRequestPayload(string $id, string $sku, string $status = 'readyForFulfillment'): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'orderId' => 'pipe17-order-1001',
            'extOrderId' => 'shopify-order-1001',
            'orderNumber' => 'SHOP-1001',
            'orderSource' => 'SHOPIFY',
            'email' => 'buyer@example.com',
            'currency' => 'GBP',
            'subTotalPrice' => 40.00,
            'shippingPrice' => 5.00,
            'orderTax' => 0.00,
            'totalPrice' => 45.00,
            'shippingAddress' => [
                'firstName' => 'Buyer',
                'lastName' => 'Name',
                'address1' => '1 Test Street',
                'city' => 'London',
                'zipCodeOrPostalCode' => 'N1 1AA',
                'country' => 'GB',
            ],
            'lineItems' => [
                [
                    'id' => 'line-1',
                    'sku' => $sku,
                    'name' => 'Limited Print',
                    'variantTitle' => 'A2',
                    'quantity' => 1,
                    'unitPrice' => 40.00,
                ],
            ],
        ];
    }
}
