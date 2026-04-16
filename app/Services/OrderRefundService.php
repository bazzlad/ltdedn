<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrderRefundService
{
    /**
     * Issue a Stripe refund against the order's payment intent. Amount is
     * in minor units (pence). Pass 0 to refund the remaining refundable
     * balance. Writes an OrderEvent in all cases.
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
        $alreadyRefunded = (int) $order->refunded_amount;
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

        $params = [
            'payment_intent' => (string) $order->stripe_payment_intent_id,
            'amount' => (string) $amountMinor,
            'reason' => 'requested_by_customer',
            'metadata[order_id]' => (string) $order->id,
            'metadata[actor_user_id]' => (string) $actor->id,
        ];

        $request = Http::asForm()->withToken((string) config('services.stripe.secret'));
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
                    'stripe_response' => $response->json() ?: ['status' => $response->status()],
                ],
            ]);

            return ['ok' => false, 'error' => 'Stripe refund failed: '.(string) $response->json('error.message', 'unknown error')];
        }

        $refundId = (string) $response->json('id');

        DB::transaction(function () use ($order, $amountMinor, $paid, $reason, $actor, $refundId) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked) {
                return;
            }

            $newRefunded = (int) $locked->refunded_amount + $amountMinor;

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
                    'stripe_refund_id' => $refundId,
                    'new_refunded_total_minor' => $newRefunded,
                ],
            ]);
        });

        return ['ok' => true, 'stripe_refund_id' => $refundId];
    }
}
