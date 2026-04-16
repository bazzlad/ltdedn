<?php

namespace Tests\Feature\Shop;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeRefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.webhook_secret', 'whsec_test_refund');
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $json = json_encode($payload);
        $timestamp = time();
        $sig = hash_hmac('sha256', $timestamp.'.'.$json, 'whsec_test_refund');

        return $this->withHeaders(['Stripe-Signature' => 't='.$timestamp.',v1='.$sig])
            ->postJson(route('webhooks.stripe'), $payload);
    }

    public function test_charge_refunded_webhook_reconciles_refunded_amount(): void
    {
        $order = Order::create([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 10000,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'refunded_amount' => 0,
            'stripe_payment_intent_id' => 'pi_wh_refund_1',
            'customer_email' => 'b@example.com',
            'paid_at' => now(),
        ]);

        $response = $this->postWebhook([
            'id' => 'evt_charge_refunded_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_test_1',
                    'payment_intent' => 'pi_wh_refund_1',
                    'amount_refunded' => 4500,
                    'refunds' => ['data' => [['id' => 're_x', 'amount' => 4500]]],
                ],
            ],
        ]);

        $response->assertOk();
        $order->refresh();

        $this->assertSame(4500, (int) $order->refunded_amount);
        $this->assertNotNull($order->last_refunded_at);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'stripe_refund_webhook',
        ]);
    }

    public function test_duplicate_charge_refunded_event_only_logs_once(): void
    {
        $order = Order::create([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 10000,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'refunded_amount' => 0,
            'stripe_payment_intent_id' => 'pi_wh_refund_2',
            'customer_email' => 'b@example.com',
            'paid_at' => now(),
        ]);

        $payload = [
            'id' => 'evt_charge_refunded_dup',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_dup',
                    'payment_intent' => 'pi_wh_refund_2',
                    'amount_refunded' => 1000,
                    'refunds' => ['data' => []],
                ],
            ],
        ];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(
            1,
            \App\Models\OrderEvent::query()
                ->where('order_id', $order->id)
                ->where('type', 'stripe_refund_webhook')
                ->count(),
        );
    }

    public function test_charge_dispute_created_logs_event_only(): void
    {
        $order = Order::create([
            'status' => 'paid',
            'currency' => 'gbp',
            'subtotal_amount' => 10000,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'refunded_amount' => 0,
            'stripe_payment_intent_id' => 'pi_wh_dispute_1',
            'customer_email' => 'b@example.com',
            'paid_at' => now(),
        ]);

        $response = $this->postWebhook([
            'id' => 'evt_dispute_1',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'id' => 'dp_test',
                    'payment_intent' => 'pi_wh_dispute_1',
                    'amount' => 10000,
                    'reason' => 'fraudulent',
                    'status' => 'needs_response',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'type' => 'dispute_created',
        ]);
        $order->refresh();
        $this->assertSame('paid', $order->status->value, 'dispute does not mutate order status in v1');
    }
}
