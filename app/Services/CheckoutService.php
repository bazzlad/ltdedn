<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Models\Cart;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private OrderStateService $orderStateService,
        private ShippingRateService $shippingRateService,
    ) {}

    /**
     * Legacy single-item entry point. Builds a throwaway line and delegates
     * to the cart-based flow. Preserved so in-flight browser tabs continue
     * to work during rollout.
     *
     * @return array{ok: bool, redirect?: string, error?: string, order?: Order}
     */
    public function createCheckout(Product $product, ?ProductSku $sku, ?string $customerEmail, string $orderCreationKey, ?int $userId = null): array
    {
        $check = ProductAvailability::assertPurchasable($product, $sku, $userId);
        if (! $check['ok']) {
            return $check;
        }

        $lines = [[
            'product' => $product,
            'sku' => $sku,
            'quantity' => 1,
        ]];

        return $this->createCheckoutInternal($lines, $customerEmail, $orderCreationKey, $userId);
    }

    /**
     * Multi-line cart-driven checkout.
     *
     * @return array{ok: bool, redirect?: string, error?: string, order?: Order}
     */
    public function createCheckoutFromCart(Cart $cart, ?string $customerEmail, string $orderCreationKey, ?int $userId = null): array
    {
        $cart->loadMissing(['items.product', 'items.sku']);

        if ($cart->items->isEmpty()) {
            return ['ok' => false, 'error' => 'Your cart is empty.'];
        }

        $lines = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            $sku = $item->sku;

            if (! $product) {
                return ['ok' => false, 'error' => 'One of the products in your cart is no longer available.'];
            }

            $check = ProductAvailability::assertPurchasable($product, $sku, $userId);
            if (! $check['ok']) {
                return ['ok' => false, 'error' => (string) $check['error']];
            }

            $lines[] = [
                'product' => $product,
                'sku' => $sku,
                'quantity' => (int) $item->quantity,
            ];
        }

        return $this->createCheckoutInternal($lines, $customerEmail, $orderCreationKey, $userId);
    }

    /**
     * @param  list<array{product: Product, sku: ?ProductSku, quantity: int}>  $lines
     * @return array{ok: bool, redirect?: string, error?: string, order?: Order}
     */
    private function createCheckoutInternal(array $lines, ?string $customerEmail, string $orderCreationKey, ?int $userId): array
    {
        $currency = $this->resolveSharedCurrency($lines);
        if ($currency === null) {
            return ['ok' => false, 'error' => 'Cart contains items with mixed currencies.'];
        }

        $order = null;

        try {
            $order = DB::transaction(function () use ($lines, $currency, $orderCreationKey, $customerEmail, $userId) {
                $this->lockSkusInDeterministicOrder($lines);

                $subtotal = 0;
                foreach ($lines as $line) {
                    $subtotal += ProductAvailability::resolvePrice($line['product'], $line['sku']) * $line['quantity'];
                }

                $ttl = (int) config('shop.reservation_ttl_minutes', 15);

                $order = Order::create([
                    'user_id' => $userId,
                    'status' => OrderStatus::Pending,
                    'currency' => $currency,
                    'subtotal_amount' => $subtotal,
                    'shipping_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $subtotal,
                    'customer_email' => $customerEmail,
                    'order_creation_key' => $orderCreationKey,
                    'checkout_expires_at' => now()->addMinutes($ttl),
                    'meta' => ['reservation_ttl_minutes' => $ttl],
                ]);

                foreach ($lines as $line) {
                    $this->reserveLine($order, $line, $ttl);
                }

                return $order;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'order_creation_key')) {
                $existingOrder = Order::where('order_creation_key', $orderCreationKey)->first();
                if ($existingOrder && $existingOrder->status === OrderStatus::Pending) {
                    $meta = is_array($existingOrder->meta) ? $existingOrder->meta : [];
                    $url = isset($meta['checkout_url']) ? (string) $meta['checkout_url'] : '';
                    if ($url !== '') {
                        return ['ok' => true, 'redirect' => $url, 'order' => $existingOrder];
                    }
                }
            }

            throw $e;
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $order) {
            return ['ok' => false, 'error' => 'Unable to create the order.'];
        }

        $stripeParams = $this->buildStripeSessionParams($order, $lines);

        $request = Http::asForm()->withToken((string) config('services.stripe.secret'));
        $apiVersion = (string) config('services.stripe.api_version', '');
        if ($apiVersion !== '') {
            $request = $request->withHeaders(['Stripe-Version' => $apiVersion]);
        }

        $response = $request->post('https://api.stripe.com/v1/checkout/sessions', $stripeParams);

        if (! $response->successful() || ! $response->json('id') || ! $response->json('url')) {
            $this->rollbackOrderAfterStripeFailure($order);

            return ['ok' => false, 'error' => 'Unable to start checkout right now. Please try again.'];
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        $meta['checkout_url'] = (string) $response->json('url');

        $order->update([
            'stripe_checkout_session_id' => (string) $response->json('id'),
            'meta' => $meta,
        ]);

        return ['ok' => true, 'redirect' => (string) $response->json('url'), 'order' => $order];
    }

    /**
     * @param  list<array{product: Product, sku: ?ProductSku, quantity: int}>  $lines
     */
    private function resolveSharedCurrency(array $lines): ?string
    {
        $currency = null;
        foreach ($lines as $line) {
            $c = ProductAvailability::resolveCurrency($line['product'], $line['sku']);
            if ($currency === null) {
                $currency = $c;
            } elseif ($currency !== $c) {
                return null;
            }
        }

        return $currency;
    }

    /**
     * Lock every SKU row referenced by any line, in ascending ID order, so
     * two concurrent checkouts that share SKUs acquire locks in the same
     * sequence and never deadlock.
     *
     * @param  list<array{product: Product, sku: ?ProductSku, quantity: int}>  $lines
     */
    private function lockSkusInDeterministicOrder(array $lines): void
    {
        $ids = (new Collection($lines))
            ->map(fn ($line) => $line['sku']?->id)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        foreach ($ids as $id) {
            ProductSku::query()->lockForUpdate()->find($id);
        }
    }

    /**
     * Reserve stock + allocate editions + create OrderItem + one
     * InventoryReservation per edition unit. Throws RuntimeException
     * if stock or editions are insufficient so the surrounding
     * transaction rolls back.
     *
     * @param  array{product: Product, sku: ?ProductSku, quantity: int}  $line
     */
    private function reserveLine(Order $order, array $line, int $ttlMinutes): void
    {
        $product = $line['product'];
        $sku = $line['sku'];
        $qty = (int) $line['quantity'];

        if ($qty < 1) {
            throw new RuntimeException('Invalid quantity.');
        }

        if ($sku) {
            $lockedSku = ProductSku::query()->find($sku->id);
            if (! $lockedSku || ! $lockedSku->reserve($qty)) {
                throw new RuntimeException('Some items sold out during checkout.');
            }
        }

        $editions = $this->allocateEditions($product, $sku, $qty);
        if ($editions->count() < $qty) {
            throw new RuntimeException('Some items sold out during checkout.');
        }

        $unit = ProductAvailability::resolvePrice($product, $sku);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_edition_id' => $qty === 1 ? (int) $editions->first()->id : null,
            'product_sku_id' => $sku?->id,
            'product_name' => (string) $product->name,
            'product_slug' => (string) $product->slug,
            'sku_code_snapshot' => $sku ? (string) $sku->sku_code : 'STANDARD',
            'attributes_snapshot' => $sku ? ($sku->attributes ?: []) : ['type' => 'standard'],
            'quantity' => $qty,
            'unit_amount' => $unit,
            'line_total_amount' => $unit * $qty,
        ]);

        foreach ($editions as $edition) {
            InventoryReservation::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'product_edition_id' => $edition->id,
                'product_sku_id' => $sku?->id,
                'quantity' => 1,
                'status' => 'active',
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);
        }
    }

    /**
     * Fetch `$qty` available editions for the product/SKU, excluding any
     * already held by a live reservation, using a single locked query.
     */
    private function allocateEditions(Product $product, ?ProductSku $sku, int $qty): Collection
    {
        $query = $product->editions()
            ->where('status', ProductEditionStatus::Available)
            ->whereNotIn('id', function ($q) {
                $q->select('product_edition_id')
                    ->from('inventory_reservations')
                    ->where('status', 'active')
                    ->whereNotNull('product_edition_id')
                    ->where('expires_at', '>', now());
            });

        if ($sku) {
            $query->where('product_sku_id', $sku->id);
        } else {
            $query->whereNull('product_sku_id');
        }

        return $query->lockForUpdate()->limit($qty)->get();
    }

    /**
     * @param  list<array{product: Product, sku: ?ProductSku, quantity: int}>  $lines
     * @return array<string, mixed>
     */
    private function buildStripeSessionParams(Order $order, array $lines): array
    {
        $defaultTaxCode = (string) config('shop.default_tax_code', 'txcd_99999999');

        $params = [
            'mode' => 'payment',
            'success_url' => route('shop.success', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('shop.cancel', $order),
            'metadata[order_id]' => (string) $order->id,
        ];

        foreach (array_values($lines) as $i => $line) {
            $product = $line['product'];
            $sku = $line['sku'];
            $unit = ProductAvailability::resolvePrice($product, $sku);
            $name = $product->name.($sku ? ' ('.$sku->sku_code.')' : ' (STANDARD)');

            $params["line_items[$i][quantity]"] = (string) $line['quantity'];
            $params["line_items[$i][price_data][currency]"] = $order->currency;
            $params["line_items[$i][price_data][unit_amount]"] = (string) $unit;
            $params["line_items[$i][price_data][product_data][name]"] = $name;
            $params["line_items[$i][price_data][product_data][tax_code]"] = $defaultTaxCode;
        }

        if ($order->customer_email) {
            $params['customer_email'] = $order->customer_email;
        }

        if ((bool) config('services.stripe.tax_enabled', true)) {
            $params['automatic_tax[enabled]'] = 'true';
        }

        $allowedCountries = (array) config('shop.allowed_shipping_countries', []);
        foreach (array_values($allowedCountries) as $i => $code) {
            $params["shipping_address_collection[allowed_countries][$i]"] = (string) $code;
        }

        $rate = $this->shippingRateService->resolveForCart(
            $this->throwawayCartFromOrder($order),
            $order->shipping_country,
        );

        if ($rate) {
            if ($rate->stripe_rate_id) {
                $params['shipping_options[0][shipping_rate]'] = (string) $rate->stripe_rate_id;
            } else {
                $params['shipping_options[0][shipping_rate_data][type]'] = 'fixed_amount';
                $params['shipping_options[0][shipping_rate_data][display_name]'] = (string) $rate->label;
                $params['shipping_options[0][shipping_rate_data][fixed_amount][amount]'] = (string) $rate->amount_minor;
                $params['shipping_options[0][shipping_rate_data][fixed_amount][currency]'] = (string) ($rate->currency ?: $order->currency);
            }
        }

        return $params;
    }

    /**
     * Release all active reservations, return SKU stock, mark order Failed.
     */
    private function rollbackOrderAfterStripeFailure(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $reservations = InventoryReservation::where('order_id', $order->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'stripe_session_failed',
                ]);

                if ($reservation->product_sku_id) {
                    $sku = ProductSku::query()->lockForUpdate()->find($reservation->product_sku_id);
                    if ($sku) {
                        $sku->release((int) $reservation->quantity);
                    }
                }
            }

            $this->orderStateService->markFailed($order);
        });
    }

    private function throwawayCartFromOrder(Order $order): Cart
    {
        $cart = new Cart;
        $cart->id = $order->id;
        $cart->user_id = $order->user_id;
        $cart->currency = (string) $order->currency;

        return $cart;
    }

    public function shippingRateService(): ShippingRateService
    {
        return $this->shippingRateService;
    }
}
