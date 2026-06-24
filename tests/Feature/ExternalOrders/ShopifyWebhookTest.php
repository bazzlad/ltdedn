<?php

namespace Tests\Feature\ExternalOrders;

use App\Enums\ExternalImportStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Enums\StorefrontPlatform;
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
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_shopify_webhook_imports_paid_order_and_allocates_stock(): void
    {
        $connection = $this->shopifyConnection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => true]);
        $sku = ProductSku::factory()->for($product)->create(['sku_code' => 'PRINT-001', 'stock_on_hand' => 1]);
        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => $sku->id,
            'number' => 1,
            'status' => ProductEditionStatus::Available,
        ]);

        $payload = $this->shopifyPayload('gid://shopify/Order/1001', 'PRINT-001');
        $response = $this->postSignedShopifyPayload($connection, $payload);

        $response->assertOk()->assertJson(['status' => ExternalImportStatus::Processed->value]);

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('shopify', $order->source_platform);
        $this->assertSame('gid://shopify/Order/1001', $order->external_order_id);
        $this->assertDatabaseHas('product_skus', ['id' => $sku->id, 'stock_on_hand' => 0]);
        $this->assertDatabaseHas('product_editions', ['product_sku_id' => $sku->id, 'status' => ProductEditionStatus::Sold->value]);
    }

    public function test_duplicate_shopify_webhook_does_not_create_duplicate_order(): void
    {
        $connection = $this->shopifyConnection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-001', 'stock_on_hand' => 5]);

        $payload = $this->shopifyPayload('1002', 'TEE-001');

        $this->postSignedShopifyPayload($connection, $payload)->assertOk();
        $this->postSignedShopifyPayload($connection, $payload)->assertOk();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, ExternalOrderImport::query()->count());
    }

    public function test_invalid_shopify_signature_is_rejected(): void
    {
        $connection = $this->shopifyConnection();

        $this->call('POST', "/api/webhooks/shopify/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => 'bad-signature',
        ], json_encode($this->shopifyPayload('1003', 'TEE-001'), JSON_THROW_ON_ERROR))->assertUnauthorized();
    }

    public function test_unknown_sku_creates_exception_order_and_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $connection = $this->shopifyConnection();
        $payload = $this->shopifyPayload('1004', 'MISSING-SKU');

        $this->postSignedShopifyPayload($connection, $payload)
            ->assertOk()
            ->assertJson(['status' => ExternalImportStatus::Exception->value]);

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::Exception, $order->status);
        $this->assertStringContainsString('Unknown SKU', (string) $order->exception_reason);

        Notification::assertSentTo($admin, ExternalOrderExceptionNotification::class);
    }

    public function test_unpaid_shopify_order_is_recorded_as_ignored(): void
    {
        $connection = $this->shopifyConnection();
        $payload = $this->shopifyPayload('1005', 'TEE-001', 'pending');

        $this->postSignedShopifyPayload($connection, $payload)
            ->assertOk()
            ->assertJson(['status' => ExternalImportStatus::Ignored->value]);

        $this->assertSame(0, Order::query()->count());
        $this->assertDatabaseHas('external_order_imports', ['status' => ExternalImportStatus::Ignored->value]);
    }

    public function test_duplicate_sku_lines_are_allocated_cumulatively(): void
    {
        $connection = $this->shopifyConnection();
        $product = Product::factory()->for($connection->artist)->create(['is_limited' => false]);
        $sku = ProductSku::factory()->for($product)->create(['sku_code' => 'TEE-002', 'stock_on_hand' => 2]);

        $payload = $this->shopifyPayload('1006', 'TEE-002');
        $payload['line_items'][] = [
            'id' => 502,
            'sku' => 'TEE-002',
            'title' => 'Limited Print',
            'variant_title' => 'A2',
            'quantity' => 1,
            'price' => '25.00',
        ];

        $this->postSignedShopifyPayload($connection, $payload)
            ->assertOk()
            ->assertJson(['status' => ExternalImportStatus::Processed->value]);

        $this->assertDatabaseHas('product_skus', ['id' => $sku->id, 'stock_on_hand' => 0]);
        $this->assertSame(2, Order::query()->firstOrFail()->items()->count());
    }

    private function shopifyConnection(): StorefrontConnection
    {
        return StorefrontConnection::factory()
            ->for(Artist::factory())
            ->create([
                'platform' => StorefrontPlatform::Shopify,
                'webhook_secret' => 'test-secret',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopifyPayload(string $id, string $sku, string $paymentStatus = 'paid'): array
    {
        return [
            'id' => $id,
            'order_number' => 'S1001',
            'email' => 'buyer@example.com',
            'currency' => 'GBP',
            'financial_status' => $paymentStatus,
            'fulfillment_status' => null,
            'subtotal_price' => '25.00',
            'total_tax' => '0.00',
            'total_price' => '25.00',
            'shipping_address' => [
                'name' => 'Buyer Name',
                'address1' => '1 Test Street',
                'city' => 'London',
                'zip' => 'N1 1AA',
                'country_code' => 'GB',
            ],
            'line_items' => [
                [
                    'id' => 501,
                    'sku' => $sku,
                    'title' => 'Limited Print',
                    'variant_title' => 'A2',
                    'quantity' => 1,
                    'price' => '25.00',
                ],
            ],
            'total_shipping_price_set' => [
                'shop_money' => ['amount' => '0.00'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedShopifyPayload(StorefrontConnection $connection, array $payload): \Illuminate\Testing\TestResponse
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, $connection->webhook_secret, true));

        return $this->call('POST', "/api/webhooks/shopify/{$connection->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
            'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'delivery-1',
        ], $content);
    }
}
