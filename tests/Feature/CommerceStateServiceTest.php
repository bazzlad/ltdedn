<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Artist;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Services\CommerceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceStateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingOrderWithReservation(array $skuOverrides = [], bool $withEdition = true): array
    {
        $artist = Artist::factory()->create();

        $product = Product::factory()->create([
            'artist_id' => $artist->id,
            'is_public' => true,
            'sell_through_ltdedn' => true,
            'is_sellable' => true,
            'sale_status' => 'active',
            'is_limited' => $withEdition,
        ]);

        $sku = ProductSku::factory()->create(array_merge([
            'product_id' => $product->id,
            'price_amount' => 9900,
            'stock_on_hand' => 3,
            'stock_reserved' => 1,
            'is_active' => true,
        ], $skuOverrides));

        $edition = $withEdition ? ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]) : null;

        $order = Order::create([
            'status' => OrderStatus::Pending,
            'currency' => 'gbp',
            'subtotal_amount' => 9900,
            'shipping_amount' => 0,
            'total_amount' => 9900,
            'customer_email' => 'buyer@example.com',
            'stripe_checkout_session_id' => 'cs_test_'.uniqid(),
            'checkout_expires_at' => now()->subMinutes(5),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_edition_id' => $edition?->id,
            'product_sku_id' => $sku->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'sku_code_snapshot' => $sku->sku_code,
            'attributes_snapshot' => $sku->attributes ?? [],
            'quantity' => 1,
            'unit_amount' => 9900,
            'line_total_amount' => 9900,
        ]);

        $reservation = InventoryReservation::create([
            'order_id' => $order->id,
            'product_edition_id' => $edition?->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->subMinutes(1),
        ]);

        return compact('product', 'sku', 'edition', 'order', 'reservation');
    }

    public function test_fulfill_paid_order_consumes_reservation_and_stock(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $service = app(CommerceStateService::class);

        $result = $service->fulfillPaidOrder($data['order'], [
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $this->assertTrue($result);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $this->assertDatabaseHas('inventory_reservations', [
            'id' => $data['reservation']->id,
            'status' => 'consumed',
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $data['sku']->id,
            'stock_on_hand' => 2,
            'stock_reserved' => 0,
        ]);

        $this->assertDatabaseHas('product_editions', [
            'id' => $data['edition']->id,
            'status' => 'sold',
        ]);
    }

    public function test_fulfill_paid_order_without_edition(): void
    {
        $data = $this->createPendingOrderWithReservation([], false);
        $service = app(CommerceStateService::class);

        $result = $service->fulfillPaidOrder($data['order']);

        $this->assertTrue($result);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('inventory_reservations', [
            'id' => $data['reservation']->id,
            'status' => 'consumed',
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $data['sku']->id,
            'stock_on_hand' => 2,
            'stock_reserved' => 0,
        ]);
    }

    public function test_fulfill_paid_order_is_idempotent(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $service = app(CommerceStateService::class);

        $this->assertTrue($service->fulfillPaidOrder($data['order']));
        $data['order']->refresh();
        $this->assertFalse($service->fulfillPaidOrder($data['order']));

        $this->assertDatabaseHas('product_skus', [
            'id' => $data['sku']->id,
            'stock_on_hand' => 2,
            'stock_reserved' => 0,
        ]);
    }

    public function test_fail_pending_order_releases_reservation_and_stock(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $service = app(CommerceStateService::class);

        $result = $service->failPendingOrder($data['order'], 'reconciliation_expired');

        $this->assertTrue($result);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('inventory_reservations', [
            'id' => $data['reservation']->id,
            'status' => 'released',
            'release_reason' => 'reconciliation_expired',
        ]);

        $this->assertDatabaseHas('product_skus', [
            'id' => $data['sku']->id,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);
    }

    public function test_fail_pending_order_is_idempotent(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $service = app(CommerceStateService::class);

        $this->assertTrue($service->failPendingOrder($data['order'], 'test'));
        $data['order']->refresh();
        $this->assertFalse($service->failPendingOrder($data['order'], 'test'));

        $this->assertDatabaseHas('product_skus', [
            'id' => $data['sku']->id,
            'stock_on_hand' => 3,
            'stock_reserved' => 0,
        ]);
    }

    public function test_apply_session_fields_reads_basil_collected_information_path(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $service = app(CommerceStateService::class);

        // API version 2025-03-31.basil and later return the buyer's address
        // under collected_information.shipping_details, and leave the legacy
        // top-level shipping_details null.
        $service->applySessionFieldsToOrder($data['order'], [
            'id' => 'cs_test_basil',
            'amount_subtotal' => 9900,
            'amount_total' => 10395,
            'total_details' => ['amount_tax' => 0],
            'shipping_cost' => ['amount_total' => 495, 'shipping_rate' => 'shr_uk'],
            'customer_details' => ['email' => 'basil@example.com', 'phone' => null],
            'shipping_details' => null,
            'collected_information' => [
                'shipping_details' => [
                    'name' => 'Basil Buyer',
                    'address' => [
                        'line1' => '20 The Poplars',
                        'line2' => 'Wordsley',
                        'city' => 'Stourbridge',
                        'state' => '',
                        'postal_code' => 'DY8 5SN',
                        'country' => 'GB',
                    ],
                ],
            ],
        ]);

        $data['order']->refresh();
        $this->assertSame('Basil Buyer', $data['order']->shipping_name);
        $this->assertSame('20 The Poplars', $data['order']->shipping_line1);
        $this->assertSame('Wordsley', $data['order']->shipping_line2);
        $this->assertSame('Stourbridge', $data['order']->shipping_city);
        $this->assertSame('DY8 5SN', $data['order']->shipping_postal_code);
        $this->assertSame('GB', $data['order']->shipping_country);
    }

    public function test_apply_session_fields_falls_back_to_legacy_shipping_details_path(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $service = app(CommerceStateService::class);

        // Older API versions (pre-basil) and replayed historical webhooks
        // return the address under the top-level shipping_details and have
        // no collected_information block at all.
        $service->applySessionFieldsToOrder($data['order'], [
            'id' => 'cs_test_legacy',
            'amount_subtotal' => 9900,
            'amount_total' => 10395,
            'total_details' => ['amount_tax' => 0],
            'shipping_cost' => ['amount_total' => 495, 'shipping_rate' => 'shr_uk'],
            'customer_details' => ['email' => 'legacy@example.com', 'phone' => null],
            'shipping_details' => [
                'name' => 'Legacy Buyer',
                'address' => [
                    'line1' => '1 Old Path',
                    'line2' => null,
                    'city' => 'London',
                    'state' => null,
                    'postal_code' => 'SW1A 1AA',
                    'country' => 'GB',
                ],
            ],
        ]);

        $data['order']->refresh();
        $this->assertSame('Legacy Buyer', $data['order']->shipping_name);
        $this->assertSame('1 Old Path', $data['order']->shipping_line1);
        $this->assertSame('London', $data['order']->shipping_city);
        $this->assertSame('SW1A 1AA', $data['order']->shipping_postal_code);
        $this->assertSame('GB', $data['order']->shipping_country);
    }

    public function test_fail_pending_order_succeeds_even_without_reservation(): void
    {
        $data = $this->createPendingOrderWithReservation();
        $data['reservation']->update(['status' => 'released', 'released_at' => now()]);

        $service = app(CommerceStateService::class);

        $result = $service->failPendingOrder($data['order'], 'reconciliation_expired');

        $this->assertTrue($result);

        $this->assertDatabaseHas('orders', [
            'id' => $data['order']->id,
            'status' => 'failed',
        ]);
    }
}
