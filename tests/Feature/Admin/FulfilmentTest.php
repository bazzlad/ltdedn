<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
