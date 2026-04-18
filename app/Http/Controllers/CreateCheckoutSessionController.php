<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\CreateCheckoutSessionRequest;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CreateCheckoutSessionController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
    ) {}

    public function __invoke(CreateCheckoutSessionRequest $request): RedirectResponse|Response
    {
        $cart = $this->cartService->resolveForRequest($request);
        $cart->loadMissing(['items.product', 'items.sku']);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.show')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $orderCreationKey = (string) ($request->header('Idempotency-Key') ?: Str::uuid());

        $user = $request->user();
        $customerEmail = $user
            ? (string) $user->email
            : ($request->input('email') ? (string) $request->input('email') : null);

        $result = $this->checkoutService->createCheckoutFromCart(
            cart: $cart,
            customerEmail: $customerEmail,
            orderCreationKey: $orderCreationKey,
            userId: $user?->id,
        );

        if (! $result['ok']) {
            return redirect()->route('cart.show')->withErrors(['cart' => (string) $result['error']]);
        }

        // Inertia::location returns a 409 + X-Inertia-Location header for XHR
        // clients (which the Inertia JS client intercepts and does a real
        // full-page navigate); falls back to a 302 for plain requests. We
        // need this instead of redirect()->away() because the frontend
        // submits via router.post() — Axios would otherwise try to follow
        // the 302 cross-origin to checkout.stripe.com and trip CORS.
        return Inertia::location((string) $result['redirect']);
    }
}
