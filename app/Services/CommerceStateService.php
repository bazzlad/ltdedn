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
