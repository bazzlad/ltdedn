<?php

namespace Tests\Feature\Shop;

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderReceivedAdminMail;
use App\Models\Artist;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use App\Services\CommerceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderEmailsTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingOrder(): array
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
            'price_amount' => 5000,
            'stock_on_hand' => 3,
            'stock_reserved' => 1,
            'is_active' => true,
        ]);

        $edition = ProductEdition::factory()->create([
            'product_id' => $product->id,
            'product_sku_id' => $sku->id,
            'status' => 'available',
        ]);

        $order = Order::create([
            'status' => 'pending',
            'currency' => 'gbp',
            'subtotal_amount' => 5000,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'customer_email' => 'buyer@example.com',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'sku_code_snapshot' => $sku->sku_code,
            'attributes_snapshot' => $sku->attributes,
            'quantity' => 1,
            'unit_amount' => 5000,
            'line_total_amount' => 5000,
        ]);

        InventoryReservation::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_edition_id' => $edition->id,
            'product_sku_id' => $sku->id,
            'quantity' => 1,
            'status' => 'active',
            'expires_at' => now()->addMinutes(15),
        ]);

        return [$order, $sku, $edition];
    }

    public function test_buyer_and_admin_mails_queued_on_first_fulfillment(): void
    {
        Mail::fake();
        config()->set('shop.admin_notification_email', 'ops@example.com');

        [$order] = $this->makePendingOrder();

        /** @var CommerceStateService $service */
        $service = app(CommerceStateService::class);
        $this->assertTrue($service->fulfillPaidOrder($order));

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            return $mail->hasTo('buyer@example.com');
        });
        Mail::assertQueued(OrderReceivedAdminMail::class, function (OrderReceivedAdminMail $mail) {
            return $mail->hasTo('ops@example.com');
        });
    }

    public function test_admin_mail_skipped_when_config_empty(): void
    {
        Mail::fake();
        config()->set('shop.admin_notification_email', '');

        [$order] = $this->makePendingOrder();

        /** @var CommerceStateService $service */
        $service = app(CommerceStateService::class);
        $service->fulfillPaidOrder($order);

        Mail::assertQueued(OrderConfirmationMail::class);
        Mail::assertNotQueued(OrderReceivedAdminMail::class);
    }

    public function test_buyer_mail_skipped_when_order_has_no_customer_email(): void
    {
        Mail::fake();
        config()->set('shop.admin_notification_email', 'ops@example.com');

        [$order] = $this->makePendingOrder();
        $order->update(['customer_email' => null]);

        /** @var CommerceStateService $service */
        $service = app(CommerceStateService::class);
        $service->fulfillPaidOrder($order);

        Mail::assertNotQueued(OrderConfirmationMail::class);
        Mail::assertQueued(OrderReceivedAdminMail::class);
    }

    public function test_duplicate_fulfillment_does_not_resend_emails(): void
    {
        Mail::fake();
        config()->set('shop.admin_notification_email', 'ops@example.com');

        [$order] = $this->makePendingOrder();

        /** @var CommerceStateService $service */
        $service = app(CommerceStateService::class);
        $this->assertTrue($service->fulfillPaidOrder($order));
        $this->assertFalse($service->fulfillPaidOrder($order));

        Mail::assertQueuedCount(2);
    }

    public function test_meta_stamp_prevents_second_send_if_fulfillment_runs_again(): void
    {
        Mail::fake();
        config()->set('shop.admin_notification_email', 'ops@example.com');

        [$order] = $this->makePendingOrder();

        /** @var CommerceStateService $service */
        $service = app(CommerceStateService::class);
        $service->fulfillPaidOrder($order);

        $order->refresh();
        $this->assertNotEmpty($order->meta['confirmation_mailed_at'] ?? null);
    }
}
