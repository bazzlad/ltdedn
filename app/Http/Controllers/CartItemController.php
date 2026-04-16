<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\StoreCartItemRequest;
use App\Http\Requests\Shop\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CartItemController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        $cart = $this->cartService->resolveForRequest($request);

        $product = Product::query()->findOrFail($request->integer('product_id'));

        $sku = null;
        if ($request->filled('product_sku_id')) {
            $sku = ProductSku::query()->findOrFail($request->integer('product_sku_id'));
            if ((int) $sku->product_id !== (int) $product->id) {
                return back()->withErrors(['product_sku_id' => 'Invalid variant selection.']);
            }
        }

        $result = $this->cartService->addItem($cart, $product, $sku, $request->quantity());

        if (! $result['ok']) {
            return back()->withErrors(['cart' => (string) $result['error']]);
        }

        $message = ! empty($result['clamped'])
            ? 'Added to cart (quantity limited by stock).'
            : 'Added to cart.';

        return back()->with('status', $message);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        $result = $this->cartService->updateQuantity($cartItem, (int) $request->input('quantity'));

        if (! $result['ok']) {
            return back()->withErrors(['cart' => (string) $result['error']]);
        }

        return back()->with('status', 'Cart updated.');
    }

    public function destroy(\Illuminate\Http\Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        $this->cartService->removeItem($cartItem);

        return back()->with('status', 'Item removed.');
    }

    private function authorizeCartItem(\Illuminate\Http\Request $request, CartItem $cartItem): void
    {
        $cart = $this->cartService->resolveForRequest($request);

        if ((int) $cartItem->cart_id !== (int) $cart->id) {
            throw new AccessDeniedHttpException;
        }
    }
}
