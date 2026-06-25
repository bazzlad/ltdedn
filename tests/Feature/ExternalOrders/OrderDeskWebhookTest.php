<?php

namespace Tests\Feature\ExternalOrders;

use App\Enums\ExternalImportStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Enums\StorefrontPlatform;
use App\Jobs\PushOrderDeskFulfilment;
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

class OrderDeskWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_orderdesk_webhook_imports_paid_order_and_allocates_stock(): void
    {
        $connection = $this->orderDeskConnection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => true]);
        $sku = ProductSku::factory()->for($product)->create(['sku_code' => 'PRINT-OD-001', 'stock_on_hand' => 1]);
        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => null,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $payload = $this->orderDeskPayload('od-1001', 'PRINT-OD-001');
        $response = $this->postSignedOrderDeskPayload($connection, $payload);

        $response->assertOk()->assertJson(['status' => ExternalImportStatus::Processed->value]);

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('orderdesk', $order->source_platform);
        $this->assertSame('od-1001', $order->external_order_id);
        $this->assertSame('OD1001', $order->external_order_number);
        $this->assertDatabaseHas('product_skus', ['id' => $sku->id, 'stock_on_hand' => 0]);
        $this->assertDatabaseHas('product_editions', ['product_sku_id' => $sku->id, 'status' => ProductEditionStatus::Sold->value]);
    }

    public function test_orderdesk_webhook_rejects_invalid_store_id(): void
    {
        $connection = $this->orderDeskConnection();
        $payload = $this->orderDeskPayload('od-1002', 'PRINT-OD-001');
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', json_encode($payload['order'], JSON_THROW_ON_ERROR), 'od-api-key');

        $this->call('POST', "/api/webhooks/orderdesk/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORDER_DESK_STORE_ID' => 'wrong-store',
            'HTTP_X_ORDER_DESK_HASH' => $signature,
        ], $content)->assertUnauthorized();
    }

    public function test_orderdesk_webhook_rejects_invalid_signature(): void
    {
        $connection = $this->orderDeskConnection();

        $this->call('POST', "/api/webhooks/orderdesk/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORDER_DESK_STORE_ID' => 'od-store-1',
            'HTTP_X_ORDER_DESK_HASH' => 'bad-signature',
        ], json_encode($this->orderDeskPayload('od-1003', 'PRINT-OD-001'), JSON_THROW_ON_ERROR))->assertUnauthorized();
    }

    public function test_orderdesk_webhook_rejects_signature_over_wrapper_body(): void
    {
        $connection = $this->orderDeskConnection();
        $payload = $this->orderDeskPayload('od-1003-wrapper', 'PRINT-OD-001');
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $content, 'od-api-key');

        $this->call('POST', "/api/webhooks/orderdesk/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORDER_DESK_STORE_ID' => 'od-store-1',
            'HTTP_X_ORDER_DESK_HASH' => $signature,
        ], $content)->assertUnauthorized();
    }

    public function test_duplicate_orderdesk_webhook_does_not_create_duplicate_order(): void
    {
        $connection = $this->orderDeskConnection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-OD-001', 'stock_on_hand' => 5]);

        $payload = $this->orderDeskPayload('od-1004', 'TEE-OD-001');

        $this->postSignedOrderDeskPayload($connection, $payload)->assertOk();
        $this->postSignedOrderDeskPayload($connection, $payload)->assertOk();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, ExternalOrderImport::query()->count());
    }

    public function test_unknown_orderdesk_sku_creates_exception_order_and_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $connection = $this->orderDeskConnection();
        $payload = $this->orderDeskPayload('od-1005', 'MISSING-OD-SKU');

        $this->postSignedOrderDeskPayload($connection, $payload)
            ->assertOk()
            ->assertJson(['status' => ExternalImportStatus::Exception->value]);

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::Exception, $order->status);
        $this->assertStringContainsString('Unknown SKU', (string) $order->exception_reason);

        Notification::assertSentTo($admin, ExternalOrderExceptionNotification::class);
    }

    public function test_unpaid_orderdesk_order_is_recorded_as_ignored(): void
    {
        $connection = $this->orderDeskConnection();
        $payload = $this->orderDeskPayload('od-1006', 'TEE-OD-001', 'pending');

        $this->postSignedOrderDeskPayload($connection, $payload)
            ->assertOk()
            ->assertJson(['status' => ExternalImportStatus::Ignored->value]);

        $this->assertSame(0, Order::query()->count());
        $this->assertDatabaseHas('external_order_imports', ['status' => ExternalImportStatus::Ignored->value]);
    }

    public function test_marking_orderdesk_order_shipped_dispatches_pushback_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $connection = $this->orderDeskConnection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'orderdesk',
            'external_order_id' => 'od-1007',
            'customer_email' => null,
            'status' => OrderStatus::Paid,
            'shipped_at' => null,
        ]);

        $this->actingAs($admin)
            ->post("/admin/sales/{$order->id}/ship", [
                'carrier' => 'royal_mail',
                'tracking' => 'TRACK-OD-123',
            ])
            ->assertRedirect();

        Queue::assertPushed(PushOrderDeskFulfilment::class, fn (PushOrderDeskFulfilment $job) => $job->orderId === $order->id);
    }

    public function test_orderdesk_pushback_posts_shipment_to_orderdesk_api(): void
    {
        Config::set('services.orderdesk.api_url', 'https://app.orderdesk.test/api/v2');
        Http::fake([
            'https://app.orderdesk.test/api/v2/orders/od-1008/shipments' => Http::response(['success' => true], 201),
        ]);

        $connection = $this->orderDeskConnection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'orderdesk',
            'external_order_id' => 'od-1008',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-OD-456',
        ]);

        (new PushOrderDeskFulfilment($order->id))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://app.orderdesk.test/api/v2/orders/od-1008/shipments'
            && $request->hasHeader('ORDERDESK-STORE-ID', 'od-store-1')
            && $request->hasHeader('ORDERDESK-API-KEY', 'od-api-key')
            && data_get($request->data(), 'tracking_number') === 'TRACK-OD-456'
            && data_get($request->data(), 'carrier_code') === 'royal_mail');

        $this->assertSame('succeeded', $order->fresh()->shipment_pushback_status);
    }

    public function test_orderdesk_pushback_url_encodes_order_id_path_segment(): void
    {
        Config::set('services.orderdesk.api_url', 'https://app.orderdesk.test/api/v2');
        Http::fake([
            'https://app.orderdesk.test/api/v2/orders/*/shipments' => Http::response(['success' => true], 201),
        ]);

        $connection = $this->orderDeskConnection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'orderdesk',
            'external_order_id' => 'od/1008?x=1',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-OD-456',
        ]);

        (new PushOrderDeskFulfilment($order->id))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://app.orderdesk.test/api/v2/orders/od%2F1008%3Fx%3D1/shipments');
    }

    public function test_orderdesk_pushback_records_failure_response(): void
    {
        Config::set('services.orderdesk.api_url', 'https://app.orderdesk.test/api/v2');
        Http::fake([
            'https://app.orderdesk.test/api/v2/orders/od-1009/shipments' => Http::response(['error' => 'nope'], 422),
        ]);

        $connection = $this->orderDeskConnection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'orderdesk',
            'external_order_id' => 'od-1009',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-OD-789',
        ]);

        (new PushOrderDeskFulfilment($order->id))->handle();

        $order->refresh();

        $this->assertSame('failed', $order->shipment_pushback_status);
        $this->assertSame('Order Desk pushback failed: nope', $order->shipment_pushback_error);
    }

    public function test_orderdesk_pushback_records_json_error_response(): void
    {
        Config::set('services.orderdesk.api_url', 'https://app.orderdesk.test/api/v2');
        Http::fake([
            'https://app.orderdesk.test/api/v2/orders/od-1010/shipments' => Http::response([
                'status' => 'error',
                'message' => 'Shipment Not Added',
            ], 200),
        ]);

        $connection = $this->orderDeskConnection();
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'orderdesk',
            'external_order_id' => 'od-1010',
            'shipping_carrier' => 'royal_mail',
            'shipping_tracking_number' => 'TRACK-OD-1010',
        ]);

        (new PushOrderDeskFulfilment($order->id))->handle();

        $order->refresh();

        $this->assertSame('failed', $order->shipment_pushback_status);
        $this->assertSame('Order Desk pushback failed: Shipment Not Added', $order->shipment_pushback_error);
    }

    private function orderDeskConnection(): StorefrontConnection
    {
        return StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::OrderDesk,
                'external_shop_id' => 'od-store-1',
                'credentials' => ['api_key' => 'od-api-key'],
                'webhook_secret' => null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderDeskPayload(string $id, string $sku, string $paymentStatus = 'Approved'): array
    {
        return [
            'order' => [
                'id' => $id,
                'source_id' => 'OD1001',
                'email' => 'buyer@example.com',
                'currency' => 'GBP',
                'payment_status' => $paymentStatus,
                'subtotal' => '40.00',
                'shipping_total' => '5.00',
                'tax_total' => '0.00',
                'total' => '45.00',
                'folder_name' => 'New',
                'shipping' => [
                    'name' => 'Buyer Name',
                    'address1' => '1 Test Street',
                    'city' => 'London',
                    'postal_code' => 'N1 1AA',
                    'country' => 'GB',
                ],
                'items' => [
                    [
                        'id' => 'line-1',
                        'code' => $sku,
                        'name' => 'Limited Print',
                        'variation_name' => 'A2',
                        'quantity' => 1,
                        'price' => '40.00',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedOrderDeskPayload(StorefrontConnection $connection, array $payload): \Illuminate\Testing\TestResponse
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', json_encode($payload['order'], JSON_THROW_ON_ERROR), 'od-api-key');

        return $this->call('POST', "/api/webhooks/orderdesk/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ORDER_DESK_STORE_ID' => $connection->external_shop_id,
            'HTTP_X_ORDER_DESK_HASH' => $signature,
        ], $content);
    }
}
