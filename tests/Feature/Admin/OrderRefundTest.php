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

    private function fakeStripe(?array $refundResponse, int $stripeAlreadyRefundedMinor = 0, int $refundStatus = 200): void
    {
        Http::fake([
            'https://api.stripe.com/v1/payment_intents/*' => Http::response([
                'id' => 'pi_test_refund',
                'latest_charge' => [
                    'id' => 'ch_test',
                    'amount_refunded' => $stripeAlreadyRefundedMinor,
                    'refunds' => ['data' => []],
                ],
            ], 200),
            'https://api.stripe.com/v1/refunds' => $refundResponse === null
                ? Http::response(['error' => ['message' => 'card_declined']], $refundStatus)
                : Http::response($refundResponse, $refundStatus),
        ]);
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

        $this->fakeStripe([
            'id' => 're_test_1',
            'amount' => 2500,
            'status' => 'succeeded',
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

        $this->fakeStripe([
            'id' => 're_test_2',
            'amount' => 10000,
            'status' => 'succeeded',
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

        $this->fakeStripe(null, 8000);

        $response = $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 5000,
            'reason' => 'Too much',
        ]);

        $response->assertSessionHasErrors('refund');
        Http::assertNotSent(function ($request) {
            return str_contains((string) $request->url(), '/v1/refunds');
        });

        $order->refresh();
        $this->assertSame(8000, (int) $order->refunded_amount);
    }

    public function test_stripe_api_failure_does_not_update_refunded_amount_and_logs_failure(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        $this->fakeStripe(null, 0, 400);

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

    public function test_refund_sends_idempotency_key_header_on_stripe_call(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder();

        $this->fakeStripe([
            'id' => 're_test_idem',
            'amount' => 1000,
            'status' => 'succeeded',
        ]);

        $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 1000,
            'reason' => 'Damaged',
        ]);

        Http::assertSent(function ($request) use ($order) {
            if (! str_contains((string) $request->url(), '/v1/refunds')) {
                return false;
            }
            $key = $request->header('Idempotency-Key')[0] ?? '';

            return str_starts_with($key, 'refund_'.$order->id.'_');
        });

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'refund_attempt',
        ]);
    }

    public function test_refund_reconciles_against_stripe_when_local_state_is_stale(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        // Local state says 0 refunded, but Stripe has already refunded £30
        // (e.g. a previous attempt succeeded at Stripe but lost its DB
        // update window and no webhook has landed yet).
        $order = $this->paidOrder(['refunded_amount' => 0]);

        $this->fakeStripe([
            'id' => 're_after_reconcile',
            'amount' => 2000,
            'status' => 'succeeded',
        ], stripeAlreadyRefundedMinor: 3000);

        $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 2000,
            'reason' => 'Top-up refund',
        ]);

        $order->refresh();
        // 3000 reconciled from Stripe + 2000 admin refund = 5000.
        $this->assertSame(5000, (int) $order->refunded_amount);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'refund_reconciled_pre_admin',
        ]);
    }

    public function test_refund_blocks_when_stripe_reports_already_fully_refunded(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->paidOrder(['refunded_amount' => 0]);

        $this->fakeStripe(null, stripeAlreadyRefundedMinor: 10000);

        $response = $this->actingAs($admin)->post(route('admin.sales.refund', $order), [
            'amount_minor' => 500,
            'reason' => 'Already refunded',
        ]);

        $response->assertSessionHasErrors('refund');
        Http::assertNotSent(function ($request) {
            return str_contains((string) $request->url(), '/v1/refunds');
        });

        $order->refresh();
        $this->assertSame(10000, (int) $order->refunded_amount);
    }
}
