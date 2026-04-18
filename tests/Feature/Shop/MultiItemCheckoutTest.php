<?php

namespace Tests\Feature\Shop;

use App\Models\Artist;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Models\ShippingRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiItemCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.secret', 'sk_test_123');

        ShippingRate::query()->create([
            'code' => 'uk-standard',
            'label' => 'UK Standard',
            'currency' => 'gbp',
            'amount_minor' => 495,
            'country_codes' => ['GB'],
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function makeSellableProductWithEditions(int $editionCount = 3, int $stockOnHand = 10): array
    {
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
            'price_amount' => 5000,
            'stock_on_hand' => $stockOnHand,
            'stock_reserved' => 0,
            'is_active' => true,
            'attributes' => ['size' => 'M'],
        ]);

        for ($i = 0; $i < $editionCount; $i++) {
            ProductEdition::factory()->create([
                'product_id' => $product->id,
                'product_sku_id' => $sku->id,
                'status' => 'available',
            ]);
        }

        return [$product, $sku];
    }

    public function test_three_line_cart_checks_out_creating_one_order_n_items_and_n_reservations(): void
    {
        [$productA, $skuA] = $this->makeSellableProductWithEditions(3, 5);
        [$productB, $skuB] = $this->makeSellableProductWithEditions(3, 5);
        [$productC, $skuC] = $this->makeSellableProductWithEditions(3, 5);

        $user = User::factory()->create();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_multi_1',
                'client_secret' => 'cs_multi_1_secret',
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product_id' => $productA->id, 'product_sku_id' => $skuA->id, 'quantity' => 1]);
        $this->post(route('cart.items.store'), ['product_id' => $productB->id, 'product_sku_id' => $skuB->id, 'quantity' => 2]);
        $this->post(route('cart.items.store'), ['product_id' => $productC->id, 'product_sku_id' => $skuC->id, 'quantity' => 1]);

        $response = $this->post(route('shop.checkout'));
        $order = \App\Models\Order::where('stripe_checkout_session_id', 'cs_multi_1')->firstOrFail();
        $response->assertRedirect(route('shop.checkout.pay', ['order' => $order->id, 'key' => $order->order_creation_key]));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 3);
        $this->assertDatabaseCount('inventory_reservations', 4);

        $this->assertDatabaseHas('product_skus', ['id' => $skuA->id, 'stock_reserved' => 1]);
        $this->assertDatabaseHas('product_skus', ['id' => $skuB->id, 'stock_reserved' => 2]);
        $this->assertDatabaseHas('product_skus', ['id' => $skuC->id, 'stock_reserved' => 1]);

        $reservationSkuIds = InventoryReservation::query()->pluck('product_sku_id')->toArray();
        $this->assertCount(4, $reservationSkuIds);
    }

    public function test_partial_stockout_rolls_back_entire_checkout(): void
    {
        [$productA, $skuA] = $this->makeSellableProductWithEditions(3, 5);
        [$productB, $skuB] = $this->makeSellableProductWithEditions(0, 5);

        $user = User::factory()->create();

        Http::fake();

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product_id' => $productA->id, 'product_sku_id' => $skuA->id, 'quantity' => 1]);
        $this->post(route('cart.items.store'), ['product_id' => $productB->id, 'product_sku_id' => $skuB->id, 'quantity' => 1]);

        $response = $this->post(route('shop.checkout'));

        $response->assertRedirect(route('cart.show'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseHas('product_skus', ['id' => $skuA->id, 'stock_reserved' => 0]);

        Http::assertNothingSent();
    }

    public function test_stripe_api_failure_releases_all_reservations_for_order(): void
    {
        [$productA, $skuA] = $this->makeSellableProductWithEditions(3, 5);
        [$productB, $skuB] = $this->makeSellableProductWithEditions(3, 5);

        $user = User::factory()->create();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response(['error' => 'nope'], 500),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product_id' => $productA->id, 'product_sku_id' => $skuA->id, 'quantity' => 2]);
        $this->post(route('cart.items.store'), ['product_id' => $productB->id, 'product_sku_id' => $skuB->id, 'quantity' => 1]);

        $response = $this->post(route('shop.checkout'));

        $response->assertRedirect(route('cart.show'));

        $this->assertDatabaseHas('orders', ['status' => 'failed']);
        $this->assertDatabaseHas('product_skus', ['id' => $skuA->id, 'stock_reserved' => 0]);
        $this->assertDatabaseHas('product_skus', ['id' => $skuB->id, 'stock_reserved' => 0]);

        $this->assertSame(
            0,
            InventoryReservation::query()->where('status', 'active')->count(),
            'no active reservations should remain after rollback',
        );
    }

    public function test_webhook_persists_tax_shipping_and_address_fields_on_paid(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_xyz');

        [$productA, $skuA] = $this->makeSellableProductWithEditions(2, 5);

        $user = User::factory()->create();
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_multi_2',
                'client_secret' => 'cs_multi_2_secret',
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product_id' => $productA->id, 'product_sku_id' => $skuA->id, 'quantity' => 2]);
        $this->post(route('shop.checkout'));

        $order = \App\Models\Order::firstOrFail();

        $payload = [
            'id' => 'evt_completed_'.$order->id,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_multi_2',
                    'payment_intent' => 'pi_test_abc',
                    'metadata' => ['order_id' => (string) $order->id],
                    'amount_subtotal' => 10000,
                    'amount_total' => 12495,
                    'total_details' => ['amount_tax' => 2000],
                    'shipping_cost' => ['amount_total' => 495, 'shipping_rate' => 'shr_test_uk'],
                    'shipping_details' => [
                        'name' => 'Ada Lovelace',
                        'address' => [
                            'line1' => '12 Manor Road',
                            'line2' => 'Flat 3',
                            'city' => 'London',
                            'state' => null,
                            'postal_code' => 'E1 6AN',
                            'country' => 'GB',
                        ],
                    ],
                    'customer_details' => ['email' => null, 'phone' => '+447000000000'],
                ],
            ],
        ];

        $json = json_encode($payload);
        $timestamp = time();
        $sig = hash_hmac('sha256', $timestamp.'.'.$json, 'whsec_test_xyz');

        $response = $this->withHeaders(['Stripe-Signature' => 't='.$timestamp.',v1='.$sig])
            ->postJson(route('webhooks.stripe'), $payload);

        $response->assertOk();

        $order->refresh();
        $this->assertSame(10000, (int) $order->subtotal_amount);
        $this->assertSame(12495, (int) $order->total_amount);
        $this->assertSame(2000, (int) $order->tax_amount);
        $this->assertSame(495, (int) $order->shipping_amount);
        $this->assertSame('shr_test_uk', $order->shipping_rate_id);
        $this->assertSame('Ada Lovelace', $order->shipping_name);
        $this->assertSame('12 Manor Road', $order->shipping_line1);
        $this->assertSame('Flat 3', $order->shipping_line2);
        $this->assertSame('London', $order->shipping_city);
        $this->assertSame('E1 6AN', $order->shipping_postal_code);
        $this->assertSame('GB', $order->shipping_country);
        $this->assertSame('+447000000000', $order->shipping_phone);
        $this->assertSame('paid', $order->status->value);

        $this->assertDatabaseHas('product_skus', ['id' => $skuA->id, 'stock_reserved' => 0, 'stock_on_hand' => 3]);

        $this->assertSame(
            2,
            \App\Models\ProductEdition::query()->where('product_id', $productA->id)->where('status', 'sold')->count(),
        );
    }

    public function test_reconciliation_expires_multi_reservation_pending_orders(): void
    {
        [$productA, $skuA] = $this->makeSellableProductWithEditions(2, 5);

        $user = User::factory()->create();
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_multi_3',
                'client_secret' => 'cs_multi_3_secret',
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product_id' => $productA->id, 'product_sku_id' => $skuA->id, 'quantity' => 2]);
        $this->post(route('shop.checkout'));

        InventoryReservation::query()->update(['expires_at' => now()->subMinute()]);

        $this->artisan('shop:expire-reservations')->assertExitCode(0);

        $this->assertSame(
            0,
            InventoryReservation::query()->where('status', 'active')->count(),
        );
        $this->assertDatabaseHas('orders', ['status' => 'failed']);
        $this->assertDatabaseHas('product_skus', ['id' => $skuA->id, 'stock_reserved' => 0]);
    }

    public function test_reconcile_enriches_paid_order_with_customer_and_shipping_from_session(): void
    {
        [$productA, $skuA] = $this->makeSellableProductWithEditions(1, 3);

        $user = User::factory()->create();

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::sequence()
                ->push(['id' => 'cs_recon_1', 'client_secret' => 'cs_recon_1_secret'], 200),
            'https://api.stripe.com/v1/checkout/sessions/cs_recon_1' => Http::response([
                'id' => 'cs_recon_1',
                'status' => 'complete',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_recon_1',
                'amount_subtotal' => 10000,
                'amount_total' => 10495,
                'total_details' => ['amount_tax' => 0],
                'shipping_cost' => ['amount_total' => 495, 'shipping_rate' => 'shr_uk_std'],
                'customer_details' => ['email' => 'real-buyer@example.com', 'phone' => '+447700900000'],
                'shipping_details' => [
                    'name' => 'Real Buyer',
                    'address' => [
                        'line1' => '1 Bond St', 'line2' => 'Flat 2', 'city' => 'London',
                        'state' => '', 'postal_code' => 'W1S 1AA', 'country' => 'GB',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product_id' => $productA->id, 'product_sku_id' => $skuA->id, 'quantity' => 1]);
        $this->post(route('shop.checkout'));

        // Simulate the realistic case: ui_mode: elements doesn't collect
        // email on our side — the buyer types it into the Stripe Elements
        // form, and it only lands on the order via session enrichment.
        // Force the order past the 2-minute grace window, and blank the
        // pre-filled email so we assert the enrichment path fills it.
        \App\Models\Order::query()->update([
            'checkout_expires_at' => now()->subHour(),
            'customer_email' => null,
        ]);

        $this->artisan('shop:reconcile-pending-orders')->assertExitCode(0);

        $order = \App\Models\Order::firstOrFail();
        $this->assertSame('paid', $order->status->value);
        $this->assertSame('real-buyer@example.com', $order->customer_email);
        $this->assertSame('Real Buyer', $order->shipping_name);
        $this->assertSame('GB', $order->shipping_country);
        $this->assertSame('1 Bond St', $order->shipping_line1);
        $this->assertSame(10495, (int) $order->total_amount);
        $this->assertSame(495, (int) $order->shipping_amount);
        $this->assertSame('pi_recon_1', $order->stripe_payment_intent_id);
    }
}
