<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductEditionStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductEdition;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;

class CommerceStateService
{
    public function __construct(private OrderStateService $orderStateService) {}

    /**
     * Fulfil a paid order: mark paid, consume reservation + stock, mark edition sold.
     *
     * Wraps its own transaction with pessimistic locking. Safe to call from
     * within an existing transaction (uses savepoints).
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

            $reservation = InventoryReservation::where('order_id', $lockedOrder->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($reservation) {
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
            }

            $item = OrderItem::where('order_id', $lockedOrder->id)->first();
            if ($item && $item->product_edition_id) {
                ProductEdition::where('id', $item->product_edition_id)
                    ->where('status', ProductEditionStatus::Available->value)
                    ->update(['status' => ProductEditionStatus::Sold]);
            }

            return true;
        });
    }

    /**
     * Fail a pending order: mark failed, release reservation + stock.
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

            $reservation = InventoryReservation::where('order_id', $lockedOrder->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return true;
            }

            $reservation->update([
                'status' => 'expired',
                'released_at' => now(),
                'release_reason' => $releaseReason,
            ]);

            if ($reservation->product_sku_id) {
                $sku = ProductSku::query()->lockForUpdate()->find($reservation->product_sku_id);
                if ($sku) {
                    $sku->release((int) $reservation->quantity);
                }
            }

            return true;
        });
    }
}
