<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.secret', 'sk_test_123');
    }

    private function paidOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 9000,
            'shipping_amount' => 500,
            'tax_amount' => 500,
            'total_amount' => 10000,
            'paid_at' => now(),
            'customer_email' => 'buyer@example.com',
            'stripe_payment_intent_id' => 'pi_test_refund',
            'refunded_amount' => 0,
        ], $overrides));
    }

    public function test_partial_refund_updates_order_and_logs_event(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_1',
                'amount' => 2500,
                'status' => 'succeeded',
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 2500,
            'reason' => 'Customer damaged item',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame(2500, (int) $order->refunded_amount);
        $this->assertNotNull($order->last_refunded_at);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'refunded_partial',
        ]);
    }

    public function test_full_refund_sets_refunded_amount_equal_to_total_and_logs_full_event(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_2',
                'amount' => 10000,
                'status' => 'succeeded',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 0,
            'reason' => 'Full refund',
        ]);

        $order->refresh();
        $this->assertSame(10000, (int) $order->refunded_amount);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'refunded_full',
        ]);
    }

    public function test_over_refund_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder(['refunded_amount' => 8000]);

        Http::fake();

        $response = $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 5000,
            'reason' => 'Too much',
        ]);

        $response->assertSessionHasErrors('refund');
        Http::assertNothingSent();

        $order->refresh();
        $this->assertSame(8000, (int) $order->refunded_amount);
    }

    public function test_stripe_api_failure_does_not_update_refunded_amount_and_logs_failure(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response(['error' => ['message' => 'card_declined']], 400),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 2500,
            'reason' => 'Try',
        ]);

        $response->assertSessionHasErrors('refund');
        $order->refresh();
        $this->assertSame(0, (int) $order->refunded_amount);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'refund_failed',
        ]);
    }

    public function test_non_admin_cannot_refund(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $order = $this->paidOrder();

        $response = $this->actingAs($user)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 500,
            'reason' => 'Nope',
        ]);

        $response->assertForbidden();
    }
}
