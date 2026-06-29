<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\StorefrontPlatform;
use App\Enums\UserRole;
use App\Jobs\PushShopifyFulfilment;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\StorefrontConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfilment_queue_shows_paid_unshipped_non_exception_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ready = Order::factory()->create(['status' => OrderStatus::Paid, 'shipped_at' => null, 'exception_reason' => null]);
        OrderItem::factory()->for($ready)->create();

        Order::factory()->create(['status' => OrderStatus::Exception, 'exception_reason' => 'Unknown SKU']);
        Order::factory()->create(['status' => OrderStatus::Paid, 'shipped_at' => now()]);

        $this->actingAs($admin)
            ->get('/admin/fulfilment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Fulfilment/Index')
                ->has('orders', 1)
                ->where('orders.0.id', $ready->id)
            );
    }

    public function test_admin_can_mark_order_shipped(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'shipped_at' => null]);

        $this->actingAs($admin)
            ->post("/admin/sales/{$order->id}/ship", [
                'carrier' => 'Royal Mail',
                'tracking' => 'TRACK123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_carrier' => 'Royal Mail',
            'shipping_tracking_number' => 'TRACK123',
        ]);
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_admin_can_retry_failed_shipment_pushback(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $connection = StorefrontConnection::factory()->create([
            'platform' => StorefrontPlatform::Shopify,
            'store_url' => 'https://retry.myshopify.com',
        ]);
        $order = Order::factory()->for($connection, 'connection')->create([
            'source_platform' => 'shopify',
            'external_order_id' => 'gid://shopify/Order/123',
            'shipment_pushback_status' => 'failed',
            'shipment_pushback_error' => 'Shopify pushback failed with HTTP 403.',
            'shipping_carrier' => 'Royal Mail',
            'shipping_tracking_number' => 'TRACK123',
            'shipped_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/admin/sales/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.can_retry_pushback', true)
            );

        $this->actingAs($admin)
            ->post("/admin/sales/{$order->id}/retry-pushback")
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('pending', $order->shipment_pushback_status);
        $this->assertNull($order->shipment_pushback_error);
        $this->assertTrue(OrderEvent::query()
            ->where('order_id', $order->id)
            ->where('type', 'shipment_pushback_retry_queued')
            ->exists());

        Queue::assertPushed(PushShopifyFulfilment::class, fn (PushShopifyFulfilment $job) => $job->orderId === $order->id);
    }

    public function test_admin_cannot_retry_non_failed_shipment_pushback(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::factory()->create([
            'source_platform' => 'shopify',
            'external_order_id' => 'gid://shopify/Order/123',
            'shipment_pushback_status' => 'succeeded',
            'shipping_carrier' => 'Royal Mail',
            'shipping_tracking_number' => 'TRACK123',
            'shipped_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/admin/sales/{$order->id}/retry-pushback")
            ->assertSessionHasErrors('pushback');

        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_retry_failed_shipment_pushback_without_connection(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::factory()->create([
            'storefront_connection_id' => null,
            'source_platform' => 'shopify',
            'external_order_id' => 'gid://shopify/Order/123',
            'shipment_pushback_status' => 'failed',
            'shipment_pushback_error' => 'Shopify pushback failed with HTTP 403.',
            'shipping_carrier' => 'Royal Mail',
            'shipping_tracking_number' => 'TRACK123',
            'shipped_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/admin/sales/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.can_retry_pushback', false)
            );

        $this->actingAs($admin)
            ->post("/admin/sales/{$order->id}/retry-pushback")
            ->assertSessionHasErrors('pushback');

        $this->assertSame('failed', $order->fresh()->shipment_pushback_status);
        Queue::assertNothingPushed();
    }
}
