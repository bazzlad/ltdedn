<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function show(Request $request): Response
    {
        $cart = $this->cartService->resolveForRequest($request);
        $snapshot = $this->cartService->pricingSnapshot($cart);

        return Inertia::render('Shop/Cart', [
            'cart' => $snapshot,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $cart = $this->cartService->resolveForRequest($request);
        $this->cartService->clear($cart);

        return back()->with('status', 'Cart cleared.');
    }
}
