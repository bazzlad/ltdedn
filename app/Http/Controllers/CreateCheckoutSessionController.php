<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\CreateCheckoutSessionRequest;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CreateCheckoutSessionController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
    ) {}

    public function __invoke(CreateCheckoutSessionRequest $request): RedirectResponse
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

        return redirect()->away((string) $result['redirect']);
    }
}
