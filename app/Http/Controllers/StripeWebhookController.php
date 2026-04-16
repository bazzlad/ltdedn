<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\StripeEvent;
use App\Services\CommerceStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function __construct(private CommerceStateService $commerceStateService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '' || $sigHeader === '') {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret, self::SIGNATURE_TOLERANCE_SECONDS);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $eventData = $event->toArray();
        $eventId = (string) data_get($eventData, 'id', '');
        $type = (string) data_get($eventData, 'type', '');
        $payloadHash = hash('sha256', $payload);

        if ($eventId === '' || $type === '') {
            return response()->json(['message' => 'Invalid event payload'], 400);
        }

        $session = (array) data_get($eventData, 'data.object', []);

        $alreadyProcessed = false;
        $payloadMismatch = false;

        DB::transaction(function () use (&$alreadyProcessed, &$payloadMismatch, $eventId, $type, $payloadHash, $session) {
            $existing = StripeEvent::where('event_id', $eventId)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->type !== $type || $existing->payload_hash !== $payloadHash) {
                    $payloadMismatch = true;

                    return;
                }

                if ($existing->processed_at) {
                    $alreadyProcessed = true;

                    return;
                }
            }

            $stripeEvent = $existing ?: StripeEvent::create([
                'event_id' => $eventId,
                'type' => $type,
                'payload_hash' => $payloadHash,
            ]);

            if ($type === 'checkout.session.completed') {
                $this->handleSessionCompleted($session);
            }

            if ($type === 'checkout.session.expired' || $type === 'checkout.session.async_payment_failed') {
                $this->handleSessionFailed($session, $type);
            }

            if ($type === 'charge.refunded') {
                $this->handleChargeRefunded($session);
            }

            if ($type === 'charge.dispute.created') {
                $this->handleChargeDisputeCreated($session);
            }

            $stripeEvent->update([
                'processed_at' => now(),
            ]);
        });

        if ($payloadMismatch) {
            return response()->json(['message' => 'Conflicting event payload'], 409);
        }

        if ($alreadyProcessed) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        return response()->json(['ok' => true]);
    }

    private function handleSessionCompleted(array $session): void
    {
        $orderId = (int) data_get($session, 'metadata.order_id');
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $updates = $this->extractSessionFieldsForOrder($session, $order);
        if ($updates !== []) {
            $order->update($updates);
            $order->refresh();
        }

        $this->commerceStateService->fulfillPaidOrder($order, [
            'stripe_checkout_session_id' => (string) data_get($session, 'id', $order->stripe_checkout_session_id),
            'stripe_payment_intent_id' => (string) data_get($session, 'payment_intent', $order->stripe_payment_intent_id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSessionFieldsForOrder(array $session, Order $order): array
    {
        $updates = [];

        if (! $order->customer_email) {
            $stripeEmail = (string) data_get($session, 'customer_details.email', '');
            if ($stripeEmail !== '') {
                $updates['customer_email'] = $stripeEmail;
            }
        }

        $subtotal = data_get($session, 'amount_subtotal');
        if ($subtotal !== null) {
            $updates['subtotal_amount'] = (int) $subtotal;
        }

        $total = data_get($session, 'amount_total');
        if ($total !== null) {
            $updates['total_amount'] = (int) $total;
        }

        $tax = data_get($session, 'total_details.amount_tax');
        if ($tax !== null) {
            $updates['tax_amount'] = (int) $tax;
        }

        $shippingTotal = data_get($session, 'shipping_cost.amount_total');
        if ($shippingTotal !== null) {
            $updates['shipping_amount'] = (int) $shippingTotal;
        }

        $shippingRateId = (string) data_get($session, 'shipping_cost.shipping_rate', '');
        if ($shippingRateId !== '') {
            $updates['shipping_rate_id'] = $shippingRateId;
        }

        $shippingName = (string) data_get($session, 'shipping_details.name', '');
        if ($shippingName !== '') {
            $updates['shipping_name'] = $shippingName;
        }

        $addressMap = [
            'shipping_line1' => 'shipping_details.address.line1',
            'shipping_line2' => 'shipping_details.address.line2',
            'shipping_city' => 'shipping_details.address.city',
            'shipping_state' => 'shipping_details.address.state',
            'shipping_postal_code' => 'shipping_details.address.postal_code',
            'shipping_country' => 'shipping_details.address.country',
        ];

        foreach ($addressMap as $col => $path) {
            $val = data_get($session, $path);
            if ($val !== null && $val !== '') {
                $updates[$col] = (string) $val;
            }
        }

        $phone = (string) data_get($session, 'customer_details.phone', '');
        if ($phone !== '' && ! $order->shipping_phone) {
            $updates['shipping_phone'] = $phone;
        }

        return $updates;
    }

    private function handleSessionFailed(array $session, string $reason): void
    {
        $orderId = (int) data_get($session, 'metadata.order_id');
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $this->commerceStateService->failPendingOrder($order, $reason);
    }

    /**
     * Reconcile Stripe's authoritative `amount_refunded` back onto the order.
     * Handles both admin-UI refunds (already recorded) and Stripe-dashboard
     * refunds by staff (never seen before) — we always trust Stripe's total.
     */
    private function handleChargeRefunded(array $charge): void
    {
        $paymentIntentId = (string) data_get($charge, 'payment_intent', '');
        if ($paymentIntentId === '') {
            return;
        }

        $order = Order::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (! $order) {
            return;
        }

        $amountRefunded = (int) data_get($charge, 'amount_refunded', 0);

        $order->update([
            'refunded_amount' => $amountRefunded,
            'last_refunded_at' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => null,
            'type' => 'stripe_refund_webhook',
            'payload' => [
                'amount_refunded' => $amountRefunded,
                'charge_id' => (string) data_get($charge, 'id', ''),
                'refunds' => data_get($charge, 'refunds.data', []),
            ],
        ]);
    }

    /**
     * Dispute opened against a charge. Log-only for v1 — no automatic
     * state change on the order.
     */
    private function handleChargeDisputeCreated(array $dispute): void
    {
        $paymentIntentId = (string) data_get($dispute, 'payment_intent', '');
        if ($paymentIntentId === '') {
            return;
        }

        $order = Order::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (! $order) {
            return;
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => null,
            'type' => 'dispute_created',
            'payload' => [
                'dispute_id' => (string) data_get($dispute, 'id', ''),
                'amount' => (int) data_get($dispute, 'amount', 0),
                'reason' => (string) data_get($dispute, 'reason', ''),
                'status' => (string) data_get($dispute, 'status', ''),
            ],
        ]);
    }
}
