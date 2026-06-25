<?php

namespace Tests\Feature;

use App\Enums\ProductEditionStatus;
use App\Enums\StorefrontConnectionStatus;
use App\Enums\StorefrontPlatform;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\StorefrontConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConnectStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_create_internal_storefront_connection(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/storefront-connections', [
                'artist_id' => $artist->id,
                'platform' => StorefrontPlatform::Shopify->value,
                'name' => 'Joe Bloggs Store',
                'store_url' => 'https://joe.myshopify.com',
                'access_token' => 'manual-token',
                'webhook_secret' => 'manual-secret',
                'connection_status' => StorefrontConnectionStatus::Testing->value,
            ])
            ->assertRedirect();

        $connection = StorefrontConnection::query()->firstOrFail();

        $this->assertSame(StorefrontPlatform::Shopify, $connection->platform);
        $this->assertSame(StorefrontConnectionStatus::Testing, $connection->connection_status);
        $this->assertSame('manual-token', data_get($connection->credentials, 'access_token'));
        $this->assertStringNotContainsString('manual-token', (string) $connection->getRawOriginal('credentials'));
        $this->assertStringNotContainsString('manual-secret', (string) $connection->getRawOriginal('webhook_secret'));
    }

    public function test_admin_shopify_connection_defaults_to_configured_app_secret(): void
    {
        Config::set('services.shopify_connect.client_secret', 'configured-shopify-secret');

        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/storefront-connections', [
                'artist_id' => $artist->id,
                'platform' => StorefrontPlatform::Shopify->value,
                'name' => 'Configured Secret Store',
                'store_url' => 'https://configured.myshopify.com',
                'connection_status' => StorefrontConnectionStatus::Testing->value,
            ])
            ->assertRedirect();

        $connection = StorefrontConnection::query()->firstOrFail();

        $this->assertSame('configured-shopify-secret', $connection->webhook_secret);
        $this->assertStringNotContainsString('configured-shopify-secret', (string) $connection->getRawOriginal('webhook_secret'));
    }

    public function test_admin_can_create_orderdesk_connection(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/storefront-connections', [
                'artist_id' => $artist->id,
                'platform' => StorefrontPlatform::OrderDesk->value,
                'name' => 'Joe Bloggs Order Desk',
                'external_shop_id' => 'od-store-1',
                'access_token' => 'od-api-key',
                'connection_status' => StorefrontConnectionStatus::Testing->value,
            ])
            ->assertRedirect();

        $connection = StorefrontConnection::query()->firstOrFail();

        $this->assertSame(StorefrontPlatform::OrderDesk, $connection->platform);
        $this->assertSame('od-store-1', $connection->external_shop_id);
        $this->assertSame('od-api-key', data_get($connection->credentials, 'api_key'));
        $this->assertNull($connection->webhook_secret);
        $this->assertStringNotContainsString('od-api-key', (string) $connection->getRawOriginal('credentials'));
    }

    public function test_artist_cannot_use_admin_connection_wizard(): void
    {
        $artistUser = User::factory()->artist()->create();
        $artist = Artist::factory()->ownedBy($artistUser)->create();

        $this->actingAs($artistUser)
            ->post('/admin/storefront-connections', [
                'artist_id' => $artist->id,
                'platform' => StorefrontPlatform::Shopify->value,
                'name' => 'Blocked Store',
                'connection_status' => StorefrontConnectionStatus::Testing->value,
            ])
            ->assertForbidden();
    }

    public function test_admin_connection_detail_shows_sku_checklist_and_test_state(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['is_limited' => false]);
        ProductSku::factory()->for($product)->create([
            'sku_code' => 'JB-PRINT-A2',
            'stock_on_hand' => 4,
        ]);
        $connection = StorefrontConnection::factory()->for($artist)->create([
            'platform' => StorefrontPlatform::Shopify,
            'last_sync_meta' => ['external_skus' => ['JB-PRINT-A2']],
        ]);

        $this->actingAs($admin)
            ->get("/admin/storefront-connections/{$connection->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/StorefrontConnections/Show')
                ->where('connection.id', $connection->id)
                ->where('skuChecklist.0.sku_code', 'JB-PRINT-A2')
                ->where('skuChecklist.0.store_sku_found', true)
                ->where('testOrder.state', 'waiting')
            );
    }

    public function test_limited_sku_checklist_counts_unassigned_product_editions(): void
    {
        $admin = User::factory()->admin()->create();
        $artist = Artist::factory()->create();
        $product = Product::factory()->for($artist)->create(['is_limited' => true]);
        ProductSku::factory()->for($product)->create([
            'sku_code' => 'JB-PRINT-LTD',
            'stock_on_hand' => 1,
        ]);
        ProductEdition::factory()->for($product)->create([
            'product_sku_id' => null,
            'status' => ProductEditionStatus::Available,
        ]);
        $connection = StorefrontConnection::factory()->for($artist)->create([
            'platform' => StorefrontPlatform::Shopify,
        ]);

        $this->actingAs($admin)
            ->get("/admin/storefront-connections/{$connection->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('skuChecklist.0.sku_code', 'JB-PRINT-LTD')
                ->where('skuChecklist.0.editions_available', 1)
                ->where('skuChecklist.0.status', 'ready')
            );
    }

    public function test_admin_can_mark_connection_tested_and_active(): void
    {
        $admin = User::factory()->admin()->create();
        $connection = StorefrontConnection::factory()->create([
            'connection_status' => StorefrontConnectionStatus::Testing,
            'tested_at' => null,
            'activated_at' => null,
        ]);

        $this->actingAs($admin)
            ->post("/admin/storefront-connections/{$connection->id}/test")
            ->assertRedirect();

        $this->assertNotNull($connection->fresh()->tested_at);

        $this->actingAs($admin)
            ->post("/admin/storefront-connections/{$connection->id}/activate")
            ->assertRedirect();

        $connection->refresh();

        $this->assertSame(StorefrontConnectionStatus::Ready, $connection->connection_status);
        $this->assertNotNull($connection->activated_at);
    }

    public function test_artist_connect_page_only_lists_their_connections(): void
    {
        $artistUser = User::factory()->artist()->create();
        $ownedArtist = Artist::factory()->ownedBy($artistUser)->create();
        $otherArtist = Artist::factory()->create();
        $ownedConnection = StorefrontConnection::factory()->for($ownedArtist)->create(['name' => 'Owned Store']);

        StorefrontConnection::factory()->for($otherArtist)->create(['name' => 'Other Store']);

        $this->actingAs($artistUser)
            ->get('/connect/storefronts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Connect/Storefronts')
                ->has('connections', 1)
                ->where('connections.0.id', $ownedConnection->id)
            );
    }

    public function test_shopify_callback_rejects_invalid_hmac(): void
    {
        Config::set('services.shopify_connect.client_id', 'client-id');
        Config::set('services.shopify_connect.client_secret', 'client-secret');

        $artistUser = User::factory()->artist()->create();
        $artist = Artist::factory()->ownedBy($artistUser)->create();

        $this->actingAs($artistUser)
            ->withSession([
                'connect.shopify' => [
                    'state' => 'state-token',
                    'artist_id' => $artist->id,
                    'shop' => 'joe.myshopify.com',
                    'name' => 'Joe Store',
                ],
            ])
            ->get('/connect/shopify/callback?shop=joe.myshopify.com&code=code&state=state-token&hmac=bad')
            ->assertRedirect('/connect/storefronts');

        $this->assertSame(0, StorefrontConnection::query()->count());
    }

    public function test_shopify_callback_creates_connection_and_registers_webhook(): void
    {
        Config::set('services.shopify_connect.client_id', 'client-id');
        Config::set('services.shopify_connect.client_secret', 'client-secret');
        Config::set('services.shopify_connect.scopes', ['read_orders', 'write_fulfillments']);
        Config::set('services.shopify_connect.api_version', '2025-10');

        Http::fake([
            'https://joe.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test_token',
                'scope' => 'read_orders,write_fulfillments',
            ]),
            'https://joe.myshopify.com/admin/api/2025-10/webhooks.json' => Http::response([
                'webhook' => ['id' => 123456],
            ]),
        ]);

        $artistUser = User::factory()->artist()->create();
        $artist = Artist::factory()->ownedBy($artistUser)->create();
        $query = [
            'shop' => 'joe.myshopify.com',
            'code' => 'oauth-code',
            'state' => 'state-token',
            'timestamp' => '1710000000',
        ];
        $query['hmac'] = $this->shopifyHmac($query, 'client-secret');

        $this->actingAs($artistUser)
            ->withSession([
                'connect.shopify' => [
                    'state' => 'state-token',
                    'artist_id' => $artist->id,
                    'shop' => 'joe.myshopify.com',
                    'name' => 'Joe Store',
                ],
            ])
            ->get('/connect/shopify/callback?'.http_build_query($query))
            ->assertRedirect();

        $connection = StorefrontConnection::query()->firstOrFail();

        $this->assertSame(StorefrontPlatform::Shopify, $connection->platform);
        $this->assertSame(StorefrontConnectionStatus::Testing, $connection->connection_status);
        $this->assertSame('joe.myshopify.com', $connection->external_shop_domain);
        $this->assertSame('123456', (string) $connection->webhook_subscription_id);
        $this->assertSame('shpat_test_token', data_get($connection->credentials, 'access_token'));
        $this->assertStringNotContainsString('shpat_test_token', (string) $connection->getRawOriginal('credentials'));
        $this->assertStringNotContainsString('client-secret', (string) $connection->getRawOriginal('webhook_secret'));
    }

    public function test_shopify_callback_cannot_reassign_existing_shop_to_another_artist(): void
    {
        Config::set('services.shopify_connect.client_id', 'client-id');
        Config::set('services.shopify_connect.client_secret', 'client-secret');

        $existingArtist = Artist::factory()->create();
        StorefrontConnection::factory()->for($existingArtist)->create([
            'platform' => StorefrontPlatform::Shopify,
            'external_shop_domain' => 'joe.myshopify.com',
            'name' => 'Existing Store',
        ]);
        $artistUser = User::factory()->artist()->create();
        $newArtist = Artist::factory()->ownedBy($artistUser)->create();
        $query = [
            'shop' => 'joe.myshopify.com',
            'code' => 'oauth-code',
            'state' => 'state-token',
            'timestamp' => '1710000000',
        ];
        $query['hmac'] = $this->shopifyHmac($query, 'client-secret');

        $this->actingAs($artistUser)
            ->withSession([
                'connect.shopify' => [
                    'state' => 'state-token',
                    'artist_id' => $newArtist->id,
                    'shop' => 'joe.myshopify.com',
                    'name' => 'Takeover Store',
                ],
            ])
            ->get('/connect/shopify/callback?'.http_build_query($query))
            ->assertRedirect('/connect/storefronts');

        $this->assertSame($existingArtist->id, StorefrontConnection::query()->firstOrFail()->artist_id);
    }

    public function test_squarespace_callback_reuses_existing_artist_connection(): void
    {
        Config::set('services.squarespace_connect.client_id', 'client-id');
        Config::set('services.squarespace_connect.client_secret', 'client-secret');
        Config::set('services.squarespace_connect.scopes', ['website.orders']);

        Http::fake([
            'https://login.squarespace.com/api/1/login/oauth/provider/tokens' => Http::response([
                'access_token' => 'squarespace-token',
                'refresh_token' => 'squarespace-refresh',
            ]),
            'https://api.squarespace.com/1.0/webhook_subscriptions' => Http::response([
                'id' => 'subscription-1',
                'websiteId' => 'website-1',
                'secret' => 'squarespace-secret',
            ]),
        ]);

        $artistUser = User::factory()->artist()->create();
        $artist = Artist::factory()->ownedBy($artistUser)->create();

        StorefrontConnection::factory()->for($artist)->create([
            'platform' => StorefrontPlatform::Squarespace,
            'external_shop_id' => 'website-1',
            'name' => 'Joe Squarespace',
            'connection_status' => StorefrontConnectionStatus::Failed,
        ]);

        $this->actingAs($artistUser)
            ->withSession([
                'connect.squarespace' => [
                    'state' => 'state-token',
                    'artist_id' => $artist->id,
                    'website_id' => 'website-1',
                    'name' => 'Joe Squarespace',
                ],
            ])
            ->get('/connect/squarespace/callback?code=oauth-code&state=state-token')
            ->assertRedirect();

        $connection = StorefrontConnection::query()->firstOrFail();

        $this->assertSame(1, StorefrontConnection::query()->count());
        $this->assertSame(StorefrontConnectionStatus::Testing, $connection->connection_status);
        $this->assertSame('subscription-1', $connection->webhook_subscription_id);
        $this->assertSame('squarespace-token', data_get($connection->credentials, 'access_token'));
        $this->assertSame('squarespace-secret', $connection->webhook_secret);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function shopifyHmac(array $query, string $secret): string
    {
        ksort($query);

        return hash_hmac('sha256', http_build_query($query, '', '&', PHP_QUERY_RFC3986), $secret);
    }
}
