<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShopCheckoutResultController extends Controller
{
    public function __construct(private CartService $cartService) {}

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
