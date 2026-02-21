<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

        if (! $order->customer_email) {
            $stripeEmail = (string) data_get($session, 'customer_details.email', '');
            if ($stripeEmail !== '') {
                $order->update(['customer_email' => $stripeEmail]);
            }
        }

        $this->commerceStateService->fulfillPaidOrder($order, [
            'stripe_checkout_session_id' => (string) data_get($session, 'id', $order->stripe_checkout_session_id),
            'stripe_payment_intent_id' => (string) data_get($session, 'payment_intent', $order->stripe_payment_intent_id),
        ]);
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
}
