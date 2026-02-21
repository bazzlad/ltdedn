<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Enums\ProductSaleStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CheckoutService
{
    public function __construct(private OrderStateService $orderStateService) {}

    public function createCheckout(Product $product, ?ProductSku $sku, ?string $customerEmail, string $orderCreationKey, ?int $userId = null): array
    {
        if (! $product->sell_through_ltdedn || ! $product->is_sellable || $product->sale_status !== ProductSaleStatus::Active) {
            return ['ok' => false, 'error' => 'This product is not currently available for purchase.'];
        }

        if (! $product->is_public && ! $userId) {
            return ['ok' => false, 'error' => 'This product is not currently available for purchase.'];
        }

        if ($sku && ! $sku->is_active) {
            return ['ok' => false, 'error' => 'This product variant is not currently available for purchase.'];
        }

        $amount = $sku ? (int) $sku->price_amount : (int) round(((float) $product->base_price) * 100);
        if ($amount < 1) {
            return ['ok' => false, 'error' => 'This product has no valid price set.'];
        }

        $order = null;
        $reservation = null;

        try {
            DB::transaction(function () use (&$order, &$reservation, $sku, $product, $amount, $orderCreationKey, $customerEmail, $userId) {
                $lockedSku = null;
                if ($sku) {
                    $lockedSku = ProductSku::query()->lockForUpdate()->find($sku->id);
                    if (! $lockedSku || ! $lockedSku->reserve(1)) {
                        return;
                    }
                }

                $editionQuery = $product->editions()
                    ->where('status', ProductEditionStatus::Available)
                    ->whereNotIn('id', function ($query) {
                        $query->select('product_edition_id')
                            ->from('inventory_reservations')
                            ->where('status', 'active')
                            ->whereNotNull('product_edition_id')
                            ->where('expires_at', '>', now());
                    });

                if ($lockedSku) {
                    $editionQuery->where('product_sku_id', $lockedSku->id);
                } else {
                    $editionQuery->whereNull('product_sku_id');
                }

                $edition = $editionQuery->lockForUpdate()->first();

                if (! $edition) {
                    if ($lockedSku) {
                        $lockedSku->release(1);
                    }

                    return;
                }

                $currency = $lockedSku ? $lockedSku->currency : ($product->currency ?: 'gbp');

                $order = Order::create([
                    'user_id' => $userId,
                    'status' => OrderStatus::Pending,
                    'currency' => $currency,
                    'subtotal_amount' => $amount,
                    'shipping_amount' => 0,
                    'total_amount' => $amount,
                    'customer_email' => $customerEmail,
                    'order_creation_key' => $orderCreationKey,
                    'checkout_expires_at' => now()->addMinutes(15),
                    'meta' => ['reservation_ttl_minutes' => 15],
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_edition_id' => $edition ? $edition->id : null,
                    'product_sku_id' => $lockedSku ? $lockedSku->id : null,
                    'product_name' => (string) $product->name,
                    'product_slug' => (string) $product->slug,
                    'sku_code_snapshot' => $lockedSku ? (string) $lockedSku->sku_code : 'STANDARD',
                    'attributes_snapshot' => $lockedSku ? ($lockedSku->attributes ?: []) : ['type' => 'standard'],
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'line_total_amount' => $amount,
                ]);

                $reservation = InventoryReservation::create([
                    'order_id' => $order->id,
                    'product_edition_id' => $edition ? $edition->id : null,
                    'product_sku_id' => $lockedSku ? $lockedSku->id : null,
                    'quantity' => 1,
                    'status' => 'active',
                    'expires_at' => now()->addMinutes(15),
                ]);
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
        }

        if (! $order || ! $reservation) {
            return ['ok' => false, 'error' => 'This item is sold out.'];
        }

        $lineItemName = $product->name.($sku ? ' ('.$sku->sku_code.')' : ' (STANDARD)');

        $stripeParams = [
            'mode' => 'payment',
            'success_url' => route('shop.success', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('shop.cancel', $order),
            'metadata[order_id]' => (string) $order->id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $order->currency,
            'line_items[0][price_data][unit_amount]' => $order->total_amount,
            'line_items[0][price_data][product_data][name]' => $lineItemName,
        ];

        if ($order->customer_email) {
            $stripeParams['customer_email'] = $order->customer_email;
        }

        $response = Http::asForm()
            ->withToken((string) config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', $stripeParams);

        if (! $response->successful() || ! $response->json('id') || ! $response->json('url')) {
            DB::transaction(function () use ($order, $reservation, $sku) {
                $this->orderStateService->markFailed($order);

                $reservation->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'stripe_session_failed',
                ]);

                if ($sku) {
                    $lockedSku = ProductSku::query()->lockForUpdate()->find($sku->id);
                    if ($lockedSku) {
                        $lockedSku->release(1);
                    }
                }
            });

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
}
