<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        return Order::create([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 5000,
            'shipping_amount' => 495,
            'tax_amount' => 0,
            'total_amount' => 5495,
            'paid_at' => now(),
            'customer_email' => 'buyer@example.com',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);
    }

    public function test_admin_can_mark_order_shipped(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        $response = $this->actingAs($admin)->post(route('admin.sales.ship', $order), [
            'carrier' => 'Royal Mail',
            'tracking' => 'RM1234GB',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame('Royal Mail', $order->shipping_carrier);
        $this->assertSame('RM1234GB', $order->shipping_tracking_number);
        $this->assertNotNull($order->shipped_at);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'type' => 'shipped',
        ]);
    }

    public function test_second_ship_submission_logs_tracking_update(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        $this->actingAs($admin)->post(route('admin.sales.ship', $order), [
            'carrier' => 'Royal Mail',
            'tracking' => 'RM1234GB',
        ]);

        $this->actingAs($admin)->post(route('admin.sales.ship', $order), [
            'carrier' => 'DPD',
            'tracking' => 'DPD999',
        ]);

        $order->refresh();
        $this->assertSame('DPD', $order->shipping_carrier);
        $this->assertSame('DPD999', $order->shipping_tracking_number);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'shipping_updated',
        ]);
    }

    public function test_ship_rejected_when_order_not_paid(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();
        $order->update(['status' => 'pending']);

        $response = $this->actingAs($admin)->post(route('admin.sales.ship', $order), [
            'carrier' => 'Royal Mail',
            'tracking' => 'RM1234GB',
        ]);

        $response->assertSessionHasErrors('shipping');
        $this->assertDatabaseMissing('order_events', ['order_id' => $order->id, 'type' => 'shipped']);
    }

    public function test_non_admin_cannot_ship(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $order = $this->paidOrder();

        $response = $this->actingAs($user)->post(route('admin.sales.ship', $order), [
            'carrier' => 'Royal Mail',
            'tracking' => 'RM1234GB',
        ]);

        $response->assertForbidden();
    }
}
