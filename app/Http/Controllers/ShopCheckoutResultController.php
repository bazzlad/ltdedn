<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CommerceStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ShopCheckoutResultController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CommerceStateService $commerceStateService,
    ) {}

    /**
     * Renders the embedded Stripe Checkout page. Reads the client_secret
     * stashed on the order (written by CheckoutService when the session
     * was created) and hands it to the Vue component so Stripe.js can
     * mount the payment iframe. Re-entrant on refresh.
     */
    public function pay(Request $request, Order $order): Response|RedirectResponse
    {
        $this->authorizeAccess($request, $order);

        if ($order->status !== OrderStatus::Pending) {
            return redirect()->route('shop.success', $order);
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        $clientSecret = isset($meta['client_secret']) ? (string) $meta['client_secret'] : '';

        if ($clientSecret === '') {
            return redirect()->route('cart.show')->withErrors([
                'cart' => 'That checkout session has expired — please try again.',
            ]);
        }

        return Inertia::render('Shop/EmbeddedCheckout', [
            'clientSecret' => $clientSecret,
            'publishableKey' => (string) config('services.stripe.publishable_key'),
            'order' => [
                'id' => $order->id,
                'total_amount' => (int) $order->total_amount,
                'currency' => (string) $order->currency,
            ],
        ]);
    }

    public function success(Request $request, Order $order): Response
    {
        $this->authorizeAccess($request, $order);

        $freshFromStripe = $request->query('session_id') === $order->stripe_checkout_session_id
            && $order->stripe_checkout_session_id !== null;

        // Fresh arrival from Stripe: clear the cart so the user doesn't see
        // the items they just paid for sitting there waiting to be bought
        // again. Query param `session_id` is only appended on the return
        // redirect, so this runs at most once per completed checkout.
        if ($freshFromStripe) {
            $this->cartService->clear($this->cartService->resolveForRequest($request));
        }

        // Poll-on-return: if the webhook hasn't landed yet (stripe listen
        // dropped locally, or a transient prod outage), fetch the session
        // from Stripe and fulfil the order synchronously. Best-effort — any
        // failure falls through and the reconcile cron picks it up.
        if ($freshFromStripe && $order->status === OrderStatus::Pending) {
            $this->pollStripeAndFulfil($order);
            $order->refresh();
        }

        return Inertia::render('ShopResult', [
            'status' => 'success',
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
            ],
        ]);
    }

    private function pollStripeAndFulfil(Order $order): void
    {
        try {
            $secret = (string) config('services.stripe.secret');
            if ($secret === '' || ! $order->stripe_checkout_session_id) {
                return;
            }

            $request = Http::withToken($secret)->timeout(5);
            $apiVersion = (string) config('services.stripe.api_version', '');
            if ($apiVersion !== '') {
                $request = $request->withHeaders(['Stripe-Version' => $apiVersion]);
            }

            $response = $request->get('https://api.stripe.com/v1/checkout/sessions/'.$order->stripe_checkout_session_id);
            if (! $response->successful()) {
                return;
            }

            $session = (array) $response->json();
            if ((string) ($session['payment_status'] ?? '') !== 'paid') {
                return;
            }

            $this->commerceStateService->fulfillFromSession($order, $session);
        } catch (Throwable $e) {
            // Don't let a Stripe outage break the success page. The reconcile
            // command is the authoritative fallback.
            Log::warning('Poll-on-return fulfil failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function cancel(Request $request, Order $order): Response
    {
        $this->authorizeAccess($request, $order);

        return Inertia::render('ShopResult', [
            'status' => 'cancel',
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
            ],
        ]);
    }

    /**
     * Ensure only the order owner (or the Stripe redirect with session_id) can view the result.
     */
    private function authorizeAccess(Request $request, Order $order): void
    {
        if ($request->user() && $order->user_id === $request->user()->id) {
            return;
        }

        if ($request->query('session_id') && $order->stripe_checkout_session_id === $request->query('session_id')) {
            return;
        }

        if ($order->order_creation_key && $request->query('key') === $order->order_creation_key) {
            return;
        }

        throw new NotFoundHttpException;
    }
}
