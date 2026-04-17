<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    /**
     * Record a shipment on a paid order. Updates carrier/tracking fields,
     * stamps shipped_at, and writes an OrderEvent. Idempotent-ish: calling
     * again will update the tracking fields and log a new event.
     *
     * @return array{ok: bool, error?: string}
     */
    public function markShipped(Order $order, string $carrier, string $tracking, User $actor): array
    {
        if ($order->status !== OrderStatus::Paid) {
            return ['ok' => false, 'error' => 'Order must be paid before it can be shipped.'];
        }

        if ((int) $order->refunded_amount >= (int) $order->total_amount && (int) $order->total_amount > 0) {
            return ['ok' => false, 'error' => 'Order has been fully refunded and cannot be shipped.'];
        }

        DB::transaction(function () use ($order, $carrier, $tracking, $actor) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (! $locked) {
                return;
            }

            $previousTracking = (string) $locked->shipping_tracking_number;

            $locked->update([
                'shipping_carrier' => $carrier,
                'shipping_tracking_number' => $tracking,
                'shipped_at' => $locked->shipped_at ?: now(),
            ]);

            OrderEvent::create([
                'order_id' => $locked->id,
                'user_id' => $actor->id,
                'type' => $previousTracking === '' ? 'shipped' : 'shipping_updated',
                'payload' => [
                    'carrier' => $carrier,
                    'tracking' => $tracking,
                    'previous_tracking' => $previousTracking ?: null,
                ],
            ]);
        });

        return ['ok' => true];
    }
}
