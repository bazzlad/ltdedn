<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderReceivedAdminMail;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CommerceStateService
{
    public function __construct(private OrderStateService $orderStateService) {}

    /**
     * Enrich the order with customer/shipping/totals pulled off a Stripe
     * Checkout Session, then fulfil it. Used by the webhook, the poll-on-
     * return success page and the reconcile command — single source of
     * truth for "session says paid → turn our order into a paid order".
     *
     * @param  array<string, mixed>  $session  Stripe Checkout Session payload
     */
    public function fulfillFromSession(Order $order, array $session): bool
    {
        $this->applySessionFieldsToOrder($order, $session);
        $order->refresh();

        return $this->fulfillPaidOrder($order, [
            'stripe_checkout_session_id' => (string) data_get($session, 'id', $order->stripe_checkout_session_id),
            'stripe_payment_intent_id' => (string) data_get($session, 'payment_intent', $order->stripe_payment_intent_id),
        ]);
    }

    /**
     * Copy authoritative buyer + totals from a Stripe Checkout Session
     * onto the order. Only fills fields that are still empty locally —
     * never downgrades data the admin/webhook has already committed.
     *
     * @param  array<string, mixed>  $session
     */
    public function applySessionFieldsToOrder(Order $order, array $session): void
    {
        $updates = [];

        if (! $order->customer_email) {
            $email = (string) data_get($session, 'customer_details.email', '');
            if ($email !== '') {
                $updates['customer_email'] = $email;
            }
        }

        foreach (['subtotal_amount' => 'amount_subtotal', 'total_amount' => 'amount_total'] as $col => $path) {
            $val = data_get($session, $path);
            if ($val !== null) {
                $updates[$col] = (int) $val;
            }
        }

        $tax = data_get($session, 'total_details.amount_tax');
        if ($tax !== null) {
            $updates['tax_amount'] = (int) $tax;
        }

        $shippingTotal = data_get($session, 'shipping_cost.amount_total');
        if ($shippingTotal !== null) {
            $updates['shipping_amount'] = (int) $shippingTotal;
        }

        $shippingRateId = (string) data_get($session, 'shipping_cost.shipping_rate', '');
        if ($shippingRateId !== '') {
            $updates['shipping_rate_id'] = $shippingRateId;
        }

        // Stripe API 2025-03-31.basil moved the buyer's shipping address from
        // the top-level `shipping_details` to `collected_information.shipping_details`.
        // Read the new path first and fall back to the legacy path so older API
        // versions, replayed webhooks, and historical fixtures keep working.
        $addressMap = [
            'shipping_name' => ['collected_information.shipping_details.name', 'shipping_details.name'],
            'shipping_line1' => ['collected_information.shipping_details.address.line1', 'shipping_details.address.line1'],
            'shipping_line2' => ['collected_information.shipping_details.address.line2', 'shipping_details.address.line2'],
            'shipping_city' => ['collected_information.shipping_details.address.city', 'shipping_details.address.city'],
            'shipping_state' => ['collected_information.shipping_details.address.state', 'shipping_details.address.state'],
            'shipping_postal_code' => ['collected_information.shipping_details.address.postal_code', 'shipping_details.address.postal_code'],
            'shipping_country' => ['collected_information.shipping_details.address.country', 'shipping_details.address.country'],
        ];

        foreach ($addressMap as $col => $paths) {
            foreach ($paths as $path) {
                $val = data_get($session, $path);
                if ($val !== null && $val !== '') {
                    $updates[$col] = (string) $val;
                    break;
                }
            }
        }

        $phone = (string) data_get($session, 'customer_details.phone', '');
        if ($phone !== '' && ! $order->shipping_phone) {
            $updates['shipping_phone'] = $phone;
        }

        if ($updates !== []) {
            $order->update($updates);
        }
    }

    /**
     * Fulfil a paid order: mark paid, consume every active reservation and
     * its SKU stock, mark each reserved edition as Sold.
     *
     * Wraps its own transaction with pessimistic locking. Safe to call
     * inside an existing transaction (uses savepoints).
     */
    public function fulfillPaidOrder(Order $order, array $orderAttrs = []): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        return DB::transaction(function () use ($order, $orderAttrs) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);
            if (! $lockedOrder || $lockedOrder->status !== OrderStatus::Pending) {
                return false;
            }

            if (! $this->orderStateService->markPaid($lockedOrder, $orderAttrs)) {
                return false;
            }

            $reservations = InventoryReservation::where('order_id', $lockedOrder->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => 'consumed',
                    'consumed_at' => now(),
                ]);

                if ($reservation->product_sku_id) {
                    $sku = ProductSku::query()->lockForUpdate()->find($reservation->product_sku_id);
                    if ($sku) {
                        $sku->consume((int) $reservation->quantity);
                    }
                }

                if ($reservation->product_edition_id) {
                    ProductEdition::where('id', $reservation->product_edition_id)
                        ->where('status', ProductEditionStatus::Available->value)
                        ->update(['status' => ProductEditionStatus::Sold]);
                }
            }

            // Legacy: orders created before one-reservation-per-edition may
            // carry the edition on OrderItem. Keep marking those sold too.
            $singleItem = OrderItem::where('order_id', $lockedOrder->id)->first();
            if ($reservations->isEmpty() && $singleItem && $singleItem->product_edition_id) {
                ProductEdition::where('id', $singleItem->product_edition_id)
                    ->where('status', ProductEditionStatus::Available->value)
                    ->update(['status' => ProductEditionStatus::Sold]);
            }

            DB::afterCommit(fn () => $this->dispatchPaidNotifications($lockedOrder->fresh()));

            return true;
        });
    }

    /**
     * Dispatch buyer + admin emails exactly once per order. Guarded by a
     * meta stamp so webhook retries and manual re-fulfilments can't
     * double-send.
     */
    private function dispatchPaidNotifications(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        if (! empty($meta['confirmation_mailed_at'])) {
            return;
        }

        if ($order->customer_email) {
            Mail::to((string) $order->customer_email)->queue(new OrderConfirmationMail($order));
        }

        $adminEmail = (string) config('shop.admin_notification_email', '');
        if ($adminEmail !== '') {
            Mail::to($adminEmail)->queue(new OrderReceivedAdminMail($order));
        }

        $meta['confirmation_mailed_at'] = now()->toIso8601String();
        $order->update(['meta' => $meta]);
    }

    /**
     * Fail a pending order: mark failed, release every active reservation
     * and restore SKU stock.
     */
    public function failPendingOrder(Order $order, string $releaseReason = 'failed'): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        return DB::transaction(function () use ($order, $releaseReason) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);
            if (! $lockedOrder || $lockedOrder->status !== OrderStatus::Pending) {
                return false;
            }

            if (! $this->orderStateService->markFailed($lockedOrder)) {
                return false;
            }

            $reservations = InventoryReservation::where('order_id', $lockedOrder->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => $releaseReason,
                ]);

                if ($reservation->product_sku_id) {
                    $sku = ProductSku::query()->lockForUpdate()->find($reservation->product_sku_id);
                    if ($sku) {
                        $sku->release((int) $reservation->quantity);
                    }
                }
            }

            return true;
        });
    }
}
