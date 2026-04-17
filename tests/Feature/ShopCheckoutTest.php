<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_order_and_redirects_to_stripe(): void
    {
        config()->set('services.stripe.secret', 'sk_test_123');

        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'is_limited' => true,
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 9900,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => ['size' => 'M'],
        ]);

        $edition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ]);

        $response = $this->post(route('shop.checkout'));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $this->assertDatabaseHas('inventory_reservations', [
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('orders', [
            'stripe_checkout_session_id' => 'cs_test_123',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $sku->id,
            'stock_reserved' => 1,
        ]);
    }

    public function test_shop_product_route_loads_for_sellable_edition_product(): void
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $response = $this->get(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));

        $response->assertOk();
    }

    public function test_shop_product_slug_route_loads_for_sellable_edition_product(): void
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $response = $this->get(route('shop.product.slug', ['artistSlug' => $artist->slug, 'productSlug' => $product->slug]));

        $response->assertOk();
    }

    public function test_non_public_product_redirects_guests_to_login(): void
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => false,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $response = $this->get(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_non_public_product_returns_404_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => false,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $response = $this->actingAs($user)
            ->get(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));

        $response->assertNotFound();
    }

    public function test_non_public_product_slug_returns_404_for_guests(): void
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => false,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $response = $this->get(route('shop.product.slug', ['artistSlug' => $artist->slug, 'productSlug' => $product->slug]));

        $response->assertNotFound();
    }

    public function test_checkout_rejects_non_public_product_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => false,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'is_limited' => true,
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 9900,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        Http::fake();

        $response = $this->actingAs($user)
            ->from(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]))
            ->post(route('shop.checkout'), [
                'artist_id' => $artist->id,
                'product_id' => $product->id,
                'product_sku_id' => $sku->id,
            ]);

        $response->assertRedirect(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));
        $response->assertSessionHasErrors('product_id');
        Http::assertNothingSent();
    }

    public function test_shop_index_excludes_sold_out_and_non_public_products(): void
    {
        $artist = Artist::factory()->create();

        $visibleProduct = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'name' => 'Visible Product',
        ]);

        ProductEdition::factory()->create([
            'product_id' => $visibleProduct->id,
            'product_sku_id' => null,
            'status' => 'available',
        ]);

        $nonPublicProduct = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => false,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'name' => 'Private Product',
        ]);

        ProductEdition::factory()->create([
            'product_id' => $nonPublicProduct->id,
            'product_sku_id' => null,
            'status' => 'available',
        ]);

        $soldOutProduct = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'name' => 'Sold Out Product',
        ]);

        ProductEdition::factory()->create([
            'product_id' => $soldOutProduct->id,
            'product_sku_id' => null,
            'status' => 'sold',
        ]);

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Shop')
                ->has('products', 1)
                ->where('products.0.name', 'Visible Product');
        });
    }

    public function test_sold_out_product_route_returns_404(): void
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => null,
            'status' => 'sold',
        ]);

        $response = $this->get(route('shop.product', ['artistId' => $artist->id, 'productId' => $product->id]));

        $response->assertNotFound();
    }

    public function test_checkout_uses_standard_price_when_edition_has_no_sku(): void
    {
        config()->set('services.stripe.secret', 'sk_test_123');

        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'base_price' => 19.99,
            'currency' => 'gbp',
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'is_limited' => true,
        ]);

        $edition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => null,
            'status' => 'available',
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_standard',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_standard',
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->post(route('shop.checkout'));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_standard');

        $this->assertDatabaseHas('inventory_reservations', [
            'product_edition_id' => $edition->id,
            'product_sku_id' => null,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_sku_id' => null,
            'unit_amount' => 1999,
            'sku_code_snapshot' => 'STANDARD',
        ]);
    }

    public function test_checkout_fails_when_product_has_no_editions(): void
    {
        config()->set('services.stripe.secret', 'sk_test_123');

        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'is_limited' => true,
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 9900,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
            'is_active' => true,
        ]);

        Http::fake();

        $this->actingAs($user);
        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
        ]);

        $response = $this->post(route('shop.checkout'));

        $response->assertRedirect(route('cart.show'));
        $response->assertSessionHasErrors('cart');

        Http::assertNothingSent();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_webhook_marks_order_paid_and_consumes_reservation(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 9900,
            'stock_on_hand' => 3,
            'stock_reserved' => 1,
            'is_active' => true,
        ]);

        $edition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $order = \App\Models\Order::create([
            'status' => 'pending',
            'currency' => 'gbp',
            'subtotal_amount' => 9900,
            'shipping_amount' => 0,
            'total_amount' => 9900,
            'customer_email' => 'buyer@example.com',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'sku_code_snapshot' => $sku->sku_code,
            'attributes_snapshot' => $sku->attributes,
            'quantity' => 1,
            'unit_amount' => 9900,
            'line_total_amount' => 9900,
        ]);

        \App\Models\InventoryReservation::create([
            'order_id' => $order->id,
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->addMinutes(15),
        ]);

        $payload = [
            'id' => 'evt_test_paid_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_abc',
                    'payment_intent' => 'pi_test_abc',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        $json = json_encode($payload);
        $timestamp = time();
        $sig = hash_hmac('sha256', $timestamp.'.'.$json, 'whsec_test_123');

        $response = $this->withHeaders([
            'Stripe-Signature' => 't='.$timestamp.',v1='.$sig,
        ])->postJson(route('webhooks.stripe'), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'stripe_checkout_session_id' => 'cs_test_abc',
            'stripe_payment_intent_id' => 'pi_test_abc',
        ]);

        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'status' => 'consumed',
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $sku->id,
            'stock_on_hand' => 2,
            'stock_reserved' => 0,
        ]);

        $this->assertDatabaseHas('product_editions', [
            'id' => $edition->id,
            'status' => 'sold',
        ]);
    }

    public function test_checkout_reserves_edition_for_selected_sku_when_multiple_skus_exist(): void
    {
        config()->set('services.stripe.secret', 'sk_test_123');

        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'is_limited' => true,
        ]);

        $smallSku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 2000,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => ['size' => 'S'],
        ]);

        $largeSku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 2500,
            'stock_on_hand' => 10,
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => ['size' => 'L'],
        ]);

        $smallEdition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $smallSku->id,
            'status' => 'available',
        ]);

        $largeEdition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $largeSku->id,
            'status' => 'available',
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_456',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_456',
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_sku_id' => $largeSku->id,
            'quantity' => 1,
        ]);

        $response = $this->post(route('shop.checkout'));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_456');

        $this->assertDatabaseHas('inventory_reservations', [
            'product_edition_id' => $largeEdition->id,
            'product_sku_id' => $largeSku->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('inventory_reservations', [
            'product_edition_id' => $smallEdition->id,
            'product_sku_id' => $largeSku->id,
            'status' => 'active',
        ]);
    }

    public function test_expired_checkout_releases_active_reservation(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_123');

        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
        ]);

        $sku = ProductSku::factory()->create([
            'product_id' => $product->id,
            'price_amount' => 9900,
            'stock_on_hand' => 3,
            'stock_reserved' => 1,
            'is_active' => true,
        ]);

        $edition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $order = \App\Models\Order::create([
            'status' => 'pending',
            'currency' => 'gbp',
            'subtotal_amount' => 9900,
            'shipping_amount' => 0,
            'total_amount' => 9900,
            'customer_email' => 'buyer@example.com',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'sku_code_snapshot' => $sku->sku_code,
            'attributes_snapshot' => $sku->attributes,
            'quantity' => 1,
            'unit_amount' => 9900,
            'line_total_amount' => 9900,
        ]);

        \App\Models\InventoryReservation::create([
            'order_id' => $order->id,
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->addMinutes(15),
        ]);

        $payload = [
            'id' => 'evt_test_expired_1',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        $json = json_encode($payload);
        $timestamp = time();
        $sig = hash_hmac('sha256', $timestamp.'.'.$json, 'whsec_test_123');

        $response = $this->withHeaders([
            'Stripe-Signature' => 't='.$timestamp.',v1='.$sig,
        ])->postJson(route('webhooks.stripe'), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'status' => 'released',
            'release_reason' => 'checkout.session.expired',
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $sku->id,
            'stock_reserved' => 0,
            'stock_on_hand' => 3,
        ]);
    }
}
