<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShopCheckoutResultController extends Controller
{
    public function __construct(private CartService $cartService) {}

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

        // Fresh arrival from Stripe (query param `session_id` is only appended
        // on the return redirect). Clear the cart so the user doesn't see the
        // items they just paid for sitting there waiting to be bought again.
        if ($request->query('session_id') === $order->stripe_checkout_session_id) {
            $this->cartService->clear($this->cartService->resolveForRequest($request));
        }

        return Inertia::render('ShopResult', [
            'status' => 'success',
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
            ],
        ]);
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
