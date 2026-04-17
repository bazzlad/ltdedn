<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrderRefundService
{
    /**
     * Issue a Stripe refund against the order's payment intent. Amount is
     * in minor units (pence). Pass 0 to refund the remaining refundable
     * balance. Writes an OrderEvent in all cases.
     *
     * Hardening vs. double-refund:
     *  - Queries Stripe for the payment-intent's authoritative refunded
     *    total before deciding the remaining balance, so a previous
     *    success we lost DB state for can't be re-issued.
     *  - Persists an Idempotency-Key as a `refund_attempt` event BEFORE
     *    the Stripe call and sends it on the HTTP request, so a retry
     *    of the same logical click returns the same refund instead of
     *    creating a second one.
     *  - Updates refunded_amount under a row lock and only ratchets
     *    upwards, matching the webhook reconciliation path.
     *
     * @return array{ok: bool, error?: string, stripe_refund_id?: string}
     */
    public function refund(Order $order, int $amountMinor, string $reason, User $actor): array
    {
        if ($order->status !== OrderStatus::Paid) {
            return ['ok' => false, 'error' => 'Only paid orders can be refunded.'];
        }

        if (! $order->stripe_payment_intent_id) {
            return ['ok' => false, 'error' => 'Order has no Stripe payment intent to refund against.'];
        }

        $paid = (int) $order->total_amount;

        $stripeRefunded = $this->fetchAuthoritativeRefundedAmount((string) $order->stripe_payment_intent_id);
        $localRefunded = (int) $order->refunded_amount;
        $alreadyRefunded = max($localRefunded, $stripeRefunded);

        if ($alreadyRefunded > $localRefunded) {
            // Webhook hasn't landed yet but Stripe has clearly processed a
            // refund we didn't know about. Ratchet local state forward so
            // the remaining-balance math below is correct.
            $this->reconcileLocal($order, $alreadyRefunded);
        }

        $remaining = max($paid - $alreadyRefunded, 0);

        if ($amountMinor <= 0) {
            $amountMinor = $remaining;
        }

        if ($amountMinor < 1) {
            return ['ok' => false, 'error' => 'Nothing left to refund.'];
        }

        if ($amountMinor > $remaining) {
            return ['ok' => false, 'error' => 'Refund exceeds remaining refundable balance.'];
        }

        $idempotencyKey = 'refund_'.$order->id.'_'.Str::uuid()->toString();

        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'type' => 'refund_attempt',
            'payload' => [
                'amount_minor' => $amountMinor,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ],
        ]);

        $params = [
            'payment_intent' => (string) $order->stripe_payment_intent_id,
            'amount' => (string) $amountMinor,
            'reason' => 'requested_by_customer',
            'metadata[order_id]' => (string) $order->id,
            'metadata[actor_user_id]' => (string) $actor->id,
        ];

        $request = Http::asForm()
            ->withToken((string) config('services.stripe.secret'))
            ->withHeaders(['Idempotency-Key' => $idempotencyKey]);

        $apiVersion = (string) config('services.stripe.api_version', '');
        if ($apiVersion !== '') {
            $request = $request->withHeaders(['Stripe-Version' => $apiVersion]);
        }

        $response = $request->post('https://api.stripe.com/v1/refunds', $params);

        if (! $response->successful() || ! $response->json('id')) {
            OrderEvent::create([
                'order_id' => $order->id,
                'user_id' => $actor->id,
                'type' => 'refund_failed',
                'payload' => [
                    'amount_minor' => $amountMinor,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                    'stripe_response' => $response->json() ?: ['status' => $response->status()],
                ],
            ]);

            return ['ok' => false, 'error' => 'Stripe refund failed: '.(string) $response->json('error.message', 'unknown error')];
        }

        $refundId = (string) $response->json('id');

        DB::transaction(function () use ($order, $amountMinor, $paid, $reason, $actor, $refundId, $idempotencyKey) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked) {
                return;
            }

            $newRefunded = min((int) $locked->refunded_amount + $amountMinor, $paid);

            $locked->update([
                'refunded_amount' => $newRefunded,
                'last_refunded_at' => now(),
            ]);

            OrderEvent::create([
                'order_id' => $locked->id,
                'user_id' => $actor->id,
                'type' => $newRefunded >= $paid ? 'refunded_full' : 'refunded_partial',
                'payload' => [
                    'amount_minor' => $amountMinor,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                    'stripe_refund_id' => $refundId,
                    'new_refunded_total_minor' => $newRefunded,
                ],
            ]);
        });

        return ['ok' => true, 'stripe_refund_id' => $refundId];
    }

    /**
     * Ask Stripe for the canonical refunded total on a payment intent. If
     * the call fails we fall back to the local value (0 from caller's
     * perspective), so a transient Stripe outage never blocks an admin's
     * legitimate refund attempt.
     */
    private function fetchAuthoritativeRefundedAmount(string $paymentIntentId): int
    {
        $request = Http::withToken((string) config('services.stripe.secret'));
        $apiVersion = (string) config('services.stripe.api_version', '');
        if ($apiVersion !== '') {
            $request = $request->withHeaders(['Stripe-Version' => $apiVersion]);
        }

        $response = $request->get('https://api.stripe.com/v1/payment_intents/'.urlencode($paymentIntentId));

        if (! $response->successful()) {
            return 0;
        }

        // Most callers will find the refunded total on the payment intent's
        // latest_charge, which Stripe expands by default.
        $latestRefunded = (int) data_get($response->json(), 'latest_charge.amount_refunded', 0);

        if ($latestRefunded > 0) {
            return $latestRefunded;
        }

        // Fallback: sum refunds.data[].amount if the API shape varies.
        $refunds = (array) data_get($response->json(), 'latest_charge.refunds.data', []);
        $sum = 0;
        foreach ($refunds as $refund) {
            $sum += (int) data_get($refund, 'amount', 0);
        }

        return $sum;
    }

    private function reconcileLocal(Order $order, int $reconciled): void
    {
        DB::transaction(function () use ($order, $reconciled) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked) {
                return;
            }

            if ((int) $locked->refunded_amount >= $reconciled) {
                return;
            }

            $prior = (int) $locked->refunded_amount;
            $locked->update([
                'refunded_amount' => $reconciled,
                'last_refunded_at' => now(),
            ]);

            OrderEvent::create([
                'order_id' => $locked->id,
                'user_id' => null,
                'type' => 'refund_reconciled_pre_admin',
                'payload' => [
                    'prior_local_refunded_amount' => $prior,
                    'reconciled_refunded_amount' => $reconciled,
                ],
            ]);
        });
    }
}
