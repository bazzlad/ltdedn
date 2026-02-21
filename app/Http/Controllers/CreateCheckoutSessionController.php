<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSku;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateCheckoutSessionController extends Controller
{
    public function __construct(private CheckoutService $checkoutService) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_sku_id' => ['nullable', 'integer', 'exists:product_skus,id'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        if ((int) $product->artist_id !== (int) $validated['artist_id']) {
            return back()->withErrors(['product_id' => 'Invalid product selection.'])->withInput();
        }

        if (! $product->is_public && ! $request->user()) {
            return redirect()->guest(route('login'))
                ->with('status', 'You must be logged in to purchase this product.');
        }

        $sku = null;
        if (! empty($validated['product_sku_id'])) {
            $sku = ProductSku::query()->findOrFail($validated['product_sku_id']);

            if ((int) $sku->product_id !== (int) $product->id) {
                return back()->withErrors(['product_sku_id' => 'Invalid product selection.'])->withInput();
            }
        }

        if ($product->editions()->count() < 1) {
            return back()->withErrors(['product_id' => 'This product cannot be sold until editions are created.'])->withInput();
        }

        $orderCreationKey = (string) ($request->header('Idempotency-Key') ?: Str::uuid());

        $customerEmail = $request->user() ? $request->user()->email : null;

        $result = $this->checkoutService->createCheckout(
            product: $product,
            sku: $sku,
            customerEmail: $customerEmail,
            orderCreationKey: $orderCreationKey,
            userId: $request->user() ? $request->user()->id : null,
        );

        if (! $result['ok']) {
            return back()->with('error', (string) $result['error']);
        }

        return redirect()->away((string) $result['redirect']);
    }
}
